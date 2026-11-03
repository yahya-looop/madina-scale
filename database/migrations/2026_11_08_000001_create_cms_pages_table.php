<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->string('slug', 190)->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('content')->nullable();
            $table->json('sections')->nullable();
            $table->string('featured_image', 2048)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('meta_title', 180)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('meta_keywords', 500)->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
