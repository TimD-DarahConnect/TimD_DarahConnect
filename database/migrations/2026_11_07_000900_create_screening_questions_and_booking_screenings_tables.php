<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('screening_questions', function (Blueprint $table) {
            $table->id();
            $table->string('question_code', 16)->unique();
            $table->unsignedTinyInteger('sort_order')->index();
            $table->string('section_key', 40)->index();
            $table->string('section_title', 100);
            $table->text('question_text');
            $table->boolean('expected_answer')->default(false);
            $table->string('deferral_note', 320);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('booking_screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donor_booking_id')->unique()->constrained('donor_bookings')->cascadeOnDelete();
            $table->foreignId('donor_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('total_questions')->default(20);
            $table->unsignedTinyInteger('passed_count')->default(0);
            $table->unsignedTinyInteger('flagged_count')->default(0);
            $table->boolean('is_eligible')->index();
            $table->json('responses');
            $table->json('flagged_reasons')->nullable();
            $table->timestamp('consent_confirmed_at');
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->index(['donor_id', 'is_eligible']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_screenings');
        Schema::dropIfExists('screening_questions');
    }
};
