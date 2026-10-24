<?php

declare(strict_types=1);

use App\Models\Cart;
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:generate-sitemap')->daily();
Schedule::command('model:prune', ['--model' => [Cart::class]])->daily();
