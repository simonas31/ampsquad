<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Orders that were never paid hold a stranger's name, email, phone and
 * address for no reason once the question of the sale is closed. After
 * Order::UNPAID_RETENTION_DAYS they are wiped, and the order itself (its
 * number, lines and amounts) stays. An order that was ever paid, even if it
 * ended cancelled, is an accounting record and is never touched here.
 */
#[Signature('app:anonymize-stale-orders')]
#[Description('Wipe the customer details of old orders that were never paid.')]
class AnonymizeStaleOrders extends Command
{
    public function handle(): void
    {
        $count = Order::query()
            ->whereIn('status', [OrderStatus::Expired, OrderStatus::Cancelled])
            ->whereNull('anonymized_at')
            ->where('created_at', '<', now()->subDays(Order::UNPAID_RETENTION_DAYS))
            ->whereDoesntHave('payments', fn ($payments) => $payments->where('status', PaymentStatus::Succeeded))
            ->update([
                'customer_name' => '',
                'customer_email' => '',
                'customer_phone' => '',
                'delivery_address' => null,
                'customer_note' => null,
                'internal_notes' => null,
                'anonymized_at' => now(),
            ]);

        $this->info("Wiped the customer details of {$count} unpaid order(s).");
    }
}
