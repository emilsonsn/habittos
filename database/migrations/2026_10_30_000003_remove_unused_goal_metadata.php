<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['team', 'place', 'owner_name'] as $column) {
            if (Schema::hasColumn('goals', $column)) {
                Schema::table('goals', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->string('team', 120)->nullable();
            $table->string('place', 160)->nullable();
            $table->string('owner_name', 120)->nullable();
        });
    }
};
