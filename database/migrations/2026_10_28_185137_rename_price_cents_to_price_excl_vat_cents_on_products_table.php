<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The stored price was always the one without VAT (it is what the
     * catalog import supplies and what the admin types); the name now says
     * so. The price with VAT is worked out, never stored.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('price_cents', 'price_excl_vat_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('price_excl_vat_cents', 'price_cents');
        });
    }
};
