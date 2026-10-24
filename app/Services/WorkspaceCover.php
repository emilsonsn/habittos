<?php

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class WorkspaceCover
{
    public static function rules(): array
    {
        return ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
    }

    public static function positionRules(): array
    {
        return [
            'positionX' => ['required', 'integer', 'between:0,100'],
            'positionY' => ['required', 'integer', 'between:0,100'],
        ];
    }

    public function save(Workspace $workspace, ?UploadedFile $cover, int $positionX = 50, int $positionY = 50): void
    {
        validator(compact('cover', 'positionX', 'positionY'), ['cover' => self::rules(), ...self::positionRules()])->validate();
        $oldPath = $workspace->cover_path;
        $path = $cover?->store('workspace-covers/'.$workspace->id, 'local');
        if ($cover && ! $path) {
            throw new RuntimeException('Não foi possível salvar a capa do ambiente.');
        }
        try {
            $workspace->update(['cover_path' => $path, 'cover_position_x' => $cover ? $positionX : 50, 'cover_position_y' => $cover ? $positionY : 50]);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
        if ($oldPath && $oldPath !== $path) {
            Storage::disk('local')->delete($oldPath);
        }
    }
}
