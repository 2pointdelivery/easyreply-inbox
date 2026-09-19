<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voice_schedules', function (Blueprint $table) {
            // Free-form context (e.g. widget callback topic + source) the
            // agent prompt can use when the callback is dialed.
            $table->json('meta')->nullable()->after('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::table('voice_schedules', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
