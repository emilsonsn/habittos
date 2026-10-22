<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('user_workspace', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->json('permissions');
            $table->timestamps();
            $table->primary(['user_id', 'workspace_id']);
        });
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['workspace_id', 'name']);
        });
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('type');
            $table->unsignedBigInteger('amount_cents');
            $table->date('date');
            $table->boolean('paid')->default(false);
            $table->boolean('is_fixed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'date', 'type']);
        });
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);
            $table->unsignedBigInteger('weekly_limit_cents')->default(0);
            $table->unsignedBigInteger('reserve_cents')->default(0);
            $table->timestamps();
            $table->unique(['workspace_id', 'month']);
        });
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

    public function down(): void
    {
        foreach (['goals', 'budgets', 'entries', 'categories', 'user_workspace', 'workspaces'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
