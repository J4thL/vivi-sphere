<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stores each booking's length at the time it was made. Until now the length was read from the
 * service, so changing a service from 45 to 90 minutes silently stretched every past and future
 * appointment and could make existing bookings overlap. Existing rows are filled from their
 * service's current length (the best information available); the column stays nullable and the
 * app falls back to the service length if it is ever empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('service_id');
        });

        DB::statement('UPDATE appointments SET duration_minutes = (SELECT duration_minutes FROM services WHERE services.id = appointments.service_id)');
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};
