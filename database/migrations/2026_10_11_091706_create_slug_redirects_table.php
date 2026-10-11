<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per retired slug of a live record, so the old URL can
        // 301 to the record's current URL instead of 404ing in search engines.
        Schema::create('slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->morphs('redirectable');
            $table->string('locale', 8);
            $table->string('old_slug');
            $table->timestamps();

            $table->unique(['redirectable_type', 'locale', 'old_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');
    }
};
