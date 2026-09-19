<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Strict no-audio policy: transcript + summary + metadata only.
        // Deliberately NO recording_url / audio_path columns — providers may
        // host audio on their side, but this package never persists a link.
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voice_agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inbox_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction')->default('inbound');
            $table->string('from_e164')->nullable();
            $table->string('to_e164')->nullable();
            $table->string('provider')->default('null');
            $table->string('provider_call_id')->unique();
            $table->string('status')->default('completed');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->text('transcript')->nullable();
            $table->text('summary')->nullable();
            $table->string('sentiment')->nullable();
            $table->string('priority_suggestion')->nullable();
            $table->json('post_actions')->nullable();
            $table->string('transfer_outcome')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
