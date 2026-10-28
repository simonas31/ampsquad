<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Checkout\OrderAdministration;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * The only way an order is changed from the admin panel. Each action asks
 * OrderAdministration, which says whether it was this click that moved the
 * order; if not (someone else did, or a payment arrived meanwhile) nothing
 * was done and the page shows where the order stands now.
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return __('admin.orders.label').' '.$this->getRecord()->number;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->shipAction(),
            $this->cancelAction(),
            $this->refundAction(),
            $this->paymentRefundedAction(),
            $this->notesAction(),
        ];
    }

    private function order(): Order
    {
        /** @var Order */
        return $this->getRecord();
    }

    private function administration(): OrderAdministration
    {
        return app(OrderAdministration::class);
    }

    /**
     * Shows the outcome of an action and the order as it is now.
     */
    private function finish(bool $done, string $doneMessage): void
    {
        $this->order()->refresh();

        $done
            ? Notification::make()->title($doneMessage)->success()->send()
            : Notification::make()->title(__('admin.orders.notifications.changed_meanwhile'))->warning()->send();
    }

    private function shipAction(): Action
    {
        return Action::make('ship')
            ->label(__('admin.orders.actions.ship'))
            ->icon(Heroicon::OutlinedTruck)
            ->color('success')
            ->authorize('update')
            ->visible(fn (): bool => $this->order()->status === OrderStatus::Paid)
            ->requiresConfirmation()
            ->modalHeading(fn (): string => __('admin.orders.actions.ship_heading', ['number' => $this->order()->number]))
            ->modalDescription(__('admin.orders.actions.ship_description'))
            ->action(fn () => $this->finish(
                $this->administration()->markShipped($this->order()),
                __('admin.orders.notifications.shipped'),
            ));
    }

    private function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label(__('admin.orders.actions.cancel'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize('update')
            ->visible(fn (): bool => in_array($this->order()->status, [OrderStatus::PendingPayment, OrderStatus::Paid], true))
            ->requiresConfirmation()
            ->modalHeading(fn (): string => __('admin.orders.actions.cancel_heading', ['number' => $this->order()->number]))
            ->modalDescription(fn (): string => $this->order()->status === OrderStatus::Paid
                ? __('admin.orders.actions.cancel_paid')
                : __('admin.orders.actions.cancel_waiting'))
            ->schema([
                Toggle::make('restock')
                    ->label(__('admin.orders.actions.restock'))
                    ->default(true)
                    ->visible(fn (): bool => $this->order()->status === OrderStatus::Paid),
            ])
            ->action(fn (array $data) => $this->finish(
                $this->administration()->cancel($this->order(), (bool) ($data['restock'] ?? true)),
                __('admin.orders.notifications.cancelled'),
            ));
    }

    private function refundAction(): Action
    {
        return Action::make('refund')
            ->label(__('admin.orders.actions.refund'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->authorize('update')
            ->visible(fn (): bool => $this->order()->status === OrderStatus::Shipped)
            ->requiresConfirmation()
            ->modalHeading(fn (): string => __('admin.orders.actions.refund_heading', ['number' => $this->order()->number]))
            ->modalDescription(__('admin.orders.actions.refund_description'))
            ->schema([
                Toggle::make('restock')
                    ->label(__('admin.orders.actions.refund_restock'))
                    ->helperText(__('admin.orders.actions.refund_restock_helper'))
                    ->default(false),
            ])
            ->action(fn (array $data) => $this->finish(
                $this->administration()->refund($this->order(), (bool) ($data['restock'] ?? false)),
                __('admin.orders.notifications.refunded'),
            ));
    }

    /**
     * For money taken that the order cannot keep: an order that expired or
     * was cancelled, or an extra payment. Refunding a shipped order has its
     * own action.
     */
    private function paymentRefundedAction(): Action
    {
        return Action::make('paymentRefunded')
            ->label(__('admin.orders.actions.payment_refunded'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('warning')
            ->authorize('update')
            ->visible(fn (): bool => $this->order()->needsAPerson())
            ->modalHeading(__('admin.orders.actions.payment_refunded_heading'))
            ->modalDescription(__('admin.orders.actions.payment_refunded_description'))
            ->schema([
                Select::make('payment')
                    ->label(__('admin.orders.actions.payment'))
                    ->options(fn (): array => $this->order()->payments()
                        ->where('status', PaymentStatus::Succeeded)
                        ->get()
                        ->mapWithKeys(fn (Payment $payment): array => [$payment->getKey() => __('admin.orders.payment_option', [
                            'reference' => $payment->provider_reference ?? $payment->provider,
                            'amount' => Money::format($payment->paid_amount_cents ?? $payment->amount_cents),
                            'status' => $payment->status->getLabel(),
                        ])])
                        ->all())
                    ->required(),
            ])
            ->action(function (array $data): void {
                $payment = $this->order()->payments()->whereKey($data['payment'])->firstOrFail();

                $this->finish(
                    $this->administration()->markPaymentRefunded($payment),
                    __('admin.orders.notifications.payment_refunded'),
                );
            });
    }

    private function notesAction(): Action
    {
        return Action::make('editNotes')
            ->label(__('admin.orders.actions.edit_notes'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->authorize('update')
            ->fillForm(fn (): array => ['internal_notes' => $this->order()->internal_notes])
            ->schema([
                Textarea::make('internal_notes')
                    ->label(__('admin.orders.fields.internal_notes'))
                    ->rows(8)
                    ->maxLength(5000),
            ])
            ->action(function (array $data): void {
                $this->order()->update(['internal_notes' => filled($data['internal_notes'] ?? null) ? $data['internal_notes'] : null]);

                Notification::make()->title(__('admin.orders.notifications.notes_saved'))->success()->send();
            });
    }
}
