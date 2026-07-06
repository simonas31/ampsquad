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
        // Structure only — deliberately no pricing/formula columns. The
        // calculator's actual business logic (how options translate into a
        // price) hasn't been scoped yet; this just gives admins a place to
        // define the categories/options that logic will eventually price.
        Schema::create('calculator_categories', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('icon')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calculator_categories');
    }
};
