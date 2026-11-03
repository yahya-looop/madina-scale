<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_media', function (Blueprint $t): void {
            $t->id();
            $t->string('disk', 40)->default('public');
            $t->string('path', 2048)->unique();
            $t->string('original_name', 255);
            $t->string('mime_type', 120);
            $t->unsignedBigInteger('size');
            $t->string('alt_text', 255)->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('cms_banners', function (Blueprint $t): void {
            $t->id();
            $t->string('title', 180);
            $t->string('image_url', 2048);
            $t->string('alt_text', 255)->nullable();
            $t->string('link_url', 2048)->nullable();
            $t->string('placement', 80)->default('homepage');
            $t->string('status', 20)->default('draft')->index();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['placement', 'status', 'sort_order']);
        });
        Schema::create('cms_content_sections', function (Blueprint $t): void {
            $t->id();
            $t->string('name', 180);
            $t->string('key', 190)->unique();
            $t->string('type', 60)->default('rich_text');
            $t->json('content');
            $t->boolean('is_active')->default(true)->index();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('cms_page_revisions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('cms_page_id')->constrained('cms_pages')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('change_note', 255)->nullable();
            $t->timestamps();
            $t->unique(['cms_page_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_page_revisions');
        Schema::dropIfExists('cms_content_sections');
        Schema::dropIfExists('cms_banners');
        Schema::dropIfExists('cms_media');
    }
};
