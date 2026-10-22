<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\CreateWorkspace;
use App\Services\OpenFinancialMonth;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('Demonstração disponível somente em ambiente local.');
        }
        $email = 'demo@habittos.test';
        if (User::where('email', $email)->exists()) {
            $this->command->warn('A conta de demonstração já existe. Nenhum dado foi alterado.');

            return;
        }
        $password = Str::random(20);
        $user = User::create(['name' => 'Alex Demo', 'email' => $email, 'password' => $password]);
        $workspace = app(CreateWorkspace::class)->handle($user, 'Meu espaço');
        $month = now()->startOfMonth();
        foreach ([['Salário', 'income', 620000, 1, true, false, null], ['Projeto extra', 'income', 85000, 12, true, false, null], ['Aluguel', 'expense', 140000, 5, true, true, 'Moradia'], ['Mercado da semana', 'expense', 23590, 8, true, false, 'Alimentação'], ['Café com amigos', 'expense', 6450, 13, true, false, 'Lazer'], ['Combustível', 'expense', 18000, 18, true, false, 'Transporte'], ['Internet', 'expense', 9990, 20, false, true, 'Moradia'], ['Passeio de fim de semana', 'expense', 12000, 24, false, false, 'Lazer'], ['Aporte mensal', 'investment', 65000, 10, true, false, null]] as [$description, $type, $amount, $day, $paid, $fixed, $category]) {
            $workspace->entries()->create(['description' => $description, 'type' => $type, 'amount_cents' => $amount, 'date' => $month->copy()->day($day), 'paid' => $paid, 'is_fixed' => $fixed, 'category_id' => $category ? $workspace->categories()->where('name', $category)->value('id') : null]);
        }
        app(OpenFinancialMonth::class)->handle($workspace, $month->format('Y-m'));
        $workspace->budgets()->create(['month' => $month->format('Y-m'), 'weekly_limit_cents' => 40000, 'reserve_cents' => 50000]);
        $workspace->goals()->create(['title' => 'Minha reserva de emergência', 'period' => 'annual', 'year' => now()->year, 'target_cents' => 2000000, 'current_cents' => 650000]);
        $this->command->info("Conta local: {$email} | Senha gerada: {$password}");
    }
}
