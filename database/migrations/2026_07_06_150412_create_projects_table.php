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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('title');
            $table->json('slug');
            $table->json('excerpt')->nullable();
            // Plain JSON, deliberately not spatie/laravel-translatable-cast
            // — see HasTranslatableBlocks. Each block's own text fields
            // carry both locales directly, so the block structure itself
            // (count/order/type) stays identical across languages.
            $table->json('blocks')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('order')->default(0);
            // Project-only fields, kept directly on the table rather than a
            // separate project_details table — just 3 nullable columns,
            // doesn't earn the extra join.
            $table->json('location')->nullable();
            $table->string('client_name')->nullable();
            $table->date('completed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Generated virtual columns pull the locale-specific slug out
            // of the translatable JSON so a real unique index can enforce
            // this at the DB layer instead of only at the app layer, which
            // is the usual limitation of JSON-column slugs.
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
        Schema::dropIfExists('projects');
    }
};
