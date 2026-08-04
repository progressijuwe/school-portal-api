<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records that a user has asked for their password to be reset.
 *
 * Laravel's own `password_reset_tokens` table is deliberately not used: that
 * flow works by emailing a signed link, and this deployment has no mail
 * service. Resets are performed by an administrator instead, so all the system
 * needs to carry is "this person is locked out and has asked for help" — a
 * single timestamp on the user, cleared the moment the reset is done.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_reset_requested_at')
                ->nullable()
                ->after('must_change_password');

            // The admin users list filters on "has an outstanding request",
            // which is a small minority of rows.
            $table->index('password_reset_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['password_reset_requested_at']);
            $table->dropColumn('password_reset_requested_at');
        });
    }
};
