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
        // Every message a provider sends about a payment, once. The unique
        // key is what makes a repeated message harmless: the second insert of
        // the same message fails, and the handler stops there. It is also the
        // trail for working out what happened to a payment.
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            // A hash of what makes the message the same message (which
            // fields depends on the provider), so a status change is a new
            // event and a repeat is not.
            $table->string('event_key', 64);

            // Empty when the message names an order we do not have.
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();

            $table->string('provider_status', 20);
            // What the handler did with it: filled in once it has.
            $table->string('outcome', 40)->nullable();
            // Only the fields the handler needs. Never the customer's bank
            // account, name or card details, which the provider also sends.
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
