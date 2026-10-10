<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // admin | org_admin | user (App\Enums\Role)
            $table->string('role', 20)->default('user')->after('password');
            // NULL: no organization, only the shared library is visible.
            $table->foreignId('organization_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('role');
        });
        Schema::dropIfExists('organizations');
    }
};
