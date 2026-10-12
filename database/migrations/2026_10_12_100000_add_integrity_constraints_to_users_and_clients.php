<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - A login may own at most one client record (clients.user_id unique). Two would split one
 *   person's visit history, and the app looks the record up with firstOrCreate(['user_id' => ...]).
 * - A stylist login must point at a real stylist, and two logins may not share a stylist
 *   (users.stylist_id gets a foreign key + unique). The users table is created before the
 *   stylists table, which is why this could not be declared there.
 *
 * Nothing is changed or deleted. If existing rows already break a rule the migration stops
 * BEFORE touching the schema and says which rows to fix (MySQL cannot roll back DDL).
 */
return new class extends Migration
{
    public function up(): void
    {
        $dupClients = DB::table('clients')->whereNotNull('user_id')->groupBy('user_id')->havingRaw('count(*) > 1')->pluck('user_id');
        $dupStylistLogins = DB::table('users')->whereNotNull('stylist_id')->groupBy('stylist_id')->havingRaw('count(*) > 1')->pluck('stylist_id');
        $orphanLogins = DB::table('users')->whereNotNull('stylist_id')->whereNotIn('stylist_id', DB::table('stylists')->select('id'))->pluck('id');

        $problems = array_filter([
            $dupClients->isNotEmpty() ? 'clients.user_id used by more than one client record: user ids '.$dupClients->implode(', ') : null,
            $dupStylistLogins->isNotEmpty() ? 'users.stylist_id shared by several logins: stylist ids '.$dupStylistLogins->implode(', ') : null,
            $orphanLogins->isNotEmpty() ? 'users.stylist_id points at a stylist that does not exist: user ids '.$orphanLogins->implode(', ') : null,
        ]);
        if ($problems) {
            throw new RuntimeException("Fix this data first, then run the migration again (nothing was changed):\n - ".implode("\n - ", $problems));
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->unique('user_id', 'clients_user_id_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('stylist_id', 'users_stylist_id_unique');
            $table->foreign('stylist_id', 'users_stylist_id_foreign')->references('id')->on('stylists')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['stylist_id']);
            $table->dropUnique('users_stylist_id_unique');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique('clients_user_id_unique');
        });
    }
};
