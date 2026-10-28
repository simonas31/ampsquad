<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnonymizeStaleOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function oldOrder(string $state): Order
    {
        return Order::factory()->{$state}()->create([
            'created_at' => now()->subDays(Order::UNPAID_RETENTION_DAYS + 1),
            'customer_name' => 'Jonas Jonaitis',
            'customer_email' => 'jonas@example.test',
            'customer_phone' => '+37060000000',
            'delivery_address' => 'Gedimino pr. 1',
            'customer_note' => 'A note',
            'internal_notes' => 'Another',
        ]);
    }

    public function test_an_old_order_nobody_paid_loses_its_customers_details_and_keeps_the_rest(): void
    {
        $expired = $this->oldOrder('expired');
        $cancelled = $this->oldOrder('cancelled');
        $number = $expired->number;
        $total = $expired->total_incl_vat_cents;
        $lines = $expired->lines()->count();

        $this->artisan('app:anonymize-stale-orders')->expectsOutputToContain('Wiped the customer details of 2')->assertSuccessful();

        foreach ([$expired, $cancelled] as $order) {
            $order->refresh();
            $this->assertSame('', $order->customer_name);
            $this->assertSame('', $order->customer_email);
            $this->assertSame('', $order->customer_phone);
            $this->assertNull($order->delivery_address);
            $this->assertNull($order->customer_note);
            $this->assertNull($order->internal_notes);
            $this->assertNotNull($order->anonymized_at);
        }

        $expired->refresh();
        $this->assertSame($number, $expired->number);
        $this->assertSame($total, $expired->total_incl_vat_cents);
        $this->assertSame($lines, $expired->lines()->count());
    }

    public function test_an_order_that_was_ever_paid_is_never_wiped(): void
    {
        $paid = $this->oldOrder('paid');
        $paidThenCancelled = $this->oldOrder('cancelled');
        Payment::factory()->for($paidThenCancelled)->succeeded()->create();
        $paidAfterExpiry = $this->oldOrder('expired');
        Payment::factory()->for($paidAfterExpiry)->succeeded()->create();
        $shipped = $this->oldOrder('shipped');
        $refunded = $this->oldOrder('refunded');

        $this->artisan('app:anonymize-stale-orders')->assertSuccessful();

        foreach ([$paid, $paidThenCancelled, $paidAfterExpiry, $shipped, $refunded] as $order) {
            $this->assertSame('Jonas Jonaitis', $order->fresh()->customer_name);
            $this->assertNull($order->fresh()->anonymized_at);
        }
    }

    public function test_a_recent_or_still_waiting_order_is_not_wiped(): void
    {
        $recent = Order::factory()->expired()->create(['customer_name' => 'Jonas Jonaitis']);
        $waiting = $this->oldOrder('awaitingPayment');

        $this->artisan('app:anonymize-stale-orders')->expectsOutputToContain('Wiped the customer details of 0')->assertSuccessful();

        $this->assertSame('Jonas Jonaitis', $recent->fresh()->customer_name);
        $this->assertSame('Jonas Jonaitis', $waiting->fresh()->customer_name);
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $order = $this->oldOrder('expired');
        $this->artisan('app:anonymize-stale-orders')->assertSuccessful();
        $wipedAt = $order->fresh()->anonymized_at;

        $this->travel(2)->days();
        $this->artisan('app:anonymize-stale-orders')->expectsOutputToContain('Wiped the customer details of 0')->assertSuccessful();

        $this->assertEquals($wipedAt, $order->fresh()->anonymized_at);
    }
}
