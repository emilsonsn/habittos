<?php

namespace App\Http\Controllers;

use App\Enums\NoteSection;
use App\Models\Workspace;

class NoteController extends Controller
{
    public function index(Workspace $workspace, string $section = 'dashboard')
    {
        $noteSection = NoteSection::from($section);

        return view('notes.index', compact('workspace', 'noteSection'));
    }
}
