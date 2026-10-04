<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_pitch_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('midi_pitch');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('in_tune')->default(0);
            // Mean offset of notes that were in tune, sharp or flat (wrong notes and misses excluded).
            $table->decimal('avg_cents', 7, 2)->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['user_id', 'midi_pitch']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_pitch_stats');
    }
};
