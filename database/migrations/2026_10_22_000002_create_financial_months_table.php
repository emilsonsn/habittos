<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);
            $table->timestamps();
            $table->unique(['workspace_id', 'month']);
        });

        // Meses já existentes são preservados; não recebem cópias retroativas.
        DB::table('entries')->select('workspace_id', 'date')->distinct()->orderBy('workspace_id')->get()
            ->each(function ($entry) {
                DB::table('financial_months')->insertOrIgnore([
                    'workspace_id' => $entry->workspace_id,
                    'month' => substr($entry->date, 0, 7),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_months');
    }
};
