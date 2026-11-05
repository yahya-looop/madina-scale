<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table): void {
            $table->string('state', 20)->default('enabled')->after('is_active')->index();
        });

        DB::table('modules')->where('is_active', false)->update(['state' => 'disabled']);

        Schema::create('feature_flags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key', 150)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedTinyInteger('rollout_percentage')->default(100);
            $table->string('environment', 20)->default('all');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->index(['is_enabled', 'environment']);
            $table->index('module_id');
        });

        DB::table('feature_flags')->insert([
            'module_id' => DB::table('modules')->where('slug', 'content')->value('id'),
            'key' => 'storefront.checkout.enabled',
            'name' => 'Storefront checkout',
            'description' => 'Allow customers to submit storefront orders.',
            'is_enabled' => true,
            'rollout_percentage' => 100,
            'environment' => 'all',
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key', 150)->unique();
            $table->string('label', 120);
            $table->string('route', 255)->nullable();
            $table->string('icon', 80)->default('circle');
            $table->string('permission', 150)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('developer_only')->default(false);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->index(['parent_id', 'is_visible', 'sort_order']);
            $table->index(['module_id', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('feature_flags');
        Schema::table('modules', function (Blueprint $table): void {
            $table->dropIndex(['state']);
            $table->dropColumn('state');
        });
    }
};
