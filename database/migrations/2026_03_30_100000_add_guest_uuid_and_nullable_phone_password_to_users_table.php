<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Allow guest users: phone and password are no longer required at DB level.
            // Existing normal users are unaffected — they already have values in both columns.
            $table->string('phone')->nullable()->change();
            $table->string('password')->nullable()->change();

            // UUID used to identify guest (unauthenticated) users.
            $table->string('guest_uuid')->nullable()->unique()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('guest_uuid');
            $table->string('phone')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
