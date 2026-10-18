<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Private staff notes about a client (allergies, preferences, colour formulas). Purely additive:
 * one nullable text column, so every existing client row stays exactly as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
