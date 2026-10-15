<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Forgot password" mails a one-time temporary password. Only its hash is kept, next to the
// normal password (which keeps working), with an expiry; signing in with it forces a new password.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('temporary_password')->nullable()->after('password');
            $table->timestamp('temporary_password_expires_at', 6)->nullable()->after('temporary_password');
            $table->boolean('must_change_password')->default(false)->after('temporary_password_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['temporary_password', 'temporary_password_expires_at', 'must_change_password']);
        });
    }
};
