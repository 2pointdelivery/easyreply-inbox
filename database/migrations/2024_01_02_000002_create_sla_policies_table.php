<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('priority'); // matches Conversation::priority, e.g. low/normal/high/urgent
            $table->unsignedInteger('first_response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->timestamps();

            $table->unique(['team_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
