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
        // A guest's order. There are no customer accounts, so everything about
        // the customer is kept here, as it was when they ordered.
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // The id the public sees (page URLs), never the sequential one.
            $table->uuid('uuid')->unique();
            // What the customer and the shop quote: random, not a count, so it
            // says nothing about how many orders there are. The payment
            // provider gets this as the order's reference.
            $table->string('number', 40)->unique();

            $table->string('status', 20)->default('pending_payment');
            // The language the customer ordered in: their emails and the
            // product names on the order's lines are in it.
            $table->char('locale', 2);

            // Whole minor units (cents) of the shop's single currency. The
            // rate is kept because it is config: it can change, orders can not.
            // Subtotal, shipping and VAT always add up to the total, which the
            // database checks. The subtotal is the lines without VAT; shipping
            // is kept both ways, as the lines are, so nothing is worked out
            // again later with a rate that may have changed.
            $table->char('currency', 3)->default('EUR');
            $table->unsignedTinyInteger('vat_rate_percent');
            $table->unsignedInteger('subtotal_excl_vat_cents');
            $table->unsignedInteger('shipping_excl_vat_cents')->default(0);
            $table->unsignedInteger('shipping_incl_vat_cents')->default(0);
            $table->unsignedInteger('vat_cents');
            // What the customer pays, and what the payment provider is asked for.
            $table->unsignedInteger('total_incl_vat_cents');

            // The method is only a pointer for the admin to follow: it can be
            // edited or deleted later, so everything the order needs of it is
            // copied here as it was when the order was placed.
            $table->foreignId('shipping_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shipping_label', 100);
            $table->string('shipping_details', 500)->nullable();
            $table->boolean('shipping_is_pickup')->default(false);
            // An address, or the parcel locker. Empty for pickup.
            $table->string('delivery_address', 500)->nullable();

            $table->string('customer_name', 150);
            $table->string('customer_email');
            $table->string('customer_phone', 30);
            // Something the customer wants the shop to know, such as another
            // delivery time.
            $table->string('customer_note', 500)->nullable();
            // The shop's own notes; never shown to the customer.
            $table->text('internal_notes')->nullable();

            // Set when the customer's details were wiped (see
            // AnonymizeStaleOrders); the order itself stays.
            $table->timestamp('anonymized_at')->nullable();

            $table->timestamp('terms_accepted_at');
            // When the stock held for an order is given up if it is still
            // unpaid. The payment window; the grace period comes after it.
            $table->timestamp('payment_expires_at');

            // Stamped when the order enters that status (OrderStatus::timestampColumn()).
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();

            // Finds the unpaid orders whose window has closed.
            $table->index(['status', 'payment_expires_at']);
            // The admin list: a status, newest first.
            $table->index(['status', 'created_at']);
            $table->index('created_at');
            $table->index('customer_email');
        });

        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_adds_up CHECK (total_incl_vat_cents = subtotal_excl_vat_cents + shipping_excl_vat_cents + vat_cents)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_shipping_vat_not_negative CHECK (shipping_incl_vat_cents >= shipping_excl_vat_cents)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
