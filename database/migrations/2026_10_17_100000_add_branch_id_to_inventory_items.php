<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-branch, step 4. Purely additive: nullable branch_id on inventory_items. Existing stock is
 * assigned to the first branch (the original salon); an admin can move items to another branch.
 * No row is deleted or otherwise changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
        });

        $default = DB::table('branches')->orderBy('id')->value('id');
        DB::table('inventory_items')->whereNull('branch_id')->update(['branch_id' => $default]);
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
