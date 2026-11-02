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
        Schema::create('donor_commitments', function (Blueprint $table) {
            $table->id();
            $table->string('pledge_code', 28)->unique();
            $table->foreignId('blood_request_id')->constrained('blood_requests')->cascadeOnDelete();
            $table->foreignId('donor_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->default('committed')->index();
            $table->unsignedTinyInteger('bags_pledged')->default(1);
            $table->timestamp('estimated_arrival_at');
            $table->boolean('health_readiness_confirmed')->default(true);
            $table->string('pledge_notes', 350)->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 350)->nullable();
            $table->string('meeting_point', 180)->nullable();
            $table->string('coordination_code', 16)->nullable();
            $table->timestamps();

            $table->index(['blood_request_id', 'status']);
            $table->index(['donor_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donor_commitments');
    }
};
