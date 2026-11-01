<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('goals');
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('goals')->nullOnDelete();
            $table->string('title', 200);
            $table->string('period', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedTinyInteger('week')->nullable();
            $table->string('priority', 10)->default('medium');
            $table->string('category', 80)->nullable();
            $table->string('status', 20)->default('not_done');
            $table->date('due_date')->nullable();
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->string('import_key', 64)->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'period', 'year']);
            $table->unique(['workspace_id', 'import_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('period');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedBigInteger('target_cents');
            $table->unsignedBigInteger('current_cents')->default(0);
            $table->timestamps();
        });
    }
};
