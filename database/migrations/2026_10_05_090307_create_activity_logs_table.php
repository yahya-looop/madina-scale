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
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();

            /*
             * Actor
             *
             * actor_user_id is intentionally nullable because some future
             * system-generated activities may not originate from a user.
             *
             * Developer actions keep the real developer user ID internally,
             * while actor_type is stored as "system" so the UI can present
             * the actor as "System".
             */
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('actor_type', 20)
                ->default('user');

            /*
             * Action classification.
             *
             * Examples:
             * - login
             * - logout
             * - create
             * - update
             * - delete
             * - status_change
             * - role_assign
             * - permission_change
             */
            $table->string('action', 100);

            /*
             * Application/module classification.
             *
             * Examples:
             * - authentication
             * - users
             * - roles
             * - permissions
             * - settings
             */
            $table->string('module', 100);

            /*
             * Human-readable explanation of the activity.
             */
            $table->text('description');

            /*
             * Polymorphic subject.
             *
             * Example:
             * User #12
             * Role #3
             * Permission #8
             */
            $table->nullableMorphs('subject');

            /*
             * Before/after snapshots.
             *
             * PostgreSQL uses JSONB for efficient structured querying.
             */
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();

            /*
             * Request/network metadata.
             */
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id', 100)->nullable();

            /*
             * Additional extensible metadata.
             *
             * This prevents the activity-log schema from needing a migration
             * every time a new contextual value becomes useful.
             */
            $table->jsonb('metadata')->nullable();

            /*
             * Audit records are immutable.
             *
             * We intentionally store only created_at and do not maintain
             * updated_at because an activity should never be edited.
             */
            $table->timestamp('created_at')->useCurrent();

            /*
             * Common audit-log query indexes.
             */
            $table->index(
                ['actor_type', 'actor_user_id'],
                'activity_logs_actor_index',
            );

            $table->index(
                ['module', 'action'],
                'activity_logs_module_action_index',
            );

            $table->index(
                ['request_id'],
                'activity_logs_request_id_index',
            );

            $table->index(
                ['created_at'],
                'activity_logs_created_at_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
