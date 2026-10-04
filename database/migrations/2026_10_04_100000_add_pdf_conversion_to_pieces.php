<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            // pending | converting | needs_review | ready | failed. A string, not an enum: enums are painful to alter.
            $table->string('parse_status', 20)->default('pending')->change();
            // A PDF upload has no MusicXML until the recogniser has produced one.
            $table->string('musicxml_path')->nullable()->change();
            $table->string('source_pdf_path')->nullable()->after('musicxml_path');
            // Why the recognised score may be wrong (shown to the owner on the review step).
            $table->text('review_notes')->nullable()->after('parse_status');
        });
    }

    public function down(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            $table->dropColumn(['source_pdf_path', 'review_notes']);
        });
    }
};
