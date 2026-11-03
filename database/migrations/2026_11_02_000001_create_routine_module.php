<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->string('title', 200);
            $table->date('starts_on');
            $table->date('ends_before')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'starts_on', 'ends_before']);
        });
        Schema::create('routine_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('routine_task_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->unsignedTinyInteger('weekday');
            $table->string('title', 200);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['routine_task_id', 'week_start']);
            $table->index(['workspace_id', 'week_start', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_activities');
        Schema::dropIfExists('routine_tasks');
    }
};
