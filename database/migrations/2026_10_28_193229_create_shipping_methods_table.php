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
        // The ways a customer can get their order, which the shop manages in
        // the admin panel: couriers, parcel lockers and pickup alike. Whether
        // an order needs an address is a property of the method, not a list
        // of known methods in the code. There is no manual order: they are
        // listed by price.
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            // What the customer is told about it at checkout and in their
            // confirmation: the delivery time, or where to collect the order.
            $table->json('description')->nullable();

            // Whole minor units (cents) of the shop's single currency, without
            // VAT: the VAT is added the way it is to a product's price.
            $table->unsignedInteger('price_excl_vat_cents')->default(0);

            // Collected at the shop: no delivery address is asked for.
            $table->boolean('is_pickup')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
