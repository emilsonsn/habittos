<?php

namespace App\Http\Controllers;

use App\Enums\StudySection;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudyController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $studySection = StudySection::from($request->route('section', 'dashboard'));

        return view('studies.index', compact('workspace', 'studySection'));
    }

    public function file(Request $request, Workspace $workspace, int $material)
    {
        $record = $workspace->studyMaterials()->findOrFail($material);
        abort_unless($record->path && Storage::disk('local')->exists($record->path), 404);
        $preview = $request->boolean('preview') && in_array($record->mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);

        return Storage::disk('local')->response($record->path, $record->original_name, [
            'Content-Type' => $record->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ], $preview ? 'inline' : 'attachment');
    }
}
