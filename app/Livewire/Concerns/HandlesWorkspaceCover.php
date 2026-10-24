<?php

namespace App\Livewire\Concerns;

use App\Services\WorkspaceCover;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;

trait HandlesWorkspaceCover
{
    use WithFileUploads;

    public $cover;

    public $positionX = 50;

    public $positionY = 50;

    #[Locked]
    public bool $positioning = false;

    #[Locked]
    public bool $coverReady = false;

    #[Locked]
    public int $coverVersion = 0;

    public function updatedCover(): void
    {
        $this->reset('positioning', 'coverReady', 'positionX', 'positionY');
        $this->coverVersion++;
        $this->validateOnly('cover', ['cover' => WorkspaceCover::rules()]);
        $this->positioning = $this->cover !== null;
    }

    public function confirmCoverPosition(): void
    {
        abort_unless($this->positioning, 422);
        $this->validate(['cover' => ['required', ...WorkspaceCover::rules()], ...WorkspaceCover::positionRules()]);
        $this->positioning = false;
        $this->coverReady = true;
    }

    public function cancelCoverPosition(): void
    {
        $this->resetCoverDraft();
    }

    protected function resetCoverDraft(): void
    {
        $this->reset('cover', 'positionX', 'positionY', 'positioning', 'coverReady');
        $this->coverVersion++;
        $this->resetValidation(['cover', 'positionX', 'positionY']);
    }

    protected function validateCoverDraft(): void
    {
        $this->validate(['cover' => WorkspaceCover::rules(), ...WorkspaceCover::positionRules()]);
        if ($this->cover && ! $this->coverReady) {
            throw ValidationException::withMessages(['cover' => 'Confirme o enquadramento da capa antes de salvar.']);
        }
    }
}
