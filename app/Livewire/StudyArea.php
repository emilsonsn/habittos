<?php

namespace App\Livewire;

use App\Enums\Permission;
use App\Enums\StudyMaterialStatus;
use App\Enums\StudySection;
use App\Enums\StudyStatus;
use App\Models\Workspace;
use App\Services\ManageStudies;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class StudyArea extends Component
{
    use WithFileUploads, WithPagination;

    #[Locked]
    public int $workspaceId;

    #[Locked]
    public string $section = 'dashboard';

    #[Url(as: 'roadmap')]
    public ?int $roadmapId = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $display = 'canvas';

    #[Url(as: 'type')]
    public string $materialType = '';

    #[Url(as: 'material_status')]
    public string $materialStatus = '';

    #[Locked]
    public ?string $formKind = null;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public int $formVersion = 0;

    #[Locked]
    public array $canvasState = [];

    public array $form = [];

    public $upload = null;

    public string $message = '';

    public ?string $formSuccess = null;

    public function mount(int $workspaceId, string $section = 'dashboard'): void
    {
        $this->workspaceId = $workspaceId;
        $this->section = StudySection::from($section)->value;
        $this->workspace();
    }

    private function workspace(): Workspace
    {
        abort_unless(auth()->check(), 401);
        $workspace = auth()->user()->workspaces()->findOrFail($this->workspaceId);
        app(ManageStudies::class)->authorize($workspace, false);

        return $workspace;
    }

    public function updatedSearch(): void
    {
        $this->resetLists();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetLists();
    }

    public function updatedMaterialType(): void
    {
        $this->resetLists();
    }

    public function updatedMaterialStatus(): void
    {
        $this->resetLists();
    }

    public function updatedRoadmapId(): void
    {
        if ($this->roadmapId !== null) {
            $this->workspace()->studyRoadmaps()->findOrFail($this->roadmapId);
        }
        $this->closeForm();
        $this->resetLists();
    }

    private function resetLists(): void
    {
        $this->resetPage();
        $this->resetPage('materialsPage');
        $this->resetPage('sessionsPage');
    }

    public function updatedForm($value, string $key): void
    {
        if ($this->formKind === 'material' && $key === 'study_roadmap_id') {
            $this->form['study_topic_id'] = null;
        }
        if ($this->formKind === 'session' && $key === 'study_topic_id') {
            $this->form['checkpoint'] = $value ? $this->workspace()->studyTopics()->findOrFail($value)->checkpoint ?? '' : '';
        }
    }

    public function updatedUpload(): void
    {
        $this->validate(['upload' => ManageStudies::uploadRules()]);
    }

    public function openForm(string $kind, ?int $id = null, ?int $parentId = null): void
    {
        $workspace = $this->workspace();
        app(ManageStudies::class)->authorize($workspace);
        abort_unless(in_array($kind, ['roadmap', 'step', 'topic', 'session', 'material'], true), 422);
        abort_if($kind === 'session' && $id !== null, 422);
        $this->closeForm();
        $this->formKind = $kind;
        $this->editingId = $id;
        $this->formVersion++;
        $this->form = match ($kind) {
            'roadmap' => ['title' => '', 'description' => '', 'color' => '#9589e8', 'status' => 'not_started', 'due_date' => ''],
            'step' => ['title' => '', 'study_roadmap_id' => $parentId ?? $this->roadmapId],
            'topic' => ['title' => '', 'description' => '', 'checkpoint' => '', 'status' => 'not_started', 'study_step_id' => $parentId],
            'session' => ['study_topic_id' => $parentId, 'studied_on' => now(config('app.timezone'))->toDateString(), 'minutes' => '', 'notes' => '', 'checkpoint' => ''],
            'material' => ['title' => '', 'url' => '', 'status' => 'to_read', 'study_roadmap_id' => $this->roadmapId, 'study_topic_id' => $parentId],
        };
        if ($kind === 'step' && ! $id) {
            $workspace->studyRoadmaps()->findOrFail($this->form['study_roadmap_id']);
        } elseif ($kind === 'topic' && ! $id) {
            $workspace->studySteps()->findOrFail($parentId);
        } elseif (in_array($kind, ['session', 'material'], true) && $parentId) {
            $topic = $workspace->studyTopics()->findOrFail($parentId);
            if ($kind === 'session') {
                $this->form['checkpoint'] = $topic->checkpoint ?? '';
            } else {
                $this->form['study_roadmap_id'] = $topic->study_roadmap_id;
            }
        }
        if ($id !== null) {
            $record = match ($kind) {
                'roadmap' => $workspace->studyRoadmaps()->findOrFail($id),
                'step' => $workspace->studySteps()->findOrFail($id),
                'topic' => $workspace->studyTopics()->findOrFail($id),
                'material' => $workspace->studyMaterials()->findOrFail($id),
            };
            foreach (array_keys($this->form) as $key) {
                $value = $record->$key;
                $this->form[$key] = $value instanceof \BackedEnum ? $value->value : ($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : ($value ?? ''));
            }
        }
    }

    public function closeForm(): void
    {
        $this->reset('formKind', 'editingId', 'form', 'upload', 'formSuccess');
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->persist();
    }

    public function saveAndCreateAnother(): void
    {
        abort_if($this->editingId !== null, 403);
        $this->persist(true);
    }

    private function persist(bool $another = false): void
    {
        $workspace = $this->workspace();
        $service = app(ManageStudies::class);
        $service->authorize($workspace);
        abort_unless($this->formKind !== null, 422);
        $kind = $this->formKind;
        $this->formSuccess = null;
        $this->resetValidation();
        try {
            $record = match ($kind) {
                'roadmap' => $service->saveRoadmap($workspace, $this->form, $this->editingId),
                'step' => $service->saveStep($workspace, (int) ($this->form['study_roadmap_id'] ?? 0), (string) ($this->form['title'] ?? ''), $this->editingId),
                'topic' => $service->saveTopic($workspace, $this->form, $this->editingId),
                'session' => $service->recordSession($workspace, $this->form),
                'material' => $service->saveMaterial($workspace, $this->form, $this->upload, $this->editingId),
            };
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $text) {
                    $this->addError('form.'.$key, $text);
                }
            }

            return;
        }
        $parentId = match ($kind) {
            'step' => $record->study_roadmap_id, 'topic' => $record->study_step_id,
            'session', 'material' => $record->study_topic_id, default => null,
        };
        $this->message = 'Registro salvo nos seus estudos.';
        $this->closeForm();
        $this->resetLists();
        if ($another) {
            $this->openForm($kind, parentId: $parentId);
            $this->formSuccess = 'Registro salvo. Você já pode cadastrar o próximo.';
        } elseif ($kind === 'roadmap') {
            $this->redirect(route('studies.roadmaps', [$workspace, 'roadmap' => $record->id]), navigate: true);
        }
    }

    public function setTopicStatus(int $id, string $status): void
    {
        app(ManageStudies::class)->setTopicStatus($this->workspace(), $id, $status);
        $this->message = 'Status do tópico atualizado.';
    }

    public function reorderStep(int $id, int $position): void
    {
        app(ManageStudies::class)->reorderStep($this->workspace(), $id, $position);
    }

    public function reorderTopic(int $id, int $position, ?int $stepId = null): void
    {
        app(ManageStudies::class)->reorderTopic($this->workspace(), $id, $position, $stepId);
    }

    public function saveCanvasPositions(int $roadmapId, array $positions): void
    {
        app(ManageStudies::class)->saveCanvasPositions($this->workspace(), $roadmapId, $positions);
    }

    public function connectSteps(int $roadmapId, int $sourceId, int $targetId): void
    {
        app(ManageStudies::class)->connectSteps($this->workspace(), $roadmapId, $sourceId, $targetId);
    }

    public function disconnectSteps(int $roadmapId, int $connectionId): void
    {
        app(ManageStudies::class)->disconnectSteps($this->workspace(), $roadmapId, $connectionId);
    }

    public function deleteRecord(string $kind, int $id): void
    {
        app(ManageStudies::class)->delete($this->workspace(), $kind, $id);
        $this->closeForm();
        if ($kind === 'roadmap' && $this->roadmapId === $id) {
            $this->roadmapId = null;
        }
        $this->resetLists();
        $this->message = 'Registro excluído.';
    }

    public function render()
    {
        $workspace = $this->workspace();
        validator(['search' => $this->search, 'display' => $this->display, 'statusFilter' => $this->statusFilter], [
            'search' => ['string', 'max:200'], 'display' => ['in:canvas,list,board'],
            'statusFilter' => ['nullable', Rule::in(StudyStatus::values())],
        ])->validate();
        validator(['type' => $this->materialType, 'status' => $this->materialStatus], [
            'type' => ['nullable', 'in:file,link,pdf,image'],
            'status' => ['nullable', Rule::in(StudyMaterialStatus::values())],
        ])->validate();
        $counts = ['topics', 'topics as completed_topics_count' => fn ($query) => $query->where('status', 'completed')];
        $roadmapOptions = $workspace->studyRoadmaps()->orderBy('title')->get();
        $roadmapsQuery = $workspace->studyRoadmaps()->withCount($counts)->orderByDesc('updated_at')->orderByDesc('id');
        if ($this->search !== '') {
            $roadmapsQuery->where('title', 'like', '%'.$this->search.'%');
        }
        if ($this->statusFilter !== '') {
            $roadmapsQuery->where('status', $this->statusFilter);
        }
        $roadmaps = $roadmapsQuery->paginate(12);
        $roadmap = $this->roadmapId ? $workspace->studyRoadmaps()->withCount($counts)->with(['steps.topics', 'connections'])->findOrFail($this->roadmapId) : null;
        $this->canvasState = $roadmap ? [
            'roadmapId' => $roadmap->id,
            'nodes' => $roadmap->steps->map(fn ($step) => [
                'id' => $step->id, 'title' => $step->title, 'x' => $step->canvas_x, 'y' => $step->canvas_y,
                'complete' => $step->topics->isNotEmpty() && $step->topics->every(fn ($topic) => $topic->status === StudyStatus::Completed),
                'topicIds' => $step->topics->pluck('id')->all(),
            ])->values()->all(),
            'edges' => $roadmap->connections->map(fn ($connection) => [
                'id' => $connection->id, 'source' => $connection->source_step_id, 'target' => $connection->target_step_id,
            ])->values()->all(),
        ] : [];
        $topicOptions = $workspace->studyTopics()->with('roadmap')->when($this->roadmapId, fn ($query) => $query->where('study_roadmap_id', $this->roadmapId))->orderBy('title')->get();
        $materialsQuery = $workspace->studyMaterials()->with(['roadmap', 'topic'])->latest('id');
        $sessionsQuery = $workspace->studySessions()->with(['roadmap', 'topic'])->orderByDesc('studied_on')->orderByDesc('id');
        if ($this->roadmapId) {
            $materialsQuery->where('study_roadmap_id', $this->roadmapId);
            $sessionsQuery->where('study_roadmap_id', $this->roadmapId);
        }
        if ($this->search !== '') {
            $materialsQuery->where('title', 'like', '%'.$this->search.'%');
            $sessionsQuery->where(fn ($query) => $query->where('topic_title', 'like', '%'.$this->search.'%')->orWhere('notes', 'like', '%'.$this->search.'%'));
        }
        if ($this->materialType === 'link') {
            $materialsQuery->whereNotNull('url');
        } elseif ($this->materialType === 'file') {
            $materialsQuery->whereNotNull('path');
        } elseif ($this->materialType === 'pdf') {
            $materialsQuery->where('mime', 'application/pdf');
        } elseif ($this->materialType === 'image') {
            $materialsQuery->where('mime', 'like', 'image/%');
        }
        if ($this->materialStatus !== '') {
            $materialsQuery->where('status', $this->materialStatus);
        }
        $materials = $materialsQuery->paginate(12, pageName: 'materialsPage');
        $sessions = $sessionsQuery->paginate(12, pageName: 'sessionsPage');
        $week = CarbonImmutable::now(config('app.timezone'))->startOfWeek();
        $weekSessions = $workspace->studySessions()->whereDate('studied_on', '>=', $week)->whereDate('studied_on', '<', $week->addWeek());
        $stats = ['roadmaps' => $workspace->studyRoadmaps()->count(),
            'active' => $workspace->studyRoadmaps()->where('status', 'in_progress')->count(),
            'sessions' => (clone $weekSessions)->count(), 'minutes' => (int) (clone $weekSessions)->sum('minutes'),
            'materials' => $workspace->studyMaterials()->count()];
        $lastSession = $workspace->studySessions()->whereHas('topic', fn ($query) => $query->whereIn('status', ['not_started', 'in_progress'])
            ->whereHas('roadmap', fn ($roadmapQuery) => $roadmapQuery->whereIn('status', ['not_started', 'in_progress'])))
            ->with(['topic', 'roadmap'])->orderByDesc('studied_on')->orderByDesc('id')->first();
        $nextTopic = $lastSession?->topic ?? $workspace->studyTopics()->whereIn('status', ['not_started', 'in_progress'])
            ->whereHas('roadmap', fn ($query) => $query->whereIn('status', ['not_started', 'in_progress']))
            ->select('study_topics.*')->join('study_steps', 'study_steps.id', '=', 'study_topics.study_step_id')
            ->with('roadmap')->orderByRaw("case when study_topics.status = 'in_progress' then 0 else 1 end")
            ->orderBy('study_topics.study_roadmap_id')->orderBy('study_steps.position')->orderBy('study_steps.id')
            ->orderBy('study_topics.position')->orderBy('study_topics.id')->first();
        $canManage = $workspace->allows(auth()->user(), Permission::StudiesManage);
        $formTopicOptions = $workspace->studyTopics()->with('roadmap')->when($this->form['study_roadmap_id'] ?? null,
            fn ($query, $id) => $query->where('study_roadmap_id', $id))->orderBy('title')->get();

        return view('livewire.study-area', compact('workspace', 'roadmaps', 'roadmap', 'roadmapOptions', 'topicOptions', 'formTopicOptions', 'materials', 'sessions', 'stats', 'nextTopic', 'canManage'));
    }
}
