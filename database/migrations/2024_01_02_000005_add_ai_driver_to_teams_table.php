<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Null = fall back to the package-wide config('shared-inbox.ai.driver').
            // Set to one of config('shared-inbox.ai.drivers') keys to override
            // per team. See AiDriverManager and Http/Controllers/AiSettingsController.
            $table->string('ai_driver')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('ai_driver');
        });
    }
};
