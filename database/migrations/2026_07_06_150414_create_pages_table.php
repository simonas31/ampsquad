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
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            // Stable, non-translatable identifier code/routes reference
            // (e.g. "about", "privacy-policy") — not the URL segment, which
            // is the translatable `slug` below and can differ per locale.
            $table->string('key')->unique();
            $table->json('title');
            $table->json('slug');
            $table->json('blocks')->nullable();
            $table->timestamps();

            // Same generated-column approach as projects.slug_lt/slug_en —
            // gives the public route a real column to query by locale.
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
        Schema::dropIfExists('pages');
    }
};
