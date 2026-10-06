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
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();

            $table->string('group', 100)
                ->default('general');

            $table->string('key', 150)
                ->unique();

            $table->text('value')
                ->nullable();

            $table->string('type', 30)
                ->default('string');

            $table->text('description')
                ->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->timestamps();

            $table->index(
                ['group', 'is_active', 'sort_order'],
                'settings_group_active_order_index'
            );

            $table->index(
                ['is_active'],
                'settings_active_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
