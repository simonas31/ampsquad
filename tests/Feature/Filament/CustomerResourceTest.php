<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    public function test_the_customers_are_listed_newest_first_without_the_closed_accounts(): void
    {
        $older = Customer::factory()->create(['created_at' => now()->subDays(2)]);
        $newer = Customer::factory()->create(['created_at' => now()->subDay()]);
        $closed = Customer::factory()->deleted()->create();

        Livewire::test(ListCustomers::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
            ->assertCanNotSeeTableRecords([$closed]);
    }

    public function test_the_closed_accounts_are_found_under_their_filter(): void
    {
        $open = Customer::factory()->create();
        $closed = Customer::factory()->deleted()->create();

        Livewire::test(ListCustomers::class)
            ->filterTable('trashed', false)
            ->assertCanSeeTableRecords([$closed])
            ->assertCanNotSeeTableRecords([$open])
            ->filterTable('trashed', true)
            ->assertCanSeeTableRecords([$open, $closed]);
    }

    public function test_a_customer_is_found_by_name_email_or_phone_and_a_closed_one_by_the_address_it_kept(): void
    {
        $jonas = Customer::factory()->create(['full_name' => 'Jonas Jonaitis', 'email' => 'jonas@example.com', 'phone' => '+37061234567']);
        $ona = Customer::factory()->create(['full_name' => 'Ona Onaitė']);
        $closed = Customer::factory()->deleted()->create(['full_name' => 'Petras Petraitis']);

        Livewire::test(ListCustomers::class)
            ->searchTable('Jonaitis')->assertCanSeeTableRecords([$jonas])->assertCanNotSeeTableRecords([$ona])
            ->searchTable('jonas@example.com')->assertCanSeeTableRecords([$jonas])->assertCanNotSeeTableRecords([$ona])
            ->searchTable('61234567')->assertCanSeeTableRecords([$jonas])
            ->filterTable('trashed', true)
            ->searchTable($closed->retained_email)->assertCanSeeTableRecords([$closed])->assertCanNotSeeTableRecords([$jonas, $ona]);
    }

    public function test_the_filters_narrow_the_list(): void
    {
        $verified = Customer::factory()->withMarketingConsent()->create();
        $unverified = Customer::factory()->unverified()->inLocale('en')->create();

        Livewire::test(ListCustomers::class)
            ->filterTable('verified', true)->assertCanSeeTableRecords([$verified])->assertCanNotSeeTableRecords([$unverified])
            ->filterTable('verified', false)->assertCanSeeTableRecords([$unverified])->assertCanNotSeeTableRecords([$verified])
            ->removeTableFilter('verified')
            ->filterTable('marketing', true)->assertCanSeeTableRecords([$verified])->assertCanNotSeeTableRecords([$unverified])
            ->removeTableFilter('marketing')
            ->filterTable('locale', 'en')->assertCanSeeTableRecords([$unverified])->assertCanNotSeeTableRecords([$verified]);
    }

    public function test_each_row_counts_the_customers_orders(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->count(3)->placedBy($customer)->create();
        Order::factory()->create();

        Livewire::test(ListCustomers::class)
            ->assertCanSeeTableRecords([$customer])
            ->assertTableColumnStateSet('orders_count', 3, $customer);
    }

    public function test_a_customer_page_shows_the_account_and_no_secrets(): void
    {
        $customer = Customer::factory()->withMarketingConsent()->create([
            'full_name' => 'Jonas Jonaitis',
            'email' => 'jonas@example.com',
            'phone' => '+37061234567',
        ]);

        Livewire::test(ViewCustomer::class, ['record' => $customer->getKey()])
            ->assertSuccessful()
            ->assertSee('Jonas Jonaitis')
            ->assertSee('jonas@example.com')
            ->assertSee('+37061234567')
            ->assertDontSee($customer->password)
            ->assertDontSee('remember_token');
    }

    public function test_an_unverified_customer_and_one_without_consent_say_so(): void
    {
        $customer = Customer::factory()->unverified()->create();

        Livewire::test(ViewCustomer::class, ['record' => $customer->getKey()])
            ->assertSee(__('admin.customers.not_verified'))
            ->assertSee(__('admin.customers.no_consent'));
    }

    public function test_a_closed_account_can_be_opened_and_says_when_its_data_goes(): void
    {
        $closedAt = now()->subMonths(3);
        $customer = Customer::factory()->deleted($closedAt)->create(['full_name' => 'Petras Petraitis']);

        $page = Livewire::test(ViewCustomer::class, ['record' => $customer->getKey()])
            ->assertSuccessful()
            ->assertSee('Petras Petraitis')
            ->assertSee($customer->retained_email)
            ->assertSee(__('admin.customers.sections.closed'));

        $page->assertSee($closedAt->copy()->addYears(Customer::RETENTION_YEARS)->isoFormat('LL'));
    }

    public function test_an_open_account_does_not_show_the_closed_notice(): void
    {
        $customer = Customer::factory()->create();

        Livewire::test(ViewCustomer::class, ['record' => $customer->getKey()])
            ->assertDontSee(__('admin.customers.sections.closed'));
    }

    public function test_the_page_lists_only_the_customers_own_orders(): void
    {
        $customer = Customer::factory()->create();
        $mine = Order::factory()->count(2)->placedBy($customer)->create();
        $other = Order::factory()->create();

        Livewire::test(OrdersRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
            ->assertSuccessful()
            ->assertCanSeeTableRecords($mine)
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_an_order_of_an_account_links_to_it_and_a_guest_order_does_not(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Jonas Jonaitis']);
        $order = Order::factory()->placedBy($customer)->create();
        $guestOrder = Order::factory()->create();

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertSee(__('admin.orders.fields.account'))
            ->assertSee(CustomerResource::getUrl('view', ['record' => $customer->getKey()]));

        Livewire::test(ViewOrder::class, ['record' => $guestOrder->getKey()])
            ->assertDontSee(__('admin.orders.fields.account'));
    }

    public function test_an_order_of_a_closed_account_still_links_to_it(): void
    {
        $customer = Customer::factory()->deleted()->create(['full_name' => 'Petras Petraitis']);
        $order = Order::factory()->placedBy($customer)->create();

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertSee('Petras Petraitis')
            ->assertSee(CustomerResource::getUrl('view', ['record' => $customer->getKey()]));
    }

    public function test_nothing_about_a_customer_can_be_created_changed_or_deleted_from_the_panel(): void
    {
        $customer = Customer::factory()->create();

        $this->assertFalse(CustomerResource::canCreate());
        $this->assertFalse(CustomerResource::canEdit($customer));
        $this->assertFalse(CustomerResource::canDelete($customer));
        $this->assertFalse(CustomerResource::canForceDelete($customer));
        $this->assertTrue(CustomerResource::canViewAny());
        $this->assertTrue(CustomerResource::canView($customer));
        $this->assertSame(['index', 'view'], array_keys(CustomerResource::getPages()));
    }

    public function test_the_policy_lets_the_panel_look_and_nothing_more(): void
    {
        $customer = Customer::factory()->create();

        $this->assertTrue($this->admin->can('viewAny', Customer::class));
        $this->assertTrue($this->admin->can('view', $customer));

        foreach (['create' => Customer::class, 'update' => $customer, 'delete' => $customer, 'restore' => $customer, 'forceDelete' => $customer, 'deleteAny' => Customer::class] as $ability => $subject) {
            $this->assertFalse($this->admin->can($ability, $subject), "{$ability} is allowed");
        }
    }

    public function test_the_customers_pages_are_in_the_panel_and_closed_to_everyone_else(): void
    {
        $this->get(CustomerResource::getUrl('index'))->assertOk();
        $this->get(CustomerResource::getUrl('view', ['record' => Customer::factory()->create()->getKey()]))->assertOk();

        auth()->guard('web')->logout();
        $this->flushSession();

        $this->get(CustomerResource::getUrl('index'))->assertRedirect(route('filament.admin.auth.login'));

        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->get(CustomerResource::getUrl('index'))->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_the_list_costs_the_same_queries_however_many_customers_it_holds(): void
    {
        Customer::factory()->count(2)->create();
        $small = $this->queriesOf(fn () => Livewire::test(ListCustomers::class));

        Customer::factory()->count(20)->create();
        $big = $this->queriesOf(fn () => Livewire::test(ListCustomers::class));

        $this->assertSame($small, $big);
    }

    private function queriesOf(callable $render): int
    {
        $render();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $render();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
