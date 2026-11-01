<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\ImportNotionGoals as Importer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class ImportNotionGoals extends Command
{
    protected $signature = 'goals:import-notion {file : Caminho do CSV completo do Notion} {--workspace= : ID do ambiente de destino} {--year= : Ano das metas anuais}';

    protected $description = 'Importa todas as metas anuais do CSV completo do Notion, sem duplicar uma reimportação';

    public function handle(Importer $importer): int
    {
        $data = validator($this->options(), ['workspace' => ['required', 'integer', 'min:1'], 'year' => ['required', 'integer', 'between:2000,2100']])->validate();
        $workspace = Workspace::findOrFail($data['workspace']);
        $path = $this->argument('file');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('Arquivo CSV não encontrado ou sem acesso de leitura.');

            return self::FAILURE;
        }
        Auth::onceUsingId($workspace->owner_id);
        $result = $importer->import($workspace, $path, (int) $data['year']);
        $this->info("{$result['rows']} linha(s): {$result['created']} meta(s) criada(s), {$result['updated']} atualizada(s), no ambiente {$workspace->name} ({$workspace->id}), ano {$data['year']}.");

        return self::SUCCESS;
    }
}
