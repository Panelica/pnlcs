<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signing in with Google.
 *
 * Two columns, and both have to change together. The Google account id is
 * what a returning visitor is recognised by - matching on the email address
 * alone would let anyone who can spoof one take over an account. And the
 * password column was NOT NULL, so an account opened through Google had to be
 * given a random password that its owner could never use and that a password
 * reset would silently replace; nullable says what is true, which is that
 * this account has no password yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
        });

        // Rows opened through Google have no password to restore; a placeholder
        // keeps the NOT NULL constraint satisfiable without inventing a
        // password anybody could use (an empty hash matches nothing).
        \Illuminate\Support\Facades\DB::table('users')->whereNull('password')->update(['password' => '']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
