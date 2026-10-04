<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('bpm');
            // The pitch rule used for this run, so old runs keep their meaning when settings change.
            $table->enum('tolerance_mode', ['cents', 'hz'])->default('cents');
            $table->decimal('tolerance_value', 6, 2)->default(30);
            $table->decimal('reference_hz', 5, 1)->default(440);
            $table->smallInteger('latency_ms')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->decimal('score_pct', 5, 2)->nullable();
            $table->timestamp('aggregated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'piece_id', 'finished_at']);
            $table->index(['finished_at', 'aggregated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_sessions');
    }
};
