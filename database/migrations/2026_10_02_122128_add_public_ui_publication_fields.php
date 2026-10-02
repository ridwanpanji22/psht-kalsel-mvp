<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('cover_path')->nullable()->after('venue');
            $table->string('cover_alt_text')->nullable()->after('cover_path');
        });

        Schema::table('galleries', function (Blueprint $table): void {
            $table->string('category')->nullable()->after('description');

            $table->index(['status', 'category', 'published_at'], 'galleries_status_category_published_at_index');
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('is_public');

            $table->index(['gallery_id', 'is_public', 'sort_order', 'id'], 'media_gallery_id_is_public_sort_order_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropIndex('media_gallery_id_is_public_sort_order_id_index');
            $table->dropColumn('sort_order');
        });

        Schema::table('galleries', function (Blueprint $table): void {
            $table->dropIndex('galleries_status_category_published_at_index');
            $table->dropColumn('category');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['cover_path', 'cover_alt_text']);
        });
    }
};
