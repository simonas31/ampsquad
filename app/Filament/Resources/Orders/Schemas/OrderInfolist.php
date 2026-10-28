<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\PaymentOutcome;
use App\Models\Order;
use App\Support\Money;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * An order as the shop sees it: everything the order itself kept, and
 * nothing read back from the products or the shipping method, which may have
 * changed since.
 */
class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.orders.sections.attention'))
                    ->description(__('admin.orders.attention.text'))
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger')
                    ->visible(fn (Order $record): bool => $record->needsAPerson())
                    ->columnSpanFull(),
                Section::make(__('admin.orders.sections.order'))
                    ->schema([
                        TextEntry::make('number')
                            ->label(__('admin.orders.fields.number'))
                            ->copyable(),
                        TextEntry::make('status')
                            ->label(__('admin.fields.status'))
                            ->badge(),
                        TextEntry::make('created_at')
                            ->label(__('admin.fields.created_at'))
                            ->dateTime(),
                        TextEntry::make('payment_expires_at')
                            ->label(__('admin.orders.fields.payment_expires_at'))
                            ->dateTime(),
                        ...collect(['paid_at', 'shipped_at', 'cancelled_at', 'expired_at', 'refunded_at'])->map(
                            fn (string $column): TextEntry => TextEntry::make($column)
                                ->label(__("admin.orders.fields.{$column}"))
                                ->dateTime()
                                ->visible(fn (Order $record): bool => $record->{$column} !== null),
                        )->all(),
                        TextEntry::make('locale')
                            ->label(__('admin.orders.fields.locale'))
                            ->formatStateUsing(fn (string $state): string => __("admin.orders.fields.locale_{$state}")),
                        TextEntry::make('public_page')
                            ->label(__('admin.orders.fields.public_page'))
                            ->state(fn (Order $record): string => $record->publicUrl())
                            ->url(fn (Order $record): string => $record->publicUrl(), shouldOpenInNewTab: true)
                            ->limit(40),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('admin.orders.sections.customer'))
                    ->schema([
                        TextEntry::make('customer_name')
                            ->label(__('admin.orders.fields.customer_name'))
                            ->placeholder('—'),
                        TextEntry::make('customer_email')
                            ->label(__('admin.orders.fields.customer_email'))
                            ->url(fn (Order $record): ?string => filled($record->customer_email) ? "mailto:{$record->customer_email}" : null)
                            ->copyable()
                            ->placeholder('—'),
                        TextEntry::make('customer_phone')
                            ->label(__('admin.orders.fields.customer_phone'))
                            ->url(fn (Order $record): ?string => filled($record->customer_phone) ? "tel:{$record->customer_phone}" : null)
                            ->placeholder('—'),
                        TextEntry::make('customer_note')
                            ->label(__('admin.orders.fields.customer_note'))
                            ->visible(fn (Order $record): bool => filled($record->customer_note))
                            ->columnSpanFull(),
                        TextEntry::make('anonymized_at')
                            ->hiddenLabel()
                            ->state(fn (Order $record): string => __('admin.orders.fields.wiped', ['date' => $record->anonymized_at?->isoFormat('LLL')]))
                            ->color('gray')
                            ->visible(fn (Order $record): bool => $record->anonymized_at !== null)
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('admin.orders.sections.delivery'))
                    ->schema([
                        TextEntry::make('shipping_label')
                            ->label(__('admin.orders.fields.shipping_label')),
                        IconEntry::make('shipping_is_pickup')
                            ->label(__('admin.orders.fields.is_pickup'))
                            ->boolean(),
                        TextEntry::make('delivery_address')
                            ->label(__('admin.orders.fields.delivery_address'))
                            ->copyable()
                            ->visible(fn (Order $record): bool => filled($record->delivery_address)),
                        TextEntry::make('shipping_details')
                            ->label(__('admin.orders.fields.shipping_details'))
                            ->visible(fn (Order $record): bool => filled($record->shipping_details))
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('admin.orders.sections.lines'))
                    ->schema([
                        RepeatableEntry::make('lines')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('product_name')
                                    ->label(__('admin.orders.fields.product')),
                                TextEntry::make('sku')
                                    ->label(__('admin.orders.fields.sku'))
                                    ->placeholder('—'),
                                TextEntry::make('quantity')
                                    ->label(__('admin.orders.fields.quantity')),
                                TextEntry::make('unit_price_incl_vat_cents')
                                    ->label(__('admin.orders.fields.unit_price'))
                                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                                TextEntry::make('line_total_incl_vat_cents')
                                    ->label(__('admin.orders.fields.line_total'))
                                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                            ])
                            ->columns(5),
                    ])
                    ->columnSpanFull(),
                Section::make(__('admin.orders.sections.amounts'))
                    ->schema([
                        TextEntry::make('subtotal_excl_vat_cents')
                            ->label(__('admin.orders.fields.subtotal_excl_vat'))
                            ->formatStateUsing(fn (int $state): string => Money::format($state)),
                        TextEntry::make('shipping_excl_vat_cents')
                            ->label(__('admin.orders.fields.shipping_excl_vat'))
                            ->formatStateUsing(fn (int $state): string => Money::format($state)),
                        TextEntry::make('shipping_incl_vat_cents')
                            ->label(__('admin.orders.fields.shipping_incl_vat'))
                            ->formatStateUsing(fn (int $state): string => Money::format($state)),
                        TextEntry::make('vat_cents')
                            ->label(fn (Order $record): string => __('admin.orders.fields.vat', ['rate' => $record->vat_rate_percent]))
                            ->formatStateUsing(fn (int $state): string => Money::format($state)),
                        TextEntry::make('total_incl_vat_cents')
                            ->label(__('admin.orders.fields.total_incl_vat'))
                            ->formatStateUsing(fn (int $state): string => Money::format($state))
                            ->weight('bold'),
                    ])
                    ->columns(5)
                    ->columnSpanFull(),
                Section::make(__('admin.orders.sections.payments'))
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('provider')
                                    ->label(__('admin.orders.fields.provider')),
                                TextEntry::make('provider_reference')
                                    ->label(__('admin.orders.fields.reference'))
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('status')
                                    ->label(__('admin.fields.status'))
                                    ->badge(),
                                TextEntry::make('amount_cents')
                                    ->label(__('admin.orders.fields.amount'))
                                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                                TextEntry::make('paid_amount_cents')
                                    ->label(__('admin.orders.fields.paid_amount'))
                                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                                    ->placeholder('—'),
                                TextEntry::make('channel')
                                    ->label(__('admin.orders.fields.channel'))
                                    ->placeholder('—'),
                                TextEntry::make('bank')
                                    ->label(__('admin.orders.fields.bank'))
                                    ->placeholder('—'),
                                TextEntry::make('provider_payment_token')
                                    ->label(__('admin.orders.fields.token'))
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('paid_at')
                                    ->label(__('admin.orders.fields.paid_at'))
                                    ->dateTime()
                                    ->placeholder('—'),
                                TextEntry::make('refunded_at')
                                    ->label(__('admin.orders.fields.refunded_at'))
                                    ->dateTime()
                                    ->placeholder('—'),
                            ])
                            ->columns(5),
                    ])
                    ->columnSpanFull(),
                Section::make(__('admin.orders.sections.messages'))
                    ->schema([
                        RepeatableEntry::make('paymentEvents')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('admin.orders.fields.received_at'))
                                    ->dateTime(),
                                TextEntry::make('provider')
                                    ->label(__('admin.orders.fields.provider')),
                                TextEntry::make('provider_status')
                                    ->label(__('admin.orders.fields.provider_status')),
                                TextEntry::make('outcome')
                                    ->label(__('admin.orders.fields.outcome'))
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : PaymentOutcome::from($state)->getLabel())
                                    ->color(fn (?string $state): string => $state === null ? 'gray' : PaymentOutcome::from($state)->getColor())
                                    ->placeholder('—'),
                            ])
                            ->columns(4),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),
                Section::make(__('admin.orders.sections.notes'))
                    ->schema([
                        TextEntry::make('internal_notes')
                            ->hiddenLabel()
                            ->placeholder('—')
                            ->prose(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
