<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Null = fall back to the package-wide config('shared-inbox.voice.*').
            $table->string('voice_driver')->nullable()->after('ai_driver');
            $table->text('voice_prompt')->nullable()->after('voice_driver');
            $table->string('voice_number_override')->nullable()->after('voice_prompt');
            $table->string('transfer_target')->nullable()->after('voice_number_override');
            $table->json('business_hours')->nullable()->after('transfer_target');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['voice_driver', 'voice_prompt', 'voice_number_override', 'transfer_target', 'business_hours']);
        });
    }
};
