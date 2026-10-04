<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pieces', function (Blueprint $table) {
            $table->id();
            // NULL owner = catalogue piece, visible to every user.
            $table->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('composer')->nullable();
            $table->string('instrument', 40)->default('violin');
            $table->string('musicxml_path');
            $table->unsignedSmallInteger('default_bpm')->default(80);
            $table->unsignedTinyInteger('beats_per_measure')->default(4);
            $table->unsignedInteger('note_count')->default(0);
            $table->enum('parse_status', ['pending', 'ready', 'failed'])->default('pending');
            $table->timestamps();

            $table->index(['owner_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pieces');
    }
};
