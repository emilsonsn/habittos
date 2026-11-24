<?php

namespace App\Services;

use App\Enums\StudyMaterialStatus;
use App\Enums\StudyStatus;
use App\Models\StudyMaterial;
use App\Models\StudyRoadmap;
use App\Models\StudySession;
use App\Models\StudyStep;
use App\Models\StudyTopic;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageStudies
{
    public function authorize(Workspace $workspace, bool $manage = true): void
    {
        abort_unless(auth()->check(), 401);
        abort_unless($workspace->members()->whereKey(auth()->id())->exists(), 404);
        Gate::authorize('studies.view', $workspace);
        if ($manage) {
            Gate::authorize('studies.manage', $workspace);
        }
    }

    private function change(Workspace $workspace, callable $action): mixed
    {
        $this->authorize($workspace);

        return DB::transaction(function () use ($workspace, $action) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();

            return $action();
        });
    }

    public function saveRoadmap(Workspace $workspace, array $input, ?int $id = null): StudyRoadmap
    {
        return $this->change($workspace, function () use ($workspace, $input, $id) {
            $roadmap = $id ? $workspace->studyRoadmaps()->findOrFail($id) : null;
            $input['title'] = trim((string) ($input['title'] ?? ''));
            $data = validator($input, [
                'title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:100000'],
                'color' => ['required', 'regex:/^#[a-fA-F0-9]{6}$/'], 'status' => ['required', Rule::enum(StudyStatus::class)],
                'due_date' => ['nullable', 'date_format:Y-m-d'],
            ])->validate();
            $data['description'] = app(RichText::class)->sanitize($data['description'] ?? null);
            $data['due_date'] = ($data['due_date'] ?? '') ?: null;
            if ($roadmap) {
                $roadmap->update($data);

                return $roadmap->fresh();
            }

            return $workspace->studyRoadmaps()->create($data);
        });
    }

    public function saveStep(Workspace $workspace, int $roadmapId, string $title, ?int $id = null): StudyStep
    {
        return $this->change($workspace, function () use ($workspace, $roadmapId, $title, $id) {
            $roadmap = $workspace->studyRoadmaps()->findOrFail($roadmapId);
            $step = $id ? $workspace->studySteps()->where('study_roadmap_id', $roadmap->id)->findOrFail($id) : null;
            $data = validator(['title' => trim($title)], ['title' => ['required', 'string', 'max:200']])->validate();
            if ($step) {
                $step->update($data);

                return $step->fresh();
            }

            $previous = $roadmap->steps()->reorder()->orderByDesc('position')->orderByDesc('id')->first();
            $step = $workspace->studySteps()->create($data + [
                'study_roadmap_id' => $roadmap->id, 'position' => ($previous?->position ?? -1) + 1,
            ]);
            if ($previous) {
                $workspace->studyConnections()->create([
                    'study_roadmap_id' => $roadmap->id, 'source_step_id' => $previous->id, 'target_step_id' => $step->id,
                ]);
            }

            return $step;
        });
    }

    public function saveTopic(Workspace $workspace, array $input, ?int $id = null): StudyTopic
    {
        return $this->change($workspace, function () use ($workspace, $input, $id) {
            $topic = $id ? $workspace->studyTopics()->findOrFail($id) : null;
            $input['title'] = trim((string) ($input['title'] ?? ''));
            $data = validator($input, [
                'title' => ['required', 'string', 'max:200'], 'study_step_id' => ['required', 'integer'],
                'description' => ['nullable', 'string', 'max:100000'], 'checkpoint' => ['nullable', 'string', 'max:2000'],
                'status' => ['required', Rule::enum(StudyStatus::class)],
            ])->validate();
            $step = $workspace->studySteps()->findOrFail($data['study_step_id']);
            // Moving between steps uses the dedicated ordering action.
            abort_if($topic && $topic->study_step_id !== $step->id, 422);
            $data['description'] = app(RichText::class)->sanitize($data['description'] ?? null);
            if ($topic) {
                $topic->update($data);

                return $topic->fresh();
            }

            return $workspace->studyTopics()->create($data + [
                'study_roadmap_id' => $step->study_roadmap_id,
                'position' => (int) $step->topics()->max('position') + 1,
            ]);
        });
    }

    public function setTopicStatus(Workspace $workspace, int $id, string $status): void
    {
        $this->change($workspace, function () use ($workspace, $id, $status) {
            validator(['status' => $status], ['status' => ['required', Rule::enum(StudyStatus::class)]])->validate();
            $workspace->studyTopics()->findOrFail($id)->update(['status' => $status]);
        });
    }

    public function saveCanvasPositions(Workspace $workspace, int $roadmapId, array $positions): void
    {
        $this->change($workspace, function () use ($workspace, $roadmapId, $positions) {
            $workspace->studyRoadmaps()->findOrFail($roadmapId);
            validator(['positions' => $positions], [
                'positions' => ['required', 'array', 'min:1', 'max:1000'],
                'positions.*.id' => ['required', 'integer', 'distinct'],
                'positions.*.x' => ['required', 'numeric', 'between:-50000,50000'],
                'positions.*.y' => ['required', 'numeric', 'between:-50000,50000'],
            ])->validate();
            foreach ($positions as $position) {
                $workspace->studySteps()->where('study_roadmap_id', $roadmapId)->findOrFail($position['id'])
                    ->update(['canvas_x' => round($position['x'], 2), 'canvas_y' => round($position['y'], 2)]);
            }
        });
    }

    public function connectSteps(Workspace $workspace, int $roadmapId, int $sourceId, int $targetId): void
    {
        $this->change($workspace, function () use ($workspace, $roadmapId, $sourceId, $targetId) {
            $workspace->studyRoadmaps()->findOrFail($roadmapId);
            $source = $workspace->studySteps()->where('study_roadmap_id', $roadmapId)->findOrFail($sourceId);
            $target = $workspace->studySteps()->where('study_roadmap_id', $roadmapId)->findOrFail($targetId);
            if ($source->id === $target->id) {
                throw ValidationException::withMessages(['connection' => 'Escolha duas etapas diferentes para conectar.']);
            }
            $workspace->studyConnections()->firstOrCreate([
                'study_roadmap_id' => $roadmapId, 'source_step_id' => $source->id, 'target_step_id' => $target->id,
            ]);
        });
    }

    public function disconnectSteps(Workspace $workspace, int $roadmapId, int $connectionId): void
    {
        $this->change($workspace, function () use ($workspace, $roadmapId, $connectionId) {
            $workspace->studyRoadmaps()->findOrFail($roadmapId);
            $workspace->studyConnections()->where('study_roadmap_id', $roadmapId)->findOrFail($connectionId)->delete();
        });
    }

    public function reorderStep(Workspace $workspace, int $id, int $position): void
    {
        $this->change($workspace, function () use ($workspace, $id, $position) {
            $step = $workspace->studySteps()->findOrFail($id);
            $items = $workspace->studySteps()->where('study_roadmap_id', $step->study_roadmap_id)->whereKeyNot($id)
                ->orderBy('position')->orderBy('id')->get()->all();
            $this->order($items, $step, $position);
        });
    }

    public function reorderTopic(Workspace $workspace, int $id, int $position, ?int $stepId = null): void
    {
        $this->change($workspace, function () use ($workspace, $id, $position, $stepId) {
            $topic = $workspace->studyTopics()->findOrFail($id);
            $step = $workspace->studySteps()->where('study_roadmap_id', $topic->study_roadmap_id)->findOrFail($stepId ?? $topic->study_step_id);
            $items = $workspace->studyTopics()->where('study_step_id', $step->id)->whereKeyNot($id)
                ->orderBy('position')->orderBy('id')->get()->all();
            validator(['position' => $position], ['position' => ['required', 'integer', 'between:0,'.count($items)]])->validate();
            $sourceStepId = $topic->study_step_id;
            $topic->update(['study_step_id' => $step->id]);
            $this->order($items, $topic, $position);
            if ($sourceStepId !== $step->id) {
                $workspace->studyTopics()->where('study_step_id', $sourceStepId)->orderBy('position')->orderBy('id')->get()
                    ->each(fn ($item, $index) => $item->update(['position' => $index]));
            }
        });
    }

    private function order(array $items, StudyStep|StudyTopic $item, int $position): void
    {
        validator(['position' => $position], ['position' => ['required', 'integer', 'between:0,'.count($items)]])->validate();
        array_splice($items, $position, 0, [$item]);
        foreach ($items as $index => $record) {
            $record->update(['position' => $index]);
        }
    }

    public function recordSession(Workspace $workspace, array $input): StudySession
    {
        return $this->change($workspace, function () use ($workspace, $input) {
            $data = validator($input, [
                'study_topic_id' => ['required', 'integer'], 'studied_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now(config('app.timezone'))->toDateString()],
                'minutes' => ['nullable', 'integer', 'between:1,1440'], 'notes' => ['nullable', 'string', 'max:10000'],
                'checkpoint' => ['nullable', 'string', 'max:2000'],
            ])->validate();
            $data['minutes'] = ($data['minutes'] ?? '') === '' ? null : $data['minutes'];
            $topic = $workspace->studyTopics()->findOrFail($data['study_topic_id']);
            $checkpoint = $data['checkpoint'] ?? null;
            unset($data['checkpoint']);
            $session = $workspace->studySessions()->create($data + [
                'study_roadmap_id' => $topic->study_roadmap_id, 'topic_title' => $topic->title, 'user_id' => auth()->id(),
            ]);
            $changes = [];
            if ($checkpoint !== null) {
                $changes['checkpoint'] = $checkpoint;
            }
            if ($topic->status === StudyStatus::NotStarted) {
                $changes['status'] = StudyStatus::InProgress;
            }
            $topic->update($changes);
            $workspace->studyRoadmaps()->whereKey($topic->study_roadmap_id)->where('status', StudyStatus::NotStarted->value)
                ->update(['status' => StudyStatus::InProgress->value]);

            return $session;
        });
    }

    public static function uploadRules(): array
    {
        return ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,txt,csv,doc,docx,xls,xlsx,ppt,pptx,zip'];
    }

    public function saveMaterial(Workspace $workspace, array $input, ?UploadedFile $file = null, ?int $id = null): StudyMaterial
    {
        $newPath = null;
        $oldPath = null;
        try {
            $material = $this->change($workspace, function () use ($workspace, $input, $file, $id, &$newPath, &$oldPath) {
                $material = $id ? $workspace->studyMaterials()->findOrFail($id) : null;
                $input['title'] = trim((string) ($input['title'] ?? ''));
                $data = validator($input + ['upload' => $file], [
                    'title' => ['required', 'string', 'max:200'], 'status' => ['required', Rule::enum(StudyMaterialStatus::class)],
                    'url' => ['nullable', 'url:http,https', 'max:4000'],
                    'study_roadmap_id' => ['nullable', 'integer'], 'study_topic_id' => ['nullable', 'integer'],
                    'upload' => self::uploadRules(),
                ])->validate();
                unset($data['upload']);
                $data['study_roadmap_id'] = empty($data['study_roadmap_id']) ? null : $workspace->studyRoadmaps()->findOrFail($data['study_roadmap_id'])->id;
                $data['study_topic_id'] = empty($data['study_topic_id']) ? null : $workspace->studyTopics()->findOrFail($data['study_topic_id'])->id;
                if ($data['study_topic_id']) {
                    $topic = $workspace->studyTopics()->findOrFail($data['study_topic_id']);
                    if ($data['study_roadmap_id'] !== null && $data['study_roadmap_id'] !== $topic->study_roadmap_id) {
                        throw ValidationException::withMessages(['study_topic_id' => 'O tópico deve pertencer ao roadmap selecionado.']);
                    }
                    $data['study_roadmap_id'] = $topic->study_roadmap_id;
                }
                $url = $data['url'] ?? null;
                if (($file && $url) || (! $file && ! $url && ! $material?->path)) {
                    throw ValidationException::withMessages(['url' => 'Informe um link ou anexe um arquivo, uma opção por material.']);
                }
                if ($file) {
                    $newPath = $file->store('studies/'.$workspace->id, 'local');
                    throw_unless($newPath, \RuntimeException::class, 'Não foi possível armazenar o arquivo.');
                    $data += ['path' => $newPath, 'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 240), 'mime' => $file->getMimeType(), 'size' => $file->getSize()];
                    $data['url'] = null;
                } elseif ($url) {
                    $data += ['path' => null, 'original_name' => null, 'mime' => null, 'size' => null];
                }
                $oldPath = $material?->path;
                if ($material) {
                    $material->update($data);

                    return $material->fresh();
                }

                return $workspace->studyMaterials()->create($data);
            });
        } catch (\Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }
        if ($oldPath && $oldPath !== $material->path) {
            Storage::disk('local')->delete($oldPath);
        }

        return $material;
    }

    public function delete(Workspace $workspace, string $kind, int $id): void
    {
        $path = $this->change($workspace, function () use ($workspace, $kind, $id) {
            $query = match ($kind) {
                'roadmap' => $workspace->studyRoadmaps(), 'step' => $workspace->studySteps(),
                'topic' => $workspace->studyTopics(), 'session' => $workspace->studySessions(),
                'material' => $workspace->studyMaterials(), default => abort(422),
            };
            $record = $query->findOrFail($id);
            $path = $record instanceof StudyMaterial ? $record->path : null;
            $record->delete();

            return $path;
        });
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}
