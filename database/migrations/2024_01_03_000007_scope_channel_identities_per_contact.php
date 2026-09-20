<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Channel identities are looked up team-scoped (CallLinker,
        // ProcessInboundMessage, WidgetChatController), but the original
        // unique index was global — two teams hearing from the same phone
        // number or email would collide with a 500 on the second team's
        // first inbound message. Scope the uniqueness per contact instead;
        // the same person can still be recognized across channels via the
        // contact's own identity rows.
        Schema::table('contact_channel_identities', function (Blueprint $table) {
            $table->dropUnique(['channel_type', 'external_id']);
            $table->unique(['contact_id', 'channel_type', 'external_id']);
            $table->index(['channel_type', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('contact_channel_identities', function (Blueprint $table) {
            $table->dropUnique(['contact_id', 'channel_type', 'external_id']);
            $table->dropIndex(['channel_type', 'external_id']);
            $table->unique(['channel_type', 'external_id']);
        });
    }
};
