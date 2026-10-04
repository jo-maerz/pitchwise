<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piece_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('note_index');
            $table->unsignedInteger('measure');
            $table->unsignedTinyInteger('midi_pitch');
            // Position and length in quarter-note beats, from the start of the piece (repeats played once).
            $table->decimal('onset_beats', 10, 4);
            $table->decimal('duration_beats', 8, 4);

            $table->unique(['piece_id', 'note_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piece_notes');
    }
};
