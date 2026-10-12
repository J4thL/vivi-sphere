<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appointments and reviews were declared "on delete cascade", so removing a client, stylist,
 * service or appointment would silently wipe booking and review history. They become
 * "restrict": the database now refuses such a delete instead. No existing row is touched, and
 * the app has no endpoint that deletes these records (ServiceController already refuses when
 * bookings exist).
 */
return new class extends Migration
{
    private const APPOINTMENT_KEYS = ['client_id' => 'clients', 'stylist_id' => 'stylists', 'service_id' => 'services'];

    private const REVIEW_KEYS = ['appointment_id' => 'appointments', 'client_id' => 'clients', 'stylist_id' => 'stylists'];

    public function up(): void
    {
        $this->rewire('appointments', self::APPOINTMENT_KEYS, restrict: true);
        $this->rewire('reviews', self::REVIEW_KEYS, restrict: true);
    }

    public function down(): void
    {
        $this->rewire('appointments', self::APPOINTMENT_KEYS, restrict: false);
        $this->rewire('reviews', self::REVIEW_KEYS, restrict: false);
    }

    private function rewire(string $table, array $keys, bool $restrict): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($keys) {
            foreach ($keys as $column => $parent) {
                $blueprint->dropForeign([$column]);
            }
        });

        Schema::table($table, function (Blueprint $blueprint) use ($table, $keys, $restrict) {
            foreach ($keys as $column => $parent) {
                $fk = $blueprint->foreign($column, "{$table}_{$column}_foreign")->references('id')->on($parent);
                $restrict ? $fk->restrictOnDelete() : $fk->cascadeOnDelete();
            }
        });
    }
};
