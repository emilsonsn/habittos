<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CreateWorkspace;
use App\Services\ImportNotionGoals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NotionGoalsImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_import_preserves_all_rows_fields_independent_status_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Pessoal');
        $this->actingAs($user);
        $path = base_path('tests/Fixtures/notion-goals.csv');
        $result = app(ImportNotionGoals::class)->import($workspace, $path, 2026);
        $this->assertSame(['created' => 3, 'updated' => 0, 'rows' => 3], $result);
        $goal = $workspace->goals()->where('title', 'Aprender inglês')->firstOrFail();
        $this->assertSame('75.00', $goal->progress_percent);
        $this->assertDatabaseHas('goals', ['title' => 'Ler um livro', 'status' => 'completed', 'progress_percent' => 40]);
        $this->assertDatabaseHas('goals', ['title' => 'Correr 10 km', 'year' => 2026, 'due_date' => '2027-03-31 00:00:00']);
        $second = app(ImportNotionGoals::class)->import($workspace, $path, 2026);
        $this->assertSame(['created' => 0, 'updated' => 3, 'rows' => 3], $second);
        $this->assertDatabaseCount('goals', 3);
        $this->assertDatabaseCount('entries', 0);
        $this->artisan('goals:import-notion', ['file' => $path, '--workspace' => $workspace->id, '--year' => 2026])->assertSuccessful();
        $this->assertDatabaseCount('goals', 3);
    }

    public function test_invalid_row_rolls_back_whole_import(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Pessoal');
        $this->actingAs($user);
        $file = tempnam(sys_get_temp_dir(), 'goals-invalid-');
        file_put_contents($file, str_replace(',Baixa,', ',Inválida,', file_get_contents(base_path('tests/Fixtures/notion-goals.csv'))));
        try {
            app(ImportNotionGoals::class)->import($workspace, $file, 2026);
            $this->fail('A importação deve rejeitar prioridade desconhecida.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('goals', 0);
        } finally {
            unlink($file);
        }
    }

    public function test_import_accepts_not_started_as_a_distinct_status(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Pessoal');
        $this->actingAs($user);
        $file = tempnam(sys_get_temp_dir(), 'goals-not-started-');
        file_put_contents($file, str_replace('Não realizado', 'Não iniciado', file_get_contents(base_path('tests/Fixtures/notion-goals.csv'))));
        try {
            app(ImportNotionGoals::class)->import($workspace, $file, 2026);
            $this->assertSame(1, $workspace->goals()->where('status', 'not_started')->count());
            $this->assertSame(0, $workspace->goals()->where('status', 'not_done')->count());
        } finally {
            unlink($file);
        }
    }
}
