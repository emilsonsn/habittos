<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_steps', function (Blueprint $table) {
            $table->decimal('canvas_x', 10, 2)->nullable();
            $table->decimal('canvas_y', 10, 2)->nullable();
        });
        Schema::create('study_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_roadmap_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_step_id')->constrained('study_steps')->cascadeOnDelete();
            $table->foreignId('target_step_id')->constrained('study_steps')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['source_step_id', 'target_step_id']);
            $table->index(['workspace_id', 'study_roadmap_id']);
        });
        $previous = null;
        foreach (DB::table('study_steps')->orderBy('study_roadmap_id')->orderBy('position')->orderBy('id')->cursor() as $step) {
            if ($previous && $previous->study_roadmap_id === $step->study_roadmap_id) {
                DB::table('study_connections')->insert([
                    'workspace_id' => $step->workspace_id, 'study_roadmap_id' => $step->study_roadmap_id,
                    'source_step_id' => $previous->id, 'target_step_id' => $step->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $previous = $step;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('study_connections');
        Schema::table('study_steps', fn (Blueprint $table) => $table->dropColumn(['canvas_x', 'canvas_y']));
    }
};
