<?php

namespace Tests\Feature;

use App\Enums\StudySection;
use App\Enums\StudyStatus;
use App\Livewire\StudyArea;
use App\Livewire\WorkspaceSettings;
use App\Livewire\WorkspaceSwitcher;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreateWorkspace;
use App\Services\ManageStudies;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class StudyModuleTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): Workspace
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-24 12:00:00', 'America/Fortaleza'));
        $user = User::factory()->create();
        $this->actingAs($user);

        return app(CreateWorkspace::class)->handle($user, 'Estudos pessoais');
    }

    private function roadmap(Workspace $workspace, string $title = 'Aprender inglês')
    {
        return app(ManageStudies::class)->saveRoadmap($workspace, [
            'title' => $title, 'description' => '<p>Um pouco por dia</p>', 'color' => '#9589e8',
            'status' => 'not_started', 'due_date' => '',
        ]);
    }

    private function topic(Workspace $workspace, $roadmap = null, string $title = 'Apresentação pessoal')
    {
        $roadmap ??= $this->roadmap($workspace);
        $step = app(ManageStudies::class)->saveStep($workspace, $roadmap->id, 'Primeiros passos');

        return app(ManageStudies::class)->saveTopic($workspace, [
            'title' => $title, 'study_step_id' => $step->id, 'status' => 'not_started', 'checkpoint' => '', 'description' => '',
        ]);
    }

    private function board(Workspace $workspace, string $section = 'dashboard')
    {
        return Livewire::test(StudyArea::class, ['workspaceId' => $workspace->id, 'section' => $section]);
    }

    public function test_pages_navigation_and_workspace_switching_preserve_the_module(): void
    {
        $workspace = $this->workspace();
        foreach (StudySection::cases() as $section) {
            $this->get(route($section->routeName(), $workspace))->assertOk()->assertSee('Estudos');
            Livewire::test(WorkspaceSwitcher::class, ['activeId' => $workspace->id, 'module' => $section->routeName()])
                ->call('selectWorkspace', $workspace->id)->assertRedirect(route($section->routeName(), $workspace));
        }
        $this->get(route('home'))->assertSeeInOrder(['aria-label="Metas"', 'aria-label="Estudos"', 'aria-label="Notas"'], false);
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro ambiente');
        $this->post(route('workspaces.select'), ['workspace_id' => $other->id, 'module' => 'studies.library'])
            ->assertRedirect(route('studies.library', $other));
        $this->post(route('workspaces.select'), ['workspace_id' => $other->id, 'module' => 'studies'])
            ->assertRedirect(route('studies.index', $other));
    }

    public function test_roadmaps_steps_and_topics_support_save_and_create_another_and_editing(): void
    {
        $workspace = $this->workspace();
        $board = $this->board($workspace, 'roadmaps')->call('openForm', 'roadmap');
        $board->call('saveAndCreateAnother')->assertHasErrors('form.title')->assertSet('formKind', 'roadmap');
        $board->set('form.title', 'Aprender francês')->set('form.description', '<p onclick="alert(1)">Bonjour<script>alert(1)</script></p>')
            ->call('saveAndCreateAnother')->assertHasNoErrors()->assertSet('formKind', 'roadmap')->assertSet('form.title', '')
            ->assertSet('form.description', '')->assertSee('Você já pode cadastrar o próximo.');
        $roadmap = $workspace->studyRoadmaps()->sole();
        $this->assertSame('<p>Bonjour</p>', $roadmap->description);
        $this->assertNull($roadmap->due_date);
        $board->call('openForm', 'step', null, $roadmap->id)->set('form.title', 'Básico')->call('saveAndCreateAnother')
            ->assertHasNoErrors()->assertSet('formKind', 'step')->assertSet('form.study_roadmap_id', $roadmap->id);
        $step = $workspace->studySteps()->sole();
        $board->call('openForm', 'topic', null, $step->id)->set('form.title', 'Pronúncia')->call('saveAndCreateAnother')
            ->assertHasNoErrors()->assertSet('formKind', 'topic')->assertSet('form.study_step_id', $step->id);
        $topic = $workspace->studyTopics()->sole();
        $board->call('openForm', 'topic', $topic->id)->set('form.title', 'Pronúncia francesa')->call('save')->assertHasNoErrors()->assertSet('formKind', null);
        $this->assertSame('Pronúncia francesa', $topic->fresh()->title);
        $board->call('openForm', 'roadmap', $roadmap->id)->assertDontSee('wire:click="saveAndCreateAnother"', false)
            ->call('saveAndCreateAnother')->assertForbidden();
        $this->board($workspace, 'roadmaps')->set('roadmapId', $roadmap->id)->assertSee('Pronúncia francesa')
            ->assertSet('display', 'canvas')->assertSee('Seu mapa de aprendizado')
            ->set('display', 'list')->assertSee('study-steps', false)
            ->set('display', 'canvas')->assertSee('study-canvas-viewport', false);
    }

    public function test_ordering_and_cross_step_moves_preserve_the_drop_position_and_reject_other_roadmaps(): void
    {
        $workspace = $this->workspace();
        $roadmap = $this->roadmap($workspace);
        $first = $this->topic($workspace, $roadmap, 'Primeiro');
        $step = $workspace->studySteps()->findOrFail($first->study_step_id);
        $second = app(ManageStudies::class)->saveTopic($workspace, ['title' => 'Segundo', 'study_step_id' => $step->id, 'status' => 'not_started']);
        $destination = app(ManageStudies::class)->saveStep($workspace, $roadmap->id, 'Prática');
        $third = app(ManageStudies::class)->saveTopic($workspace, ['title' => 'Terceiro', 'study_step_id' => $destination->id, 'status' => 'completed']);
        $board = $this->board($workspace, 'roadmaps')->set('roadmapId', $roadmap->id);
        $board->call('reorderStep', $destination->id, 0)->assertHasNoErrors();
        $this->assertSame([$destination->id, $step->id], $roadmap->steps()->pluck('id')->all());
        $board->call('reorderTopic', $second->id, 0, $destination->id)->assertHasNoErrors();
        $this->assertSame([$second->id, $third->id], $destination->topics()->pluck('id')->all());
        $this->assertSame(0, $first->fresh()->position);
        $board->call('reorderTopic', $second->id, 1, $destination->id)->assertHasNoErrors();
        $this->assertSame([$third->id, $second->id], $destination->topics()->pluck('id')->all());
        $board->call('reorderTopic', $second->id, 5, $destination->id)->assertHasErrors('position');
        $foreignRoadmapTopic = $this->topic($workspace, title: 'Outra trilha');
        try {
            $board->call('reorderTopic', $first->id, 0, $foreignRoadmapTopic->study_step_id);
            $this->fail('Não deve mover para outro roadmap.');
        } catch (ModelNotFoundException) {
            $this->assertSame($step->id, $first->fresh()->study_step_id);
        }
    }

    public function test_sessions_keep_completion_separate_and_update_dashboard_and_resume_checkpoint(): void
    {
        $workspace = $this->workspace();
        $topic = $this->topic($workspace);
        $board = $this->board($workspace)->call('openForm', 'session', null, $topic->id)
            ->set('form.minutes', 25)->set('form.notes', 'Aprendi a me apresentar')->set('form.checkpoint', 'Exercício 4')
            ->call('saveAndCreateAnother')->assertHasNoErrors()->assertSet('formKind', 'session')->assertSet('form.minutes', '')
            ->assertViewHas('stats', fn ($stats) => $stats['sessions'] === 1 && $stats['minutes'] === 25)
            ->assertViewHas('nextTopic', fn ($item) => $item->id === $topic->id);
        $this->assertSame(StudyStatus::InProgress, $topic->fresh()->status);
        $this->assertSame('Exercício 4', $topic->fresh()->checkpoint);
        $board->call('closeForm')->call('setTopicStatus', $topic->id, 'completed')->assertHasNoErrors()
            ->assertViewHas('nextTopic', null);
        $this->board($workspace, 'roadmaps')->set('roadmapId', $topic->study_roadmap_id)
            ->assertViewHas('roadmap', fn ($item) => $item->progress() === 100);
        $this->board($workspace, 'history')->assertSee('Aprendi a me apresentar')->assertSee('25 min');
        $this->board($workspace)->call('openForm', 'session', null, $topic->id)->set('form.studied_on', '2026-11-25')->call('save')->assertHasErrors('form.studied_on');
        $this->assertSame(1, $workspace->studySessions()->count());
    }

    public function test_optional_session_time_and_topic_deletion_preserve_recorded_history(): void
    {
        $workspace = $this->workspace();
        $topic = $this->topic($workspace);
        $this->board($workspace)->call('openForm', 'session', null, $topic->id)->call('save')->assertHasNoErrors();
        $session = $workspace->studySessions()->sole();
        $this->assertNull($session->minutes);
        $this->board($workspace)->call('deleteRecord', 'topic', $topic->id)->assertHasNoErrors();
        $this->assertNull($session->fresh()->study_topic_id);
        $this->assertSame($topic->title, $session->fresh()->topic_title);
        $this->board($workspace, 'history')->assertSee($topic->title)->assertSee('Estudo registrado');
    }

    public function test_material_links_uploads_downloads_previews_and_deletion_are_private(): void
    {
        Storage::fake('local');
        $workspace = $this->workspace();
        $topic = $this->topic($workspace);
        $board = $this->board($workspace, 'library')->call('openForm', 'material', null, $topic->id)
            ->set('form.title', 'Guia de conversação')->set('form.url', 'https://example.com/guide')
            ->call('saveAndCreateAnother')->assertHasNoErrors()->assertSet('formKind', 'material')->assertSet('form.title', '');
        $material = $workspace->studyMaterials()->sole();
        $this->assertSame($topic->id, $material->study_topic_id);
        $board->set('form.title', 'Exercícios')->set('upload', UploadedFile::fake()->create('exercicios.pdf', 40, 'application/pdf'))
            ->call('save')->assertHasNoErrors();
        $file = $workspace->studyMaterials()->whereNotNull('path')->sole();
        Storage::disk('local')->assertExists($file->path);
        $this->get(route('studies.file', [$workspace, $file->id]))->assertOk()->assertDownload('exercicios.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('studies.file', [$workspace, $file->id, 'preview' => 1]))->assertOk()->assertHeader('Content-Disposition', 'inline; filename=exercicios.pdf');
        $board->call('openForm', 'material', $file->id)->set('form.status', 'finished')->call('save')->assertHasNoErrors();
        Storage::disk('local')->assertExists($file->path);
        $board->call('deleteRecord', 'material', $file->id)->assertHasNoErrors();
        Storage::disk('local')->assertMissing($file->path);
        $this->get(route('studies.file', [$workspace, $file->id]))->assertNotFound();
    }

    public function test_material_validation_rejects_unsafe_urls_files_and_mismatched_topic_links(): void
    {
        Storage::fake('local');
        $workspace = $this->workspace();
        $topic = $this->topic($workspace);
        $otherTopic = $this->topic($workspace, title: 'Outra trilha');
        $board = $this->board($workspace, 'library')->call('openForm', 'material')->set('form.title', 'Teste');
        $board->set('form.url', 'javascript:alert(1)')->call('save')->assertHasErrors('form.url');
        $board->set('form.url', 'https://example.com')->set('form.study_roadmap_id', $topic->study_roadmap_id)
            ->set('form.study_topic_id', $otherTopic->id)->call('save')->assertHasErrors('form.study_topic_id');
        try {
            app(ManageStudies::class)->saveMaterial($workspace, ['title' => 'Arquivo', 'status' => 'to_read'], UploadedFile::fake()->create('evil.html', 5, 'text/html'));
            $this->fail('Não deve aceitar HTML.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('upload', $exception->errors());
        }
        try {
            app(ManageStudies::class)->saveMaterial($workspace, ['title' => 'Grande', 'status' => 'to_read'], UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf'));
            $this->fail('Não deve aceitar mais de 10 MB.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('upload', $exception->errors());
        }
        $this->assertSame(0, $workspace->studyMaterials()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_library_filters_and_pagination_reset_when_the_search_or_type_changes(): void
    {
        Storage::fake('local');
        $workspace = $this->workspace();
        foreach (range(1, 13) as $number) {
            app(ManageStudies::class)->saveMaterial($workspace, ['title' => 'Referência '.$number, 'status' => 'to_read', 'url' => 'https://example.com/'.$number]);
        }
        $board = $this->board($workspace, 'library')->assertViewHas('materials', fn ($items) => $items->total() === 13)
            ->call('nextPage', 'materialsPage')->assertViewHas('materials', fn ($items) => $items->currentPage() === 2)
            ->set('search', 'Referência 13')->assertViewHas('materials', fn ($items) => $items->currentPage() === 1 && $items->total() === 1);
        $board->set('search', '')->set('materialType', 'pdf')->assertViewHas('materials', fn ($items) => $items->total() === 0)
            ->set('materialType', 'link')->assertViewHas('materials', fn ($items) => $items->total() === 13)
            ->set('materialStatus', 'finished')->assertViewHas('materials', fn ($items) => $items->total() === 0);
        $board->call('openForm', 'material')->set('form.title', 'Finalizado')->set('form.url', 'https://example.com/done')
            ->set('form.status', 'finished')->call('save')->assertHasNoErrors()->assertViewHas('materials', fn ($items) => $items->total() === 1);
    }

    public function test_next_topic_respects_step_order_and_paused_roadmaps_are_excluded(): void
    {
        $workspace = $this->workspace();
        $roadmap = $this->roadmap($workspace);
        $first = $this->topic($workspace, $roadmap, 'Tópico A');
        $second = $this->topic($workspace, $roadmap, 'Tópico B');
        $board = $this->board($workspace)->assertViewHas('nextTopic', fn ($topic) => $topic->id === $first->id);
        $board->call('reorderStep', $second->study_step_id, 0)->assertHasNoErrors()
            ->assertViewHas('nextTopic', fn ($topic) => $topic->id === $second->id);
        app(ManageStudies::class)->saveRoadmap($workspace, ['title' => $roadmap->title, 'color' => $roadmap->color, 'status' => 'paused'], $roadmap->id);
        $board->call('$refresh')->assertViewHas('nextTopic', null);
    }

    public function test_canvas_positions_and_connections_persist_and_removing_a_step_cleans_its_connections(): void
    {
        $workspace = $this->workspace();
        $roadmap = $this->roadmap($workspace);
        $first = app(ManageStudies::class)->saveStep($workspace, $roadmap->id, 'Básico');
        $second = app(ManageStudies::class)->saveStep($workspace, $roadmap->id, 'Prática');
        $third = app(ManageStudies::class)->saveStep($workspace, $roadmap->id, 'Projeto');
        $this->assertSame(2, $workspace->studyConnections()->count());
        $board = $this->board($workspace, 'roadmaps')->set('roadmapId', $roadmap->id)->assertSet('display', 'canvas');
        $board->call('saveCanvasPositions', $roadmap->id, [
            ['id' => $first->id, 'x' => -180.5, 'y' => 96], ['id' => $second->id, 'x' => 400, 'y' => 700],
        ])->assertHasNoErrors();
        $this->assertSame(-180.5, $first->fresh()->canvas_x);
        $this->assertSame(700.0, $second->fresh()->canvas_y);
        $this->board($workspace, 'roadmaps')->set('roadmapId', $roadmap->id)
            ->assertSet('canvasState.nodes.0.x', -180.5)->assertSee('Quadro visual')->assertSee('Conectar');
        $board->call('connectSteps', $roadmap->id, $first->id, $third->id)->assertHasNoErrors()
            ->call('connectSteps', $roadmap->id, $first->id, $third->id)->assertHasNoErrors();
        $this->assertSame(3, $workspace->studyConnections()->count());
        $connection = $workspace->studyConnections()->where('source_step_id', $first->id)->where('target_step_id', $third->id)->sole();
        $board->call('disconnectSteps', $roadmap->id, $connection->id)->assertHasNoErrors();
        $this->assertSame(2, $workspace->studyConnections()->count());
        $board->call('deleteRecord', 'step', $second->id)->assertHasNoErrors();
        $this->assertSame(0, $workspace->studyConnections()->count());
    }

    public function test_canvas_rejects_invalid_positions_self_links_and_cross_roadmap_changes_atomically(): void
    {
        $workspace = $this->workspace();
        $roadmap = $this->roadmap($workspace);
        $first = app(ManageStudies::class)->saveStep($workspace, $roadmap->id, 'Básico');
        $otherTopic = $this->topic($workspace, title: 'Outra trilha');
        $board = $this->board($workspace, 'roadmaps')->set('roadmapId', $roadmap->id);
        $board->call('saveCanvasPositions', $roadmap->id, [['id' => $first->id, 'x' => 50001, 'y' => 0]])->assertHasErrors('positions.0.x');
        $board->call('connectSteps', $roadmap->id, $first->id, $first->id)->assertHasErrors('connection');
        foreach ([
            fn () => $board->call('connectSteps', $roadmap->id, $first->id, $otherTopic->study_step_id),
            fn () => $board->call('saveCanvasPositions', $roadmap->id, [
                ['id' => $first->id, 'x' => 40, 'y' => 50], ['id' => $otherTopic->study_step_id, 'x' => 80, 'y' => 90],
            ]),
        ] as $action) {
            try {
                $action();
                $this->fail('Não deve acessar etapas de outra trilha.');
            } catch (ModelNotFoundException) {
                $this->assertNull($first->fresh()->canvas_x);
            }
        }
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro ambiente');
        $foreign = $this->topic($other);
        try {
            $this->board($workspace)->call('connectSteps', $foreign->study_roadmap_id, $first->id, $foreign->study_step_id);
            $this->fail('Não deve conectar outro ambiente.');
        } catch (ModelNotFoundException) {
            $this->assertSame(0, $workspace->studyConnections()->count());
        }
    }

    public function test_canvas_readers_cannot_move_or_connect_even_when_permissions_change_mid_session(): void
    {
        $workspace = $this->workspace();
        $topic = $this->topic($workspace);
        $otherStep = app(ManageStudies::class)->saveStep($workspace, $topic->study_roadmap_id, 'Prática');
        $edge = $workspace->studyConnections()->sole();
        $member = User::factory()->create();
        $workspace->members()->attach($member, ['permissions' => ['studies.view', 'studies.manage']]);
        $this->actingAs($member);
        $board = $this->board($workspace, 'roadmaps')->set('roadmapId', $topic->study_roadmap_id);
        $workspace->members()->updateExistingPivot($member->id, ['permissions' => ['studies.view']]);
        $board->call('saveCanvasPositions', $topic->study_roadmap_id, [['id' => $topic->study_step_id, 'x' => 10, 'y' => 20]])->assertForbidden();
        $this->board($workspace)->call('connectSteps', $topic->study_roadmap_id, $otherStep->id, $topic->study_step_id)->assertForbidden();
        $this->board($workspace)->call('disconnectSteps', $topic->study_roadmap_id, $edge->id)->assertForbidden();
        $this->board($workspace, 'roadmaps')->set('roadmapId', $topic->study_roadmap_id)->assertSee('Seu mapa de aprendizado')
            ->assertDontSee('>Conectar</span>', false)->assertDontSee('Organizar as posições seguindo as conexões');
    }

    public function test_canvas_migration_connects_existing_steps_in_their_saved_order(): void
    {
        $workspace = $this->workspace();
        $roadmap = $this->roadmap($workspace);
        $first = app(ManageStudies::class)->saveStep($workspace, $roadmap->id, 'Primeiro');
        $second = app(ManageStudies::class)->saveStep($workspace, $roadmap->id, 'Segundo');
        app(ManageStudies::class)->reorderStep($workspace, $second->id, 0);
        $migration = require database_path('migrations/2026_11_24_000002_add_study_canvas.php');
        $migration->down();
        $migration->up();
        $connection = $workspace->studyConnections()->sole();
        $this->assertSame($second->id, $connection->source_step_id);
        $this->assertSame($first->id, $connection->target_step_id);
        $this->assertSame(2, $workspace->studySteps()->count());
    }

    public function test_all_records_and_foreign_keys_are_scoped_to_the_workspace(): void
    {
        Storage::fake('local');
        $workspace = $this->workspace();
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $topic = $this->topic($other, title: 'Tópico privado');
        $file = app(ManageStudies::class)->saveMaterial($other, ['title' => 'Privado', 'status' => 'to_read'], UploadedFile::fake()->create('privado.pdf', 5, 'application/pdf'));
        $this->board($workspace)->assertDontSee('Tópico privado')->assertViewHas('stats', fn ($stats) => $stats['roadmaps'] === 0 && $stats['materials'] === 0);
        $this->get(route('studies.file', [$workspace, $file->id]))->assertNotFound();
        foreach ([
            fn () => $this->board($workspace)->set('roadmapId', $topic->study_roadmap_id),
            fn () => $this->board($workspace)->call('openForm', 'topic', $topic->id),
            fn () => app(ManageStudies::class)->recordSession($workspace, ['study_topic_id' => $topic->id, 'studied_on' => '2026-11-24']),
            fn () => app(ManageStudies::class)->saveTopic($workspace, ['title' => 'Inválido', 'study_step_id' => $topic->study_step_id, 'status' => 'not_started']),
            fn () => app(ManageStudies::class)->delete($workspace, 'material', $file->id),
        ] as $action) {
            try {
                $action();
                $this->fail('Não deve acessar outro ambiente.');
            } catch (ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_view_and_manage_permissions_are_checked_on_every_action_and_download(): void
    {
        Storage::fake('local');
        $workspace = $this->workspace();
        $topic = $this->topic($workspace);
        $file = app(ManageStudies::class)->saveMaterial($workspace, ['title' => 'Guia', 'status' => 'to_read'], UploadedFile::fake()->create('guia.pdf', 5, 'application/pdf'));
        $member = User::factory()->create();
        $workspace->members()->attach($member, ['permissions' => ['studies.view', 'studies.manage']]);
        $this->actingAs($member);
        $stale = $this->board($workspace)->call('openForm', 'roadmap')->set('form.title', 'Inválido');
        $workspace->members()->updateExistingPivot($member->id, ['permissions' => ['studies.view']]);
        $stale->call('save')->assertForbidden();
        $this->board($workspace)->call('openForm', 'roadmap')->assertForbidden();
        $this->board($workspace)->call('setTopicStatus', $topic->id, 'completed')->assertForbidden();
        $this->board($workspace)->call('deleteRecord', 'topic', $topic->id)->assertForbidden();
        $this->get(route('studies.file', [$workspace, $file->id]))->assertOk();
        $workspace->members()->updateExistingPivot($member->id, ['permissions' => ['studies.manage']]);
        foreach (StudySection::cases() as $section) {
            $this->get(route($section->routeName(), $workspace))->assertForbidden();
        }
        $this->get(route('studies.file', [$workspace, $file->id]))->assertForbidden();
        $this->get(route('home'))->assertDontSee('Seções de estudos');
    }

    public function test_deleting_a_roadmap_preserves_library_materials_and_workspace_deletion_removes_files(): void
    {
        Storage::fake('local');
        $workspace = $this->workspace();
        $topic = $this->topic($workspace);
        $material = app(ManageStudies::class)->saveMaterial($workspace, ['title' => 'Guia', 'status' => 'to_read', 'study_topic_id' => $topic->id], UploadedFile::fake()->create('guia.pdf', 5, 'application/pdf'));
        app(ManageStudies::class)->recordSession($workspace, ['study_topic_id' => $topic->id, 'studied_on' => '2026-11-24']);
        $this->board($workspace)->call('deleteRecord', 'roadmap', $topic->study_roadmap_id)->assertHasNoErrors();
        $this->assertNull($material->fresh()->study_roadmap_id);
        $this->assertNull($material->fresh()->study_topic_id);
        $this->assertSame(0, $workspace->studySessions()->count());
        Storage::disk('local')->assertExists($material->path);
        Livewire::test(WorkspaceSettings::class, ['workspaceId' => $workspace->id])->set('confirmation', $workspace->name)
            ->call('deleteWorkspace')->assertRedirect(route('home'));
        Storage::disk('local')->assertMissing($material->path);
        $this->assertDatabaseCount('study_materials', 0);
    }
}
