<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('note_categories')->nullOnDelete();
            $table->string('name', 100);
            $table->timestamps();
            $table->index(['workspace_id', 'parent_id']);
        });
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('note_categories')->nullOnDelete();
            $table->string('title', 200);
            $table->longText('text')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();
            $table->index(['workspace_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
        Schema::dropIfExists('note_categories');
    }
};
