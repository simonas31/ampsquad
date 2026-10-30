<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The orders in a customer's account, newest first, each a way into the
 * order itself. Only for reading: the orders are worked from their own pages.
 */
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.orders.plural_label');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                TextColumn::make('number')
                    ->label(__('admin.orders.fields.number'))
                    ->copyable()
                    ->weight('medium'),
                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('total_incl_vat_cents')
                    ->label(__('admin.orders.fields.total'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label(__('admin.fields.status'))
                    ->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
