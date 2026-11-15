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
        Schema::table('donor_commitments', function (Blueprint $table) {
            $table->string('building_floor', 120)->nullable()->after('meeting_point');
            $table->string('companion_alias', 120)->nullable()->after('building_floor');
            $table->string('meeting_instructions', 350)->nullable()->after('companion_alias');
            $table->timestamp('meeting_point_updated_at')->nullable()->after('coordination_code');
            $table->timestamp('meeting_point_confirmed_at')->nullable()->after('meeting_point_updated_at');
        });

        Schema::create('coordination_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donor_commitment_id')->constrained('donor_commitments')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('sender_role', 24);
            $table->string('message_type', 32)->default('contextual_note');
            $table->string('preset_key', 48)->nullable();
            $table->string('body', 400);
            $table->boolean('contained_masked_contact')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['donor_commitment_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coordination_messages');

        Schema::table('donor_commitments', function (Blueprint $table) {
            $table->dropColumn([
                'building_floor',
                'companion_alias',
                'meeting_instructions',
                'meeting_point_updated_at',
                'meeting_point_confirmed_at',
            ]);
        });
    }
};
