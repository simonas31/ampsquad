<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Orders as the shop works them: found, read, shipped, cancelled, refunded
 * and annotated. They are placed by checkout and never created, edited or
 * deleted here (see OrderPolicy); what can change is done through the actions
 * of the view page, which go through OrderAdministration.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'number';

    public static function getModelLabel(): string
    {
        return __('admin.orders.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.orders.plural_label');
    }

    /**
     * How many paid orders are waiting to be shipped. One indexed count per
     * admin page.
     */
    public static function getNavigationBadge(): ?string
    {
        $toShip = Order::query()->where('status', OrderStatus::Paid)->count();

        return $toShip > 0 ? (string) $toShip : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('admin.orders.to_ship');
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
