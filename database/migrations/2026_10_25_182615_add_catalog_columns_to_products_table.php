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
        Schema::table('products', function (Blueprint $table) {
            // The catalog import's upsert key. Nullable because products
            // created by hand in the admin panel have none; MySQL allows
            // any number of NULLs in a unique index.
            $table->string('sku', 64)->nullable()->unique();
            // Not unique: variants of one product can share a barcode.
            $table->string('ean', 14)->nullable()->index();
            // NULL means stock is not tracked and the product is always
            // available, which is what every product had before this column.
            $table->unsignedInteger('stock_quantity')->nullable();
            // Hotlinked from the supplier, never downloaded. The uploaded
            // featured_image media wins when a product has both.
            $table->string('image_url', 500)->nullable();
            $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manufacturer_id');
            $table->dropConstrainedForeignId('product_category_id');
            $table->dropColumn(['image_url', 'stock_quantity']);
            $table->dropIndex(['ean']);
            $table->dropUnique(['sku']);
            $table->dropColumn(['ean', 'sku']);
        });
    }
};
