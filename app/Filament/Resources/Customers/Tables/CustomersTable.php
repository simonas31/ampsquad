<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * A page at a time, the newest first, and one extra count per row batch
 * however many rows: the number of orders is a subquery, never a load. A
 * closed account keeps its address in another column, so searching looks in
 * both.
 */
class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('orders'))
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('admin.customers.fields.full_name'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('email')
                    ->label(__('admin.customers.fields.email'))
                    ->state(fn (Customer $record): ?string => $record->email ?? $record->retained_email)
                    ->searchable(['email', 'retained_email'])
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('phone')
                    ->label(__('admin.customers.fields.phone'))
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('email_verified_at')
                    ->label(__('admin.customers.fields.verified'))
                    ->state(fn (Customer $record): bool => $record->email_verified_at !== null)
                    ->boolean(),
                TextColumn::make('orders_count')
                    ->label(__('admin.customers.fields.orders'))
                    ->alignEnd()
                    ->sortable(),
                IconColumn::make('marketing_consent_at')
                    ->label(__('admin.customers.fields.marketing'))
                    ->state(fn (Customer $record): bool => $record->marketing_consent_at !== null)
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('locale')
                    ->label(__('admin.customers.fields.locale'))
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->label(__('admin.customers.fields.closed_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->filtersFormColumns(['default' => 1, 'sm' => 2, 'xl' => 4])
            ->filters([
                TrashedFilter::make()
                    ->label(__('admin.customers.filters.closed')),
                TernaryFilter::make('verified')
                    ->label(__('admin.customers.filters.verified'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('email_verified_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('email_verified_at'),
                    ),
                TernaryFilter::make('marketing')
                    ->label(__('admin.customers.filters.marketing'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('marketing_consent_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('marketing_consent_at'),
                    ),
                SelectFilter::make('locale')
                    ->label(__('admin.customers.fields.locale'))
                    ->options(['lt' => 'LT', 'en' => 'EN']),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
