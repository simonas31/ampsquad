<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('show_in_header')->default(false)->after('blocks');
            $table->boolean('show_in_footer')->default(false)->after('show_in_header');
        });

        // The About page is already linked from both menus by the fixed
        // navigation, so its flags start out reflecting that.
        DB::table('pages')->where('key', 'about')->update([
            'show_in_header' => true,
            'show_in_footer' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['show_in_header', 'show_in_footer']);
        });
    }
};
