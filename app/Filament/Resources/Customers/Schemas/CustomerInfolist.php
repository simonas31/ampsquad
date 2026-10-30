<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A customer as the shop holds them. Nothing secret is shown (the password
 * is not even loaded into the page), and no addresses: an order carries the
 * address it was sent to, which is what the shop needs of them.
 */
class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.customers.sections.closed'))
                    ->description(fn (Customer $record): string => __('admin.customers.closed_text', [
                        'closed' => $record->deleted_at?->isoFormat('LLL'),
                        'removed' => $record->deleted_at?->copy()->addYears(Customer::RETENTION_YEARS)->isoFormat('LL'),
                    ]))
                    ->icon('heroicon-o-archive-box')
                    ->visible(fn (Customer $record): bool => $record->trashed())
                    ->columnSpanFull(),
                Section::make(__('admin.customers.sections.account'))
                    ->schema([
                        TextEntry::make('full_name')
                            ->label(__('admin.customers.fields.full_name')),
                        TextEntry::make('email')
                            ->label(__('admin.customers.fields.email'))
                            ->state(fn (Customer $record): ?string => $record->email ?? $record->retained_email)
                            ->url(fn (Customer $record): ?string => filled($record->email ?? $record->retained_email) ? 'mailto:'.($record->email ?? $record->retained_email) : null)
                            ->copyable()
                            ->placeholder('—'),
                        TextEntry::make('phone')
                            ->label(__('admin.customers.fields.phone'))
                            ->url(fn (Customer $record): ?string => filled($record->phone) ? "tel:{$record->phone}" : null)
                            ->placeholder('—'),
                        TextEntry::make('locale')
                            ->label(__('admin.customers.fields.locale'))
                            ->formatStateUsing(fn (string $state): string => __("admin.orders.fields.locale_{$state}")),
                        TextEntry::make('email_verified_at')
                            ->label(__('admin.customers.fields.email_verified_at'))
                            ->dateTime()
                            ->placeholder(__('admin.customers.not_verified')),
                        TextEntry::make('marketing_consent_at')
                            ->label(__('admin.customers.fields.marketing_consent_at'))
                            ->dateTime()
                            ->placeholder(__('admin.customers.no_consent')),
                        TextEntry::make('pending_email')
                            ->label(__('admin.customers.fields.pending_email'))
                            ->visible(fn (Customer $record): bool => filled($record->pending_email)),
                        TextEntry::make('created_at')
                            ->label(__('admin.fields.created_at'))
                            ->dateTime(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }
}
