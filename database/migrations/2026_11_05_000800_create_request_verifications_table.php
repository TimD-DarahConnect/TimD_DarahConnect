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
        Schema::create('request_verifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('blood_request_id')
                ->constrained('blood_requests')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('reviewer_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('decision', 32);
            $table->string('previous_status', 32);
            $table->string('resulting_status', 32);
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('decided_at');

            $table->timestamps();

            $table->index(['blood_request_id', 'decided_at']);
            $table->index(['reviewer_id', 'decision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_verifications');
    }
};
