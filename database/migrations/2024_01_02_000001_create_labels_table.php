<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color')->default('#6366f1');
            $table->timestamps();

            $table->unique(['team_id', 'name']);
        });

        Schema::create('conversation_label', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();

            $table->primary(['conversation_id', 'label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_label');
        Schema::dropIfExists('labels');
    }
};
