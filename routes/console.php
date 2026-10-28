<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CatalogImport;
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:generate-sitemap')->daily();
Schedule::command('model:prune', ['--model' => [Cart::class, CatalogImport::class]])->daily();

// Gives up unpaid orders once their payment window and grace period are over.
// Every minute, so stock is held no longer than it has to be. The overlap lock
// lasts 10 minutes, not the default day, so a run that died does not stop it.
Schedule::command('app:expire-unpaid-orders')->everyMinute()->withoutOverlapping(10);
Schedule::command('app:anonymize-stale-orders')->daily();
