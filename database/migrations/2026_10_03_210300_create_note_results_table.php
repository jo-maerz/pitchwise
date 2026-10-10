<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('practice_sessions')->cascadeOnDelete();
            $table->unsignedInteger('note_index');
            $table->unsignedTinyInteger('expected_midi');
            $table->unsignedTinyInteger('detected_midi')->nullable();
            $table->decimal('detected_hz', 8, 2)->nullable();
            $table->decimal('cents_offset', 7, 2)->nullable();
            $table->enum('outcome', ['in_tune', 'sharp', 'flat', 'wrong_note', 'missed']);
            $table->decimal('clarity', 4, 3)->nullable();

            $table->unique(['session_id', 'note_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_results');
    }
};
