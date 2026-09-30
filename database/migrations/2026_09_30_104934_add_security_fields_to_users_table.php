<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 32)
                ->default('active')
                ->after('password');

            $table->timestamp('last_login_at')
                ->nullable()
                ->after('status');

            $table->ipAddress('last_login_ip')
                ->nullable()
                ->after('last_login_at');

            $table->unsignedInteger('failed_login_attempts')
                ->default(0)
                ->after('last_login_ip');

            $table->timestamp('locked_until')
                ->nullable()
                ->after('failed_login_attempts');

            $table->timestamp('password_changed_at')
                ->nullable()
                ->after('locked_until');

            $table->index('status');
            $table->index('locked_until');
            $table->index('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['locked_until']);
            $table->dropIndex(['last_login_at']);

            $table->dropColumn([
                'status',
                'last_login_at',
                'last_login_ip',
                'failed_login_attempts',
                'locked_until',
                'password_changed_at',
            ]);
        });
    }
};
