<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // What was sold, as it was sold: the name, SKU and prices are copied
        // from the product, so repricing, renaming or deleting the product
        // later leaves the order as it was. The product id is only a pointer
        // for the admin to follow.
        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            // Restrict, not cascade: orders are never deleted.
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            // Products soft delete and are never force deleted, so this never dangles.
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            $table->string('product_name');
            // Null for a product made by hand in the admin panel.
            $table->string('sku', 64)->nullable();
            $table->unsignedSmallInteger('quantity');

            // The unit price with VAT is rounded once, and the line is that
            // times the quantity, so the lines a customer sees add up exactly.
            $table->unsignedInteger('unit_price_excl_vat_cents');
            $table->unsignedInteger('unit_price_incl_vat_cents');
            $table->unsignedInteger('line_total_excl_vat_cents');
            $table->unsignedInteger('line_total_incl_vat_cents');

            $table->timestamps();

            // One line per product, like a cart.
            $table->unique(['order_id', 'product_id']);
        });

        DB::statement('ALTER TABLE order_lines ADD CONSTRAINT order_lines_quantity_positive CHECK (quantity >= 1)');
        DB::statement('ALTER TABLE order_lines ADD CONSTRAINT order_lines_totals_add_up CHECK (line_total_excl_vat_cents = quantity * unit_price_excl_vat_cents AND line_total_incl_vat_cents = quantity * unit_price_incl_vat_cents)');
        DB::statement('ALTER TABLE order_lines ADD CONSTRAINT order_lines_vat_not_negative CHECK (unit_price_incl_vat_cents >= unit_price_excl_vat_cents)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
