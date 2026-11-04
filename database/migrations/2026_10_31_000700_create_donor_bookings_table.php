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
        Schema::create('donor_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code', 28)->unique();
            $table->string('qr_token', 64)->unique();
            $table->foreignId('donor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('donation_schedule_id')->constrained('donation_schedules')->cascadeOnDelete();
            $table->foreignId('donation_session_id')->constrained('donation_sessions')->cascadeOnDelete();
            $table->string('status', 28)->default('confirmed')->index();
            $table->string('donation_type', 24)->default('WB');
            $table->string('donor_notes', 350)->nullable();
            $table->boolean('screening_passed')->nullable();
            $table->timestamp('screening_completed_at')->nullable();
            $table->string('screening_summary_note', 350)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('rescheduled_from_session_id')->nullable()->constrained('donation_sessions')->nullOnDelete();
            $table->unsignedTinyInteger('reschedule_count')->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 350)->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->index(['donor_id', 'status']);
            $table->index(['donation_session_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donor_bookings');
    }
};
