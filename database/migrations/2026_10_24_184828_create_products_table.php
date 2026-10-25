<?php

declare(strict_types=1);

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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('slug');
            $table->json('excerpt')->nullable();
            $table->json('description')->nullable();

            // The catalog import's upsert key. Nullable because products
            // created by hand in the admin panel have none; MySQL allows
            // any number of NULLs in a unique index.
            $table->string('sku', 64)->nullable()->unique();
            // Not unique: variants of one product can share a barcode.
            $table->string('ean', 14)->nullable()->index();

            // Whole minor units (cents) of the shop's single currency, EUR.
            // Integers keep line totals exact and are what payment providers
            // expect, so no currency column or decimal math is needed yet.
            $table->unsignedInteger('price_cents');

            // Owned by the shop, never by the catalog import: an import only
            // seeds it when it creates a product. 0 means sold out, and every
            // new product starts there until someone stocks it.
            $table->unsignedInteger('stock_quantity')->default(0);

            $table->boolean('is_active')->default(true);

            // Hotlinked from the supplier, never downloaded. The uploaded
            // featured_image media wins when a product has both.
            $table->string('image_url', 500)->nullable();
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            // Same generated-column approach as projects.slug_lt/slug_en —
            // gives the public route a real column to query by locale and a
            // unique index to enforce slugs at the DB layer.
            $table->string('slug_lt')->virtualAs('json_unquote(json_extract(`slug`, \'$."lt"\'))');
            $table->string('slug_en')->virtualAs('json_unquote(json_extract(`slug`, \'$."en"\'))');
            $table->unique('slug_lt');
            $table->unique('slug_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
