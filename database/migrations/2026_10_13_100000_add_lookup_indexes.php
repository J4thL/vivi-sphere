<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index-only migration: adds no columns, changes no rows, and drops nothing, so it is safe to run
 * on a database that already holds data. Each index matches a query the app already runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // Public landing page: approved reviews, newest first, limited.
            $table->index(['status', 'created_at'], 'reviews_status_created_index');
        });

        Schema::table('waitlist_entries', function (Blueprint $table) {
            // Manager list: status = waiting, ordered by preferred_date.
            $table->index(['status', 'preferred_date'], 'waitlist_status_date_index');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            // Audit screen: newest 200 entries.
            $table->index('created_at', 'audit_logs_created_at_index');
            // "Everything that happened to this record".
            $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_index');
        });

        Schema::table('clients', function (Blueprint $table) {
            // Manager client list ordered by spend, and the "name already exists" check when booking.
            $table->index('spend', 'clients_spend_index');
            $table->index('name', 'clients_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', fn (Blueprint $t) => $t->dropIndex('reviews_status_created_index'));
        Schema::table('waitlist_entries', fn (Blueprint $t) => $t->dropIndex('waitlist_status_date_index'));
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->dropIndex('audit_logs_created_at_index');
            $t->dropIndex('audit_logs_subject_index');
        });
        Schema::table('clients', function (Blueprint $t) {
            $t->dropIndex('clients_spend_index');
            $t->dropIndex('clients_name_index');
        });
    }
};
