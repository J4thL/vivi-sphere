<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// This file replaces Laravel's default users migration (same filename, so
// it overwrites on copy) so there's only ever one migration for `users` -
// no duplicate-table conflict. stylist_id is intentionally NOT a hard
// foreign key: enforcing it here would force this migration to run after
// the stylists table exists, which would break Laravel's own migration
// order (cache/jobs tables expect to run right after users). The
// relationship is still enforced at the application layer via Eloquent.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'manager', 'stylist', 'client'])->default('client');
            $table->unsignedBigInteger('stylist_id')->nullable(); // links to stylists.id when role = stylist
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
