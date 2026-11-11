<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The code mailed after registering, as an alternative to the link: kept only as a hash, with an expiry
// and a count of wrong tries so it cannot be guessed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email_verification_code')->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_code_expires_at', 6)->nullable()->after('email_verification_code');
            $table->unsignedTinyInteger('email_verification_code_attempts')->default(0)->after('email_verification_code_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['email_verification_code', 'email_verification_code_expires_at', 'email_verification_code_attempts']);
        });
    }
};
