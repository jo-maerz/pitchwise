<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            // NULL organization = the shared library every user sees.
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('folders')->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();

            $table->index(['organization_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};
