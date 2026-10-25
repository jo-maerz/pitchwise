<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            // Why the score could not be read, shown to the owner while parse_status is 'failed'.
            $table->string('parse_error')->nullable()->after('review_notes');
        });
    }

    public function down(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            $table->dropColumn('parse_error');
        });
    }
};
