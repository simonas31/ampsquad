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
        // The shop's own tree, built by the catalog import. Kept apart from
        // `categories`, which classifies portfolio projects.
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->json('name');
            $table->json('slug');
            $table->timestamps();

            // Same generated-column approach as products.slug_lt/slug_en: the
            // shop filters by slug, and the unique index enforces slugs at
            // the DB layer.
            $table->string('slug_lt')->virtualAs('json_unquote(json_extract(`slug`, \'$."lt"\'))');
            $table->string('slug_en')->virtualAs('json_unquote(json_extract(`slug`, \'$."en"\'))');
            $table->unique('slug_lt');
            $table->unique('slug_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
