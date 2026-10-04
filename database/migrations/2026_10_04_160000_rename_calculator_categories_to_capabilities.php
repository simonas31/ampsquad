<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * These tables never drove the price calculator (that runs client-side);
 * they feed the "capabilities" section on the home page. Renamed to say so,
 * keeping every row.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('calculator_categories', 'capabilities');
        Schema::rename('calculator_options', 'capability_options');

        Schema::table('capability_options', function (Blueprint $table) {
            $table->dropForeign('calculator_options_calculator_category_id_foreign');
            $table->dropIndex('calculator_options_calculator_category_id_foreign');
        });

        Schema::table('capability_options', function (Blueprint $table) {
            $table->renameColumn('calculator_category_id', 'capability_id');
        });

        Schema::table('capability_options', function (Blueprint $table) {
            $table->foreign('capability_id')->references('id')->on('capabilities')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capability_options', function (Blueprint $table) {
            $table->dropForeign('capability_options_capability_id_foreign');
            $table->dropIndex('capability_options_capability_id_foreign');
        });

        Schema::table('capability_options', function (Blueprint $table) {
            $table->renameColumn('capability_id', 'calculator_category_id');
        });

        Schema::rename('capability_options', 'calculator_options');
        Schema::rename('capabilities', 'calculator_categories');

        Schema::table('calculator_options', function (Blueprint $table) {
            $table->foreign('calculator_category_id')->references('id')->on('calculator_categories')->cascadeOnDelete();
        });
    }
};
