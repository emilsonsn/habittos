<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('goals', 'target_value')) {
            Schema::table('goals', fn (Blueprint $table) => $table->dropColumn('target_value'));
        }
    }

    public function down(): void
    {
        Schema::table('goals', fn (Blueprint $table) => $table->decimal('target_value', 12, 2)->default(100));
    }
};
