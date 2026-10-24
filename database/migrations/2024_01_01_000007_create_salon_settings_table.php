<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salon_settings', function (Blueprint $table) {
            $table->id();
            $table->string('salon_name')->default('Gilt & Grace Salon');
            $table->string('hero_headline');
            $table->text('hero_sub');
            $table->json('featured_services')->nullable(); // array of service names to feature on the landing page
            $table->json('testimonials')->nullable();      // [{name, quote}]
            $table->json('faqs')->nullable();               // [{question, answer}]
            $table->string('contact_phone')->nullable();
            $table->string('contact_address')->nullable();
            $table->string('map_query')->nullable();        // address string used to build the embedded map
            $table->text('cancellation_policy')->nullable();
            $table->time('open_time')->default('09:00');
            $table->time('close_time')->default('19:00');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_settings');
    }
};
