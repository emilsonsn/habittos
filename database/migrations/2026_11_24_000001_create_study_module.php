<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_roadmaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->longText('description')->nullable();
            $table->string('color', 7)->default('#9589e8');
            $table->string('status', 20)->default('not_started');
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'status']);
        });
        Schema::create('study_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_roadmap_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['study_roadmap_id', 'position']);
        });
        Schema::create('study_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_roadmap_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_step_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->longText('description')->nullable();
            $table->text('checkpoint')->nullable();
            $table->string('status', 20)->default('not_started');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['study_step_id', 'position']);
        });
        Schema::create('study_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_roadmap_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_topic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('topic_title', 200);
            $table->date('studied_on');
            $table->unsignedInteger('minutes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'studied_on']);
        });
        Schema::create('study_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_roadmap_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('study_topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->text('url')->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('status', 20)->default('to_read');
            $table->timestamps();
            $table->index(['workspace_id', 'study_roadmap_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_materials');
        Schema::dropIfExists('study_sessions');
        Schema::dropIfExists('study_topics');
        Schema::dropIfExists('study_steps');
        Schema::dropIfExists('study_roadmaps');
    }
};
