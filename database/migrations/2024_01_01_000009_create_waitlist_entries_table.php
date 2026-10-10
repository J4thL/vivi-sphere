<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('client_name');
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('stylist_id')->nullable()->constrained('stylists')->nullOnDelete();
            $table->date('preferred_date');
            $table->enum('status', ['waiting', 'converted', 'cancelled'])->default('waiting');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
