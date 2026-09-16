<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('integration_key'); // linear, hubspot, betterstack
            $table->boolean('enabled')->default(false);
            $table->text('config')->nullable(); // encrypted JSON: API keys etc.
            $table->timestamps();

            $table->unique(['team_id', 'integration_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
    }
};
