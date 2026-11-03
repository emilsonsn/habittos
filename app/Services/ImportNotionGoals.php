<?php

namespace App\Services;

use App\Enums\GoalPeriod;
use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportNotionGoals
{
    public function import(Workspace $workspace, string $path, int $year): array
    {
        $file = fopen($path, 'r');
        if (! $file) {
            throw ValidationException::withMessages(['file' => 'Não foi possível abrir o CSV.']);
        }
        try {
            $header = fgetcsv($file, escape: '');
            if (! $header) {
                throw ValidationException::withMessages(['file' => 'CSV sem cabeçalho.']);
            }
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            $required = ['Nome da meta', 'Categoria', 'Feito (%)', 'Prazo', 'Prioridade', 'Progresso', 'Status'];
            if (array_diff($required, $header)) {
                throw ValidationException::withMessages(['file' => 'O CSV deve conter nome da meta, categoria, feito (%), prazo, prioridade, progresso e status.']);
            }
            $rows = [];
            while (($row = fgetcsv($file, escape: '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                if (count($row) !== count($header)) {
                    throw ValidationException::withMessages(['file' => 'Uma linha do CSV possui quantidade inválida de campos.']);
                }
                $rows[] = array_combine($header, $row);
            }
        } finally {
            fclose($file);
        }

        return DB::transaction(function () use ($workspace, $rows, $year) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $created = 0;
            $updated = 0;
            $priorities = array_flip(GoalPriority::options());
            $statuses = array_flip(GoalStatus::options());
            foreach ($rows as $row) {
                $key = hash('sha256', 'notion|annual|'.$year.'|'.mb_strtolower(trim($row['Nome da meta'])));
                $existing = $workspace->goals()->where('import_key', $key)->first();
                $deadline = trim($row['Prazo']);
                if ($deadline !== '' && (! CarbonImmutable::canBeCreatedFromFormat($deadline, 'd/m/Y')
                    || CarbonImmutable::createFromFormat('!d/m/Y', $deadline)->format('d/m/Y') !== $deadline)) {
                    throw ValidationException::withMessages(['file' => 'Prazo inválido em '.$row['Nome da meta']]);
                }
                $data = [
                    'title' => $row['Nome da meta'], 'period' => GoalPeriod::Annual->value, 'year' => $year,
                    'priority' => $priorities[trim($row['Prioridade'])] ?? '',
                    'status' => $statuses[trim($row['Status'])] ?? '',
                    'category' => $row['Categoria'],
                    'due_date' => $deadline !== '' ? CarbonImmutable::createFromFormat('!d/m/Y', $deadline)->toDateString() : null,
                    'progress_percent' => str_replace(',', '.', trim($row['Feito (%)'])),
                ];
                $goal = app(ManageGoals::class)->save($workspace, $data, $existing?->id);
                $goal->update(['import_key' => $key]);
                $existing ? $updated++ : $created++;
            }

            return compact('created', 'updated') + ['rows' => count($rows)];
        });
    }
}
