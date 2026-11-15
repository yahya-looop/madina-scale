<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('password')->nullable()->after('email');
            $table->timestampTz('phone_verified_at')->nullable()->after('password');
            $table->rememberToken();
        });

        Schema::create('customer_login_otps', function (Blueprint $table): void {
            $table->id();
            $table->string('phone', 40)->index();
            $table->string('purpose', 20)->default('login');
            $table->string('code_hash', 255);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestampsTz();
            $table->index(['phone', 'purpose', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_login_otps');

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropRememberToken();
            $table->dropColumn(['password', 'phone_verified_at']);
        });
    }
};
