<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Overlap checks and a stylist's calendar: "this stylist, around this time".
            $table->index(['stylist_id', 'scheduled_at'], 'appointments_stylist_time_index');
            // Day views and reports: "everything between two dates".
            $table->index('scheduled_at', 'appointments_scheduled_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_stylist_time_index');
            $table->dropIndex('appointments_scheduled_at_index');
        });
    }
};
