<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('direction'); // inbound, outbound
            $table->string('channel'); // denormalized from the conversation's inbox
            $table->string('external_id')->nullable(); // provider message id, used for threading
            $table->string('in_reply_to_external_id')->nullable();
            $table->nullableMorphs('sender'); // sender_type/sender_id: a User (outbound) or a Contact (inbound)
            $table->text('body');
            $table->json('raw_payload')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->string('status')->default('sent'); // draft, sent
            $table->timestamps();

            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
