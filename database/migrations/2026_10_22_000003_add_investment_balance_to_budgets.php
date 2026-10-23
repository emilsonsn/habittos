<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->unsignedBigInteger('investment_balance_cents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('budgets', fn (Blueprint $table) => $table->dropColumn('investment_balance_cents'));
    }
};
