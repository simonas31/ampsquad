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
        Schema::table('orders', function (Blueprint $table) {
            // Who the order belongs to when it was placed by a logged-in,
            // verified customer, or linked to one afterwards on their
            // request. A guest's order has none. Everything about the
            // customer is still copied onto the order, so it never depends
            // on this row; the column only lets a customer find their orders.
            // Set null, not cascade: orders are never deleted.
            $table->unsignedBigInteger('customer_id')->nullable()->after('number');

            // The customer's order history, newest first. Declared before the
            // foreign key so that the key uses it and adds no index of its own.
            $table->index(['customer_id', 'created_at']);

            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['customer_id', 'created_at']);
            $table->dropColumn('customer_id');
        });
    }
};
