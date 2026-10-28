<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Checkout\UnpaidOrderExpirer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:expire-unpaid-orders')]
#[Description('Give up orders that were not paid in time and put their stock back.')]
class ExpireUnpaidOrders extends Command
{
    public function handle(UnpaidOrderExpirer $expirer): void
    {
        $this->info("Gave up {$expirer->expire()} unpaid order(s).");
    }
}
