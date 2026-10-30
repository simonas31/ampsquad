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
        // The places a customer has asked us to deliver to, so that checkout
        // can offer them. Only shipping: the shop issues no invoices from an
        // address. An order never reads one back; it copies the address into
        // its own delivery_address as text when it is placed, so editing or
        // deleting an address here never changes an order.
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            // Gone with the customer: they are only kept for as long as the
            // account is (see Customer::prunable()).
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // What the customer calls it, such as "Home" or "Office".
            $table->string('label', 50)->nullable();
            // Who receives the parcel and how to reach them, which is not
            // always the customer.
            $table->string('recipient_name', 150);
            $table->string('phone', 30);
            // Street and number, with the flat if any.
            $table->string('street');
            $table->string('city', 100);
            $table->string('postal_code', 20);
            // Lithuania only for now; kept as a code so that other countries
            // can be added without changing the table.
            $table->char('country', 3)->default('LT');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
