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
        Schema::table('customers', function (Blueprint $table) {
            // The address checkout offers first. One column, not a flag on
            // each address, so the database itself allows only one default per
            // customer. That the address is the customer's own is for
            // CustomerAddressBook to see to. It is emptied when the address
            // is deleted.
            $table->foreignId('default_shipping_address_id')
                ->nullable()
                ->after('marketing_consent_at')
                ->constrained('customer_addresses')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_shipping_address_id');
        });
    }
};
