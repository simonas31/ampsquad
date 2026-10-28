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
        // One payment started with a provider for an order. Nothing here is
        // specific to a provider: its own words are mapped onto these columns
        // by its gateway, and what it sent is kept in payment_events.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Restrict, not cascade: orders and their payments are never deleted.
            $table->foreignId('order_id')->constrained()->restrictOnDelete();

            $table->string('provider', 32);
            // The provider's id for this payment, known once it has been started.
            $table->string('provider_reference', 64)->nullable();
            // The provider's id for the money that was actually taken, known
            // only once it has been. A provider can take money more than once
            // for one payment, and that must not pass as the same payment.
            $table->string('provider_payment_token', 100)->nullable();

            $table->string('status', 20)->default('pending');
            // What was asked for, and what the customer actually paid. The
            // order is only paid when the two agree with its total.
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('paid_amount_cents')->nullable();
            $table->char('currency', 3)->default('EUR');
            // How the customer paid, as far as the provider says.
            $table->string('channel', 30)->nullable();
            $table->string('bank', 30)->nullable();
            // Where the customer pays, kept so an unfinished payment can be
            // picked up again while it is still open.
            $table->string('redirect_url', 500)->nullable();

            $table->timestamp('paid_at')->nullable();
            // Set by hand once the shop has paid the money back.
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            // NULLs do not collide, so payments not yet started or paid are fine.
            $table->unique(['provider', 'provider_reference']);
            $table->unique(['provider', 'provider_payment_token']);
            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
