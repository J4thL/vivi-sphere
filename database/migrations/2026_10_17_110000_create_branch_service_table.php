<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-branch, step 5. A new table only: no existing table or row is touched.
 * A row says "at this branch, this service has its own price / length / availability".
 * No row (or a NULL price/length) means "use the service's normal price/length", so until an
 * admin sets something, every branch behaves exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->decimal('price', 8, 2)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('available')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_service');
    }
};
