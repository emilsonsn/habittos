<?php

namespace Tests\Feature;

use App\Livewire\GoalsBoard;
use App\Models\User;
use App\Services\CreateWorkspace;
use App\Services\GoalDescription;
use App\Services\ImportNotionGoals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GoalDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_description_preserves_formatting_after_save_reopen_and_cancellation(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Pessoal');
        $this->actingAs($user);
        $html = '<p>Plano <strong>importante</strong> e <em>possível</em> com <u>foco</u>.</p><ul><li>Primeiro passo</li></ul><ol><li>Revisar</li></ol><blockquote>Persistir</blockquote>';
        $component = Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id])
            ->set('yearFilter', 2026)->call('openCreate')->assertSet('form.description', '')
            ->set('form.title', 'Plano com detalhes')->set('form.description', $html)
            ->call('saveGoal')->assertHasNoErrors();
        $goal = $workspace->goals()->firstOrFail();
        $this->assertSame($html, $goal->description);
        $component->call('openEdit', $goal->id)->assertSet('form.description', $html)
            ->set('form.description', '<p>Descartar</p>')->call('closeForm')
            ->call('openEdit', $goal->id)->assertSet('form.description', $html)
            ->set('form.description', '<p><br></p>')->call('saveGoal')->assertHasNoErrors();
        $this->assertNull($goal->fresh()->description);
    }

    public function test_server_strips_active_html_and_attributes_for_http_writes_and_readers_see_formatting(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Pessoal');
        $this->actingAs($user);
        $data = ['title' => 'Segura', 'period' => 'annual', 'year' => 2026, 'priority' => 'medium',
            'status' => 'not_started', 'progress_percent' => 0,
            'description' => '<p onclick="alert(1)" style="color:red">Olá <b>mundo</b><img src=x onerror=alert(1)><a href="javascript:alert(1)">link</a></p><script>alert(1)</script><svg onload="alert(1)"></svg><iframe src="https://example.com"></iframe>'];
        $this->post(route('goals.store', $workspace), $data)->assertSessionHasNoErrors();
        $goal = $workspace->goals()->firstOrFail();
        $this->assertSame('<p>Olá <b>mundo</b>link</p>', $goal->description);
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['goals.view']]);
        $this->actingAs($reader);
        Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id])->set('yearFilter', 2026)
            ->call('openDetails', $goal->id)->assertSee('<p>Olá <b>mundo</b>link</p>', false)
            ->assertDontSee('contenteditable="true"', false)
            ->set('form.description', '<p>Proibido</p>')->call('saveGoal')->assertForbidden();
        $this->assertSame('<p>Olá <b>mundo</b>link</p>', $goal->fresh()->description);
    }

    public function test_description_is_optional_limited_and_not_erased_by_notion_reimport(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Pessoal');
        $this->actingAs($user);
        $path = base_path('tests/Fixtures/notion-goals.csv');
        app(ImportNotionGoals::class)->import($workspace, $path, 2026);
        $goal = $workspace->goals()->firstOrFail();
        $this->assertNull($goal->description);
        Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id])->set('yearFilter', 2026)
            ->call('openEdit', $goal->id)->set('form.description', str_repeat('a', 20001))
            ->call('saveGoal')->assertHasErrors('form.description')
            ->set('form.description', '<p><strong>Meu planejamento</strong></p>')->call('saveGoal')->assertHasNoErrors();
        app(ImportNotionGoals::class)->import($workspace, $path, 2026);
        $this->assertSame('<p><strong>Meu planejamento</strong></p>', $goal->fresh()->description);
    }

    public function test_sanitizer_handles_encoded_text_and_malformed_markup_without_retaining_attributes(): void
    {
        $sanitizer = app(GoalDescription::class);
        $this->assertSame('<p>&lt;script&gt; &amp; ação</p>', $sanitizer->sanitize('<p>&lt;script&gt; &amp; ação</p>'));
        $this->assertSame('<p><b>Texto</b></p>', $sanitizer->sanitize('<p><b title="teste">Texto'));
        $this->assertNull($sanitizer->sanitize('<div>&nbsp;<br></div>'));
    }
}
