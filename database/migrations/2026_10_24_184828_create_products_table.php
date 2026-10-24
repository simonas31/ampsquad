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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('slug');
            $table->json('excerpt')->nullable();
            $table->json('description')->nullable();
            // Whole minor units (cents) of the shop's single currency, EUR.
            // Integers keep line totals exact and are what payment providers
            // expect, so no currency column or decimal math is needed yet.
            $table->unsignedInteger('price_cents');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            // Same generated-column approach as projects.slug_lt/slug_en —
            // gives the public route a real column to query by locale and a
            // unique index to enforce slugs at the DB layer.
            $table->string('slug_lt')->virtualAs('json_unquote(json_extract(`slug`, \'$."lt"\'))');
            $table->string('slug_en')->virtualAs('json_unquote(json_extract(`slug`, \'$."en"\'))');
            $table->unique('slug_lt');
            $table->unique('slug_en');

            // The shop listing: active products in admin-defined order.
            $table->index(['is_active', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
