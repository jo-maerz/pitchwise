<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piece_annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            // NULL: the organization's shared layer. Otherwise that user's personal layer.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            // The score file the marks were placed on: a new upload can move the music under them.
            $table->string('source_path')->nullable();
            $table->longText('pages');
            $table->timestamps();

            $table->unique(['piece_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piece_annotations');
    }
};
