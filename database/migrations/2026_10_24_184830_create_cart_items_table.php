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
        // Deliberately no price column: a cart is transient, so its totals
        // always come from the product's current price. The price a customer
        // actually agreed to is snapshotted on the order line at checkout.
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            // Restrict, not cascade: a product still in someone's cart (or,
            // later, on an order) must never vanish. Products soft delete.
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();

            // One line per product; adding it again raises the quantity.
            $table->unique(['cart_id', 'product_id']);
        });

        // Zero has no meaning (removing a line is its own action), and
        // `unsigned` already rules out negatives.
        DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_quantity_positive CHECK (quantity >= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
