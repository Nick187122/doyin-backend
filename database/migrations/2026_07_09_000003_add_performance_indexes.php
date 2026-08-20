<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to add performance indexes on frequently queried columns.
     *
     * Tables indexed:
     * - products: name (ordering), in_stock (filtering), price (future filtering), views_count (popularity sorting), created_at (new arrivals)
     * - categories: name (ordering)
     * - hero_images: is_active + order (composite for public listing)
     * - user_interactions: type (filtering), is_read (admin filtering), created_at (ordering)
     * - testimonials: is_visible + sort_order (composite for public listing)
     * - salespersons: is_active (public listing)
     */
    public function up(): void
    {
        // Products
        Schema::table('products', function (Blueprint $table) {
            $table->index('name', 'idx_products_name');
            $table->index('in_stock', 'idx_products_in_stock');
            $table->index('price', 'idx_products_price');
            $table->index('views_count', 'idx_products_views_count');
            $table->index('created_at', 'idx_products_created_at');
        });

        // Categories
        Schema::table('categories', function (Blueprint $table) {
            $table->index('name', 'idx_categories_name');
        });

        // Hero images (composite index for the active + ordered query)
        Schema::table('hero_images', function (Blueprint $table) {
            $table->index(['is_active', 'order'], 'idx_hero_images_active_order');
        });

        // User interactions
        Schema::table('user_interactions', function (Blueprint $table) {
            $table->index('type', 'idx_interactions_type');
            $table->index('is_read', 'idx_interactions_is_read');
            $table->index('created_at', 'idx_interactions_created_at');
        });

        // Testimonials (composite index for visible + sorted query)
        Schema::table('testimonials', function (Blueprint $table) {
            $table->index(['is_visible', 'sort_order'], 'idx_testimonials_visible_sort');
        });

        // Salespersons
        Schema::table('salespersons', function (Blueprint $table) {
            $table->index('is_active', 'idx_salespersons_is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_name');
            $table->dropIndex('idx_products_in_stock');
            $table->dropIndex('idx_products_price');
            $table->dropIndex('idx_products_views_count');
            $table->dropIndex('idx_products_created_at');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('idx_categories_name');
        });

        Schema::table('hero_images', function (Blueprint $table) {
            $table->dropIndex('idx_hero_images_active_order');
        });

        Schema::table('user_interactions', function (Blueprint $table) {
            $table->dropIndex('idx_interactions_type');
            $table->dropIndex('idx_interactions_is_read');
            $table->dropIndex('idx_interactions_created_at');
        });

        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropIndex('idx_testimonials_visible_sort');
        });

        Schema::table('salespersons', function (Blueprint $table) {
            $table->dropIndex('idx_salespersons_is_active');
        });
    }
};
