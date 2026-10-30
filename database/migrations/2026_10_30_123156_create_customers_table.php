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
        // The shop's customers, kept apart from `users` (the people who work
        // in the admin panel) so that nobody who registers in the shop can
        // ever be let into it.
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->string('full_name', 150);
            // Null once the account is deleted: the address moves to
            // retained_email, which is not unique, so the person can register
            // again at once. The unique index allows any number of nulls.
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();

            $table->string('phone', 30)->nullable();
            // The language of the customer's emails.
            $table->char('locale', 2)->default('lt');
            // When they agreed to marketing emails; null means they have not.
            $table->timestamp('marketing_consent_at')->nullable();

            // A new address waiting for its link to be followed. It only
            // replaces `email` when it is.
            $table->string('pending_email')->nullable();
            // The address of a deleted account, kept with the rest of its
            // data until the retention period ends (see Customer::prunable()).
            $table->string('retained_email')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Finds the accounts that were never verified.
            $table->index(['email_verified_at', 'created_at']);
            // Finds the deleted accounts whose retention period is over.
            $table->index('deleted_at');
        });

        // The password broker's own table: the admin's tokens are keyed by
        // email too, and a customer and an admin may share an address.
        Schema::create('customer_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_password_reset_tokens');
        Schema::dropIfExists('customers');
    }
};
