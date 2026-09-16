<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('app_slug'); // e.g. gmail, linear, slack — the Composio app being connected
            $table->string('composio_connection_id')->nullable();
            // Random token round-tripped through the OAuth redirect so the
            // callback can prove it belongs to the connection THIS team
            // initiated, not just any connectedAccountId Composio returns.
            $table->string('state')->unique();
            $table->json('scopes')->nullable();
            $table->string('status')->default('pending'); // pending, active, failed, revoked
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_connections');
    }
};
