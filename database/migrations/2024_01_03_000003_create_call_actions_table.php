<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_log_id')->constrained()->cascadeOnDelete();
            $table->string('action_type');
            $table->json('payload')->nullable();
            $table->string('status')->default('done');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('call_log_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_actions');
    }
};
