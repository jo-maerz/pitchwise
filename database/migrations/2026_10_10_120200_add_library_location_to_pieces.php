<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            // Pieces are shared through the library now: deleting the uploader must not delete them.
            $table->dropForeign(['owner_id']);
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();

            // NULL organization = the shared library. Visibility follows the organization, not the uploader.
            $table->foreignId('organization_id')->nullable()->after('owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();

            $table->index(['organization_id', 'folder_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            // Foreign keys first: MySQL uses the composite index for the organization_id key.
            $table->dropForeign(['folder_id']);
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id', 'folder_id', 'title']);
            $table->dropColumn(['folder_id', 'organization_id']);
            $table->dropForeign(['owner_id']);
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
