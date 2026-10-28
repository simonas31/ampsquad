<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Built for thousands of orders: the newest first on an indexed column, a
 * page at a time (never "all"), one extra query for the payment column
 * however many rows, and filters that use the (status, created_at) index or
 * the payments' (order_id, status) one.
 */
class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('latestPayment'))
            ->columns([
                TextColumn::make('number')
                    ->label(__('admin.orders.fields.number'))
                    ->searchable()
                    ->copyable()
                    ->weight('medium'),
                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label(__('admin.orders.fields.customer_name'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('customer_email')
                    ->label(__('admin.orders.fields.customer_email'))
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('customer_phone')
                    ->label(__('admin.orders.fields.customer_phone'))
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('shipping_label')
                    ->label(__('admin.orders.fields.shipping'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_incl_vat_cents')
                    ->label(__('admin.orders.fields.total'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('admin.fields.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('latestPayment.status')
                    ->label(__('admin.orders.fields.payment'))
                    ->badge()
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.fields.status'))
                    ->options(OrderStatus::class)
                    ->multiple(),
                Filter::make('created_at')
                    ->label(__('admin.orders.filters.created_at'))
                    ->schema([
                        DatePicker::make('from')->label(__('admin.orders.filters.from')),
                        DatePicker::make('until')->label(__('admin.orders.filters.until')),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $from): Builder => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
                        ->when($data['until'] ?? null, fn (Builder $query, string $until): Builder => $query->where('created_at', '<=', Carbon::parse($until)->endOfDay())))
                    ->indicateUsing(function (array $data): array {
                        return array_values(array_filter([
                            ($data['from'] ?? null) ? __('admin.orders.filters.from_indicator', ['date' => Carbon::parse($data['from'])->toFormattedDateString()]) : null,
                            ($data['until'] ?? null) ? __('admin.orders.filters.until_indicator', ['date' => Carbon::parse($data['until'])->toFormattedDateString()]) : null,
                        ]));
                    }),
                SelectFilter::make('payment_status')
                    ->label(__('admin.orders.filters.payment_status'))
                    ->options(PaymentStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('payments', fn (Builder $payments) => $payments->where('status', $data['value']))
                        : $query),
                Filter::make('needs_a_person')
                    ->label(__('admin.orders.filters.needs_a_person'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->needingAPerson()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
