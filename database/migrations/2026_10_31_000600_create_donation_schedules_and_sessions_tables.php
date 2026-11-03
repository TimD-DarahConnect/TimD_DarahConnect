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
        Schema::create('donation_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('schedule_code', 28)->unique();
            $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('schedule_type', 24)->default('udd_building')->index();
            $table->string('title', 160);
            $table->string('partner_institution', 160)->nullable();
            $table->date('service_date')->index();
            $table->time('opening_time');
            $table->time('closing_time');
            $table->string('venue_name', 160);
            $table->string('venue_address', 280);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('target_bags')->default(0);
            $table->string('publication_status', 24)->default('published')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['facility_id', 'service_date', 'schedule_type'], 'idx_facility_date_type');
        });

        Schema::create('donation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donation_schedule_id')->constrained('donation_schedules')->cascadeOnDelete();
            $table->string('session_label', 80);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('quota_total');
            $table->unsignedSmallInteger('quota_booked')->default(0);
            $table->string('operational_status', 24)->default('open')->index();
            $table->string('operational_note', 280)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['donation_schedule_id', 'operational_status'], 'idx_schedule_session_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donation_sessions');
        Schema::dropIfExists('donation_schedules');
    }
};
