<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\OrderStatus;
use App\Enums\PaymentOutcome;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\Checkout\OrderAdministration;
use App\Services\Checkout\OrderStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class OrderResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    private function orderPage(Order $order): Testable
    {
        return Livewire::test(ViewOrder::class, ['record' => $order->id]);
    }

    /**
     * An order as checkout leaves it, holding 3 of the product.
     *
     * @param  'awaitingPayment'|'paid'|'shipped'  $state
     */
    private function heldOrder(Product $product, string $state = 'paid'): Order
    {
        $order = Order::factory()->{$state}()->withLine($product, 3)->create();
        Product::takeStock($product->id, 3);
        $payment = Payment::factory()->for($order);
        ($state === 'awaitingPayment' ? $payment : $payment->succeeded())->create();

        return $order->refresh();
    }

    public function test_guests_cannot_reach_orders(): void
    {
        auth()->logout();
        $order = Order::factory()->create();

        $this->get('/admin/orders')->assertRedirect();
        $this->get("/admin/orders/{$order->id}")->assertRedirect();
    }

    public function test_orders_are_never_created_edited_or_deleted_from_the_panel(): void
    {
        $order = Order::factory()->paid()->create();

        $this->assertFalse(OrderResource::canCreate());
        $this->assertSame(['index', 'view'], array_keys(OrderResource::getPages()));
        foreach (['create', 'delete', 'deleteAny', 'restore', 'forceDelete', 'forceDeleteAny'] as $ability) {
            $this->assertFalse($this->admin->can($ability, $ability === 'create' || str_ends_with($ability, 'Any') ? Order::class : $order), $ability);
        }
        $this->assertTrue($this->admin->can('view', $order));
        $this->assertTrue($this->admin->can('update', $order));

        $this->orderPage($order)->assertActionDoesNotExist('delete')->assertActionDoesNotExist('edit');
        Livewire::test(ListOrders::class)->assertTableBulkActionDoesNotExist('delete');
    }

    public function test_the_list_shows_the_newest_orders_first_with_their_status_and_payment(): void
    {
        $old = Order::factory()->paid()->create(['created_at' => now()->subDays(3)]);
        $new = Order::factory()->expired()->create(['created_at' => now()->subDay()]);
        Payment::factory()->for($old)->succeeded()->create();
        Payment::factory()->for($new)->failed()->create();

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$new, $old], inOrder: true)
            ->assertTableColumnStateSet('status', OrderStatus::Paid, $old)
            ->assertTableColumnStateSet('latestPayment.status', PaymentStatus::Succeeded, $old)
            ->assertTableColumnStateSet('latestPayment.status', PaymentStatus::Failed, $new)
            ->assertTableColumnStateSet('number', $old->number, $old);
    }

    public function test_orders_are_found_by_number_name_email_and_phone(): void
    {
        $target = Order::factory()->create(['customer_name' => 'Petras Petraitis', 'customer_email' => 'petras@findme.test', 'customer_phone' => '+37069998877']);
        $other = Order::factory()->create(['customer_name' => 'Ona Onaitė', 'customer_email' => 'ona@else.test', 'customer_phone' => '+37061112233']);

        foreach ([$target->number, 'Petraitis', 'findme.test', '99988'] as $term) {
            Livewire::test(ListOrders::class)
                ->searchTable($term)
                ->assertCanSeeTableRecords([$target])
                ->assertCanNotSeeTableRecords([$other]);
        }
    }

    public function test_orders_are_filtered_by_status(): void
    {
        $paid = Order::factory()->paid()->create();
        $shipped = Order::factory()->shipped()->create();
        $cancelled = Order::factory()->cancelled()->create();

        Livewire::test(ListOrders::class)
            ->filterTable('status', [OrderStatus::Paid->value, OrderStatus::Shipped->value])
            ->assertCanSeeTableRecords([$paid, $shipped])
            ->assertCanNotSeeTableRecords([$cancelled]);
    }

    public function test_orders_are_filtered_by_the_day_they_were_placed(): void
    {
        $before = Order::factory()->create(['created_at' => '2026-03-09 23:59:59']);
        $first = Order::factory()->create(['created_at' => '2026-03-10 00:00:00']);
        $last = Order::factory()->create(['created_at' => '2026-03-12 23:59:59']);
        $after = Order::factory()->create(['created_at' => '2026-03-13 00:00:00']);

        Livewire::test(ListOrders::class)
            ->filterTable('created_at', ['from' => '2026-03-10', 'until' => '2026-03-12'])
            ->assertCanSeeTableRecords([$first, $last])
            ->assertCanNotSeeTableRecords([$before, $after])
            ->removeTableFilter('created_at')
            ->filterTable('created_at', ['from' => '2026-03-12', 'until' => null])
            ->assertCanSeeTableRecords([$last, $after])
            ->assertCanNotSeeTableRecords([$before, $first]);
    }

    public function test_the_date_filter_reads_and_shows_days_as_year_month_day_in_a_picker_of_its_own(): void
    {
        $filter = Livewire::test(ListOrders::class)->instance()->getTable()->getFilter('created_at');

        $pickers = collect($filter->getSchemaComponents())->keyBy(fn ($component): string => $component->getName());

        $this->assertSame(['from', 'until'], $pickers->keys()->all());
        foreach ($pickers as $picker) {
            $this->assertSame('Y-m-d', $picker->getDisplayFormat());
            $this->assertSame('Y-m-d', $picker->getFormat());
            $this->assertFalse($picker->isNative(), 'the browser\'s own picker would show the browser\'s date format');
        }
    }

    public function test_the_filters_have_room_to_be_read(): void
    {
        $table = Livewire::test(ListOrders::class)->instance()->getTable();

        $this->assertSame(['default' => 1, 'sm' => 2, 'xl' => 4], $table->getFiltersFormColumns());
        $this->assertSame(['default' => 1, 'sm' => 2], $table->getFilter('created_at')->getColumnSpan());
    }

    public function test_orders_are_filtered_by_payment_state(): void
    {
        $succeeded = Order::factory()->paid()->create();
        Payment::factory()->for($succeeded)->succeeded()->create();
        $refunded = Order::factory()->refunded()->create();
        Payment::factory()->for($refunded)->refunded()->create();
        $unpaid = Order::factory()->create();
        Payment::factory()->for($unpaid)->create();

        Livewire::test(ListOrders::class)
            ->filterTable('payment_status', PaymentStatus::Succeeded->value)
            ->assertCanSeeTableRecords([$succeeded])
            ->assertCanNotSeeTableRecords([$refunded, $unpaid])
            ->filterTable('payment_status', PaymentStatus::Refunded->value)
            ->assertCanSeeTableRecords([$refunded])
            ->assertCanNotSeeTableRecords([$succeeded, $unpaid]);
    }

    public function test_the_orders_that_need_a_person_can_be_listed(): void
    {
        $paidAfterExpiry = Order::factory()->expired()->create();
        Payment::factory()->for($paidAfterExpiry)->succeeded()->create();
        $cancelledNotRefunded = Order::factory()->cancelled()->create();
        Payment::factory()->for($cancelledNotRefunded)->succeeded()->create();
        $paidTwice = Order::factory()->paid()->create();
        Payment::factory()->for($paidTwice)->succeeded()->count(2)->create();

        $cancelledAndRefunded = Order::factory()->cancelled()->create();
        Payment::factory()->for($cancelledAndRefunded)->refunded()->create();
        $fine = Order::factory()->paid()->create();
        Payment::factory()->for($fine)->succeeded()->create();
        $expiredUnpaid = Order::factory()->expired()->create();
        Payment::factory()->for($expiredUnpaid)->failed()->create();

        Livewire::test(ListOrders::class)
            ->filterTable('needs_a_person', true)
            ->assertCanSeeTableRecords([$paidAfterExpiry, $cancelledNotRefunded, $paidTwice])
            ->assertCanNotSeeTableRecords([$cancelledAndRefunded, $fine, $expiredUnpaid]);

        foreach ([$paidAfterExpiry, $cancelledNotRefunded, $paidTwice] as $order) {
            $this->assertTrue($order->needsAPerson());
        }
        foreach ([$cancelledAndRefunded, $fine, $expiredUnpaid] as $order) {
            $this->assertFalse($order->needsAPerson());
        }
    }

    public function test_the_list_is_paged_and_never_offers_all(): void
    {
        Order::factory()->count(30)->create();

        $component = Livewire::test(ListOrders::class);

        $this->assertSame([10, 25, 50, 100], $component->instance()->getTable()->getPaginationPageOptions());
        $this->assertSame(25, $component->instance()->getTable()->getDefaultPaginationPageOption());
        $this->assertNotContains('all', $component->instance()->getTable()->getPaginationPageOptions());
        $this->assertCount(25, $component->instance()->getTableRecords());
    }

    public function test_the_navigation_badge_counts_paid_orders_waiting_to_be_shipped(): void
    {
        $this->assertNull(OrderResource::getNavigationBadge());

        Order::factory()->paid()->count(2)->create();
        Order::factory()->shipped()->create();
        Order::factory()->create();

        $this->assertSame('2', OrderResource::getNavigationBadge());
    }

    public function test_the_page_shows_the_order_as_it_was_sold(): void
    {
        $product = Product::factory()->priced(1000)->create(['name' => ['lt' => 'Maršrutizatorius', 'en' => 'Router'], 'sku' => 'RT-100']);
        $order = Order::factory()
            ->shippedBy(ShippingMethod::factory()->named('DPD')->priced(450)->create())
            ->withLine($product, 2)
            ->paid()
            ->create([
                'customer_name' => 'Jonas Jonaitis',
                'customer_email' => 'jonas@example.test',
                'customer_phone' => '+37060000000',
                'delivery_address' => 'Gedimino pr. 1, Vilnius',
                'customer_note' => 'Please call first.',
                'internal_notes' => 'Regular customer.',
            ]);
        Payment::factory()->for($order)->succeeded()->create(['provider_reference' => 'T123456', 'bank' => 'swedbank']);
        PaymentEvent::factory()->processed(PaymentOutcome::Paid->value)->create(['order_id' => $order->id]);
        $product->update(['name' => ['lt' => 'Pervadintas', 'en' => 'Renamed'], 'price_excl_vat_cents' => 9999]);

        $this->orderPage($order)
            ->assertSuccessful()
            ->assertSee($order->number)
            ->assertSee('Jonas Jonaitis')
            ->assertSee('jonas@example.test')
            ->assertSee('+37060000000')
            ->assertSee('Gedimino pr. 1, Vilnius')
            ->assertSee('Please call first.')
            ->assertSee('Regular customer.')
            ->assertSee('Maršrutizatorius')
            ->assertDontSee('Pervadintas')
            ->assertSee('RT-100')
            ->assertSee('DPD')
            ->assertSee('T123456')
            ->assertSee('swedbank')
            ->assertSee(PaymentOutcome::Paid->getLabel())
            ->assertSee(OrderStatus::Paid->getLabel());
    }

    public function test_an_order_whose_details_were_wiped_says_so(): void
    {
        $order = Order::factory()->expired()->create(['customer_name' => '', 'customer_email' => '', 'customer_phone' => '', 'anonymized_at' => now()]);

        $this->orderPage($order)->assertSuccessful()->assertSee(__('admin.orders.sections.customer'));
    }

    public function test_money_that_an_order_cannot_keep_is_called_out(): void
    {
        $order = Order::factory()->cancelled()->create();
        Payment::factory()->for($order)->succeeded()->create();
        $fine = Order::factory()->paid()->create();
        Payment::factory()->for($fine)->succeeded()->create();

        $this->orderPage($order)->assertSee(__('admin.orders.attention.text'));
        $this->orderPage($fine)->assertDontSee(__('admin.orders.attention.text'));
    }

    public function test_the_actions_are_there_only_when_they_make_sense(): void
    {
        $product = Product::factory()->inStock()->create();
        $waiting = $this->heldOrder($product, 'awaitingPayment');
        $paid = $this->heldOrder($product, 'paid');
        $shipped = $this->heldOrder($product, 'shipped');
        $cancelled = Order::factory()->cancelled()->create();

        $this->orderPage($waiting)->assertActionHidden('ship')->assertActionVisible('cancel')->assertActionHidden('refund')->assertActionHidden('paymentRefunded')->assertActionVisible('editNotes');
        $this->orderPage($paid)->assertActionVisible('ship')->assertActionVisible('cancel')->assertActionHidden('refund')->assertActionHidden('paymentRefunded');
        $this->orderPage($shipped)->assertActionHidden('ship')->assertActionHidden('cancel')->assertActionVisible('refund')->assertActionHidden('paymentRefunded');
        $this->orderPage($cancelled)->assertActionHidden('ship')->assertActionHidden('cancel')->assertActionHidden('refund')->assertActionHidden('paymentRefunded')->assertActionVisible('editNotes');
    }

    public function test_an_order_is_marked_shipped(): void
    {
        $product = Product::factory()->inStock(10)->create();
        $order = $this->heldOrder($product);

        $this->orderPage($order)->callAction('ship')->assertNotified(__('admin.orders.notifications.shipped'));

        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->shipped_at);
    }

    public function test_a_paid_order_is_cancelled_with_or_without_its_goods_going_back(): void
    {
        $product = Product::factory()->inStock(10)->create();
        $restocked = $this->heldOrder($product);
        $kept = $this->heldOrder($product);
        $this->assertSame(4, $product->fresh()->stock_quantity);

        $this->orderPage($restocked)->callAction('cancel', ['restock' => true])->assertNotified(__('admin.orders.notifications.cancelled'));
        $this->assertSame(7, $product->fresh()->stock_quantity);

        $this->orderPage($kept)->callAction('cancel', ['restock' => false]);
        $this->assertSame(7, $product->fresh()->stock_quantity);

        $this->assertSame(OrderStatus::Cancelled, $restocked->fresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $kept->fresh()->status);
        $this->assertSame(PaymentStatus::Succeeded, $restocked->payments()->sole()->status);
    }

    public function test_a_waiting_order_is_cancelled_and_its_stock_always_goes_back(): void
    {
        $product = Product::factory()->inStock(10)->create();
        $order = $this->heldOrder($product, 'awaitingPayment');

        $this->orderPage($order)->callAction('cancel');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(PaymentStatus::Failed, $order->payments()->sole()->status);
    }

    public function test_a_shipped_order_is_refunded(): void
    {
        $product = Product::factory()->inStock(10)->create();
        $returned = $this->heldOrder($product, 'shipped');
        $damaged = $this->heldOrder($product, 'shipped');
        $this->assertSame(4, $product->fresh()->stock_quantity);

        $this->orderPage($returned)->callAction('refund', ['restock' => true])->assertNotified(__('admin.orders.notifications.refunded'));
        $this->orderPage($damaged)->callAction('refund', ['restock' => false]);

        $this->assertSame(7, $product->fresh()->stock_quantity);
        foreach ([$returned, $damaged] as $order) {
            $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
            $this->assertSame(PaymentStatus::Refunded, $order->payments()->sole()->status);
        }
    }

    public function test_the_refund_does_not_put_goods_back_unless_asked(): void
    {
        $product = Product::factory()->inStock(10)->create();
        $order = $this->heldOrder($product, 'shipped');

        $this->orderPage($order)->mountAction('refund')->assertActionDataSet(['restock' => false]);
    }

    public function test_money_taken_for_a_cancelled_order_is_recorded_as_refunded(): void
    {
        $product = Product::factory()->inStock(10)->create();
        $order = $this->heldOrder($product);
        app(OrderAdministration::class)->cancel($order, restock: true);
        $payment = $order->payments()->sole();

        $this->orderPage($order->fresh())
            ->assertActionVisible('paymentRefunded')
            ->callAction('paymentRefunded', ['payment' => $payment->id])
            ->assertNotified(__('admin.orders.notifications.payment_refunded'));

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertFalse($order->fresh()->needsAPerson());
        $this->orderPage($order->fresh())->assertActionHidden('paymentRefunded');
    }

    public function test_an_extra_payment_is_recorded_as_refunded_without_touching_the_paid_order(): void
    {
        $order = Order::factory()->paid()->create();
        $first = Payment::factory()->for($order)->succeeded()->create(['provider_payment_token' => 'tok-1']);
        $extra = Payment::factory()->for($order)->succeeded()->create(['provider_payment_token' => 'tok-2']);

        $this->orderPage($order)
            ->assertActionVisible('paymentRefunded')
            ->callAction('paymentRefunded', ['payment' => $extra->id]);

        $this->assertSame(PaymentStatus::Refunded, $extra->fresh()->status);
        $this->assertSame(PaymentStatus::Succeeded, $first->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_the_payment_to_refund_must_be_one_of_the_orders_succeeded_payments(): void
    {
        $order = Order::factory()->cancelled()->create();
        Payment::factory()->for($order)->succeeded()->create();
        $elsewhere = Payment::factory()->succeeded()->create();

        $this->orderPage($order)
            ->callAction('paymentRefunded', ['payment' => $elsewhere->id])
            ->assertHasActionErrors(['payment']);

        $this->assertSame(PaymentStatus::Succeeded, $elsewhere->fresh()->status);
    }

    public function test_an_action_is_gone_once_another_admin_has_moved_the_order(): void
    {
        $product = Product::factory()->inStock(10)->create();
        $order = $this->heldOrder($product);
        $page = $this->orderPage($order);

        // Another admin cancels it first. The page's next request sees that.
        app(OrderAdministration::class)->cancel(Order::query()->findOrFail($order->id), restock: true);
        $this->assertSame(10, $product->fresh()->stock_quantity);

        // The next request the page makes reads the order afresh, and the buttons are gone.
        $page->call('$refresh')->assertActionHidden('cancel')->assertActionHidden('ship');

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertNull($order->fresh()->shipped_at);
    }

    public function test_a_click_that_loses_the_race_inside_the_transaction_says_so_and_changes_nothing(): void
    {
        $product = Product::factory()->inStock(10)->create();
        $order = $this->heldOrder($product);

        // The order moves between the page deciding the action is allowed and the transaction.
        $this->app->instance(OrderAdministration::class, new class(app(OrderStock::class)) extends OrderAdministration
        {
            public function markShipped(Order $order): bool
            {
                return false;
            }

            public function cancel(Order $order, bool $restock): bool
            {
                return false;
            }
        });

        $this->orderPage($order)->callAction('ship')->assertNotified(__('admin.orders.notifications.changed_meanwhile'));
        $this->orderPage($order)->callAction('cancel', ['restock' => true])->assertNotified(__('admin.orders.notifications.changed_meanwhile'));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(7, $product->fresh()->stock_quantity);
    }

    public function test_internal_notes_are_saved_without_changing_anything_else(): void
    {
        $order = Order::factory()->paid()->create(['internal_notes' => 'First.']);
        $paidAt = $order->paid_at;

        $this->orderPage($order)
            ->mountAction('editNotes')
            ->assertActionDataSet(['internal_notes' => 'First.'])
            ->callMountedAction()
            ->callAction('editNotes', ['internal_notes' => 'Called the customer, delivery on Friday.'])
            ->assertNotified(__('admin.orders.notifications.notes_saved'));

        $order->refresh();
        $this->assertSame('Called the customer, delivery on Friday.', $order->internal_notes);
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertEquals($paidAt, $order->paid_at);

        $this->orderPage($order)->callAction('editNotes', ['internal_notes' => '']);
        $this->assertNull($order->fresh()->internal_notes);
    }

    public function test_notes_have_a_length_limit(): void
    {
        $order = Order::factory()->create();

        $this->orderPage($order)
            ->callAction('editNotes', ['internal_notes' => str_repeat('x', 5001)])
            ->assertHasActionErrors(['internal_notes']);

        $this->assertNull($order->fresh()->internal_notes);
    }

    public function test_the_list_stays_cheap_with_thousands_of_orders(): void
    {
        $this->insertOrders(5000);

        $queries = [];
        foreach ([10, 100] as $perPage) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $component = Livewire::test(ListOrders::class)->set('tableRecordsPerPage', $perPage);
            $records = $component->instance()->getTableRecords();
            $queries[$perPage] = count(DB::getQueryLog());
            DB::disableQueryLog();

            $this->assertCount($perPage, $records);
        }

        // However many rows a page holds, it costs the same few queries: no N+1.
        $this->assertSame($queries[10], $queries[100], json_encode($queries));
        $this->assertLessThanOrEqual(12, $queries[100]);

        Livewire::test(ListOrders::class)
            ->filterTable('status', [OrderStatus::Paid->value])
            ->assertCanSeeTableRecords(Order::query()->where('status', OrderStatus::Paid)->latest()->limit(25)->get());
    }

    public function test_the_status_filter_reads_through_its_index_and_the_date_sort_has_one(): void
    {
        $this->insertOrders(5000);

        // At a few thousand rows MySQL may sort an unfiltered list instead of walking
        // created_at, which costs milliseconds; the index is there for when it matters.
        $this->assertTrue(Schema::hasIndex('orders', ['created_at']));

        $byStatus = DB::select("explain select * from orders where status = 'paid' order by created_at desc limit 25")[0];
        $this->assertSame('orders_status_created_at_index', $byStatus->key);
        $this->assertStringNotContainsString('filesort', $byStatus->Extra);
    }

    /**
     * Orders in bulk, cheap to make: rows only, spread over a year and the
     * statuses, each with amounts that add up.
     */
    private function insertOrders(int $count): void
    {
        $statuses = array_map(fn (OrderStatus $status): string => $status->value, OrderStatus::cases());
        $now = now();

        collect(range(1, $count))->chunk(500)->each(function ($chunk) use ($statuses, $now): void {
            DB::table('orders')->insert($chunk->map(fn (int $i): array => [
                'uuid' => (string) Str::uuid(),
                'number' => sprintf('AS-%08d', $i),
                'status' => $statuses[$i % count($statuses)],
                'locale' => 'lt',
                'currency' => 'EUR',
                'vat_rate_percent' => 21,
                'subtotal_excl_vat_cents' => 1000,
                'shipping_excl_vat_cents' => 0,
                'shipping_incl_vat_cents' => 0,
                'vat_cents' => 210,
                'total_incl_vat_cents' => 1210,
                'shipping_label' => 'Pickup',
                'shipping_is_pickup' => true,
                'customer_name' => "Customer {$i}",
                'customer_email' => "customer{$i}@example.test",
                'customer_phone' => '+37060000000',
                'terms_accepted_at' => $now,
                'payment_expires_at' => $now,
                'created_at' => $now->copy()->subMinutes($i * 90),
                'updated_at' => $now,
            ])->all());
        });
    }
}
