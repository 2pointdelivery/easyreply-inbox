<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_channel_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('channel_type'); // email, slack, whatsapp, instagram
            $table->string('external_id'); // email address / Slack user id / phone number / IG handle
            $table->timestamps();

            $table->unique(['channel_type', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_channel_identities');
        Schema::dropIfExists('contacts');
    }
};
