<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\PaymentOutcome;
use App\Events\PaymentNeedsAPerson;
use App\Mail\ContactRequestReceivedMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderPaidMail;
use App\Mail\PaymentNeedsAPersonMail;
use App\Mail\VerifyCustomerEmailMail;
use App\Models\ContactRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Recipient;
use App\Services\RecipientNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The convention (CLAUDE.md): every email is written in Lithuanian and
 * English, in lang/{lt,en}/mail.php and nowhere else, and the shop's team gets
 * Lithuanian unless told otherwise.
 */
class EmailLanguagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, Mailable>
     */
    private function mails(): array
    {
        $order = Order::factory()->paid()->create();

        return [
            'contact request' => new ContactRequestReceivedMail(ContactRequest::factory()->create()),
            'paid order' => new OrderPaidMail($order),
            'payment needs a person' => new PaymentNeedsAPersonMail($order, PaymentOutcome::DoublePayment),
            'order confirmation' => new OrderConfirmationMail($order),
            'customer verify email' => new VerifyCustomerEmailMail(Customer::factory()->unverified()->create()),
        ];
    }

    public function test_both_languages_have_exactly_the_same_email_texts(): void
    {
        $lithuanian = array_keys(Arr::dot(require lang_path('lt/mail.php')));
        $english = array_keys(Arr::dot(require lang_path('en/mail.php')));

        $this->assertEqualsCanonicalizing($english, $lithuanian);
        $this->assertNotEmpty($english);
    }

    public function test_no_email_text_is_left_empty_or_the_same_in_both_languages_by_accident(): void
    {
        $lithuanian = Arr::dot(require lang_path('lt/mail.php'));
        $english = Arr::dot(require lang_path('en/mail.php'));
        // Words that are the same in both on purpose.
        $shared = ['order_paid.sku', 'order_paid.total', 'order_confirmation.total'];

        foreach ($english as $key => $text) {
            $this->assertNotSame('', trim((string) $lithuanian[$key]), "lt {$key} is empty");
            $this->assertNotSame('', trim((string) $text), "en {$key} is empty");

            if (! in_array($key, $shared, true) && mb_strlen((string) $text) > 12) {
                $this->assertNotSame($text, $lithuanian[$key], "{$key} is not translated");
            }
        }
    }

    public function test_every_email_renders_in_both_languages_with_no_key_left_showing(): void
    {
        foreach (['lt', 'en'] as $locale) {
            app()->setLocale($locale);

            foreach ($this->mails() as $name => $mail) {
                $html = $mail->render();

                $this->assertDoesNotMatchRegularExpression('/\bmail\.[a-z_]+\.[a-z_.]+/', $html, "{$name} in {$locale} shows a translation key");
                $this->assertStringNotContainsString("\u{2014}", $html, "{$name} in {$locale} has a dash separator");
            }
        }
    }

    public function test_the_same_email_differs_between_the_languages(): void
    {
        foreach ($this->mails() as $name => $mail) {
            app()->setLocale('lt');
            $lithuanian = $mail->render();
            app()->setLocale('en');
            $english = $mail->render();

            $this->assertNotSame($lithuanian, $english, "{$name} is the same in both languages");
        }
    }

    public function test_the_subject_of_every_email_follows_the_language(): void
    {
        foreach ($this->mails() as $name => $mail) {
            app()->setLocale('lt');
            $lithuanian = $mail->envelope()->subject;
            app()->setLocale('en');
            $english = $mail->envelope()->subject;

            $this->assertNotSame($lithuanian, $english, "{$name}'s subject is the same in both languages");
        }
    }

    /**
     * Words in a view belong in lang/mail.php. Whatever is left of a view once
     * its Blade tags and markup are taken out must have no letters in it.
     */
    public function test_the_mail_views_hold_no_wording_of_their_own(): void
    {
        $views = glob(resource_path('views/mail/*.blade.php'));
        $this->assertNotEmpty($views);

        foreach ($views as $view) {
            $source = (string) file_get_contents($view);
            $source = (string) preg_replace('/@php.*?@endphp/s', '', $source);
            $source = (string) preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', '', $source);
            $source = (string) preg_replace('/^\s*@.*$/m', '', $source);
            $source = (string) preg_replace('/<[^>]+>/', '', $source);

            $this->assertDoesNotMatchRegularExpression('/\p{L}{2,}/u', $source, basename($view).' has wording that is not translated: '.trim($source));
        }
    }

    public function test_the_shops_team_gets_lithuanian_by_default(): void
    {
        Recipient::factory()->create(['email' => 'team@shop.test', 'notification_types' => [NotificationType::OrderPaid, NotificationType::ContactFormSubmitted]]);
        Mail::fake();
        $order = Order::factory()->paid()->create();
        $this->assertSame('lt', app()->getLocale(), 'Lithuanian is the default language');

        PaymentNeedsAPerson::dispatch($order, PaymentOutcome::DoublePayment);
        $this->post('/contact', ['name' => 'Jonas', 'email' => 'jonas@example.test', 'phone' => '', 'message' => 'Sveiki, turiu klausimą.', 'company' => ''])->assertRedirect();

        Mail::assertQueued(PaymentNeedsAPersonMail::class, fn (PaymentNeedsAPersonMail $mail): bool => $mail->hasTo('team@shop.test') && $mail->locale === 'lt');
        Mail::assertQueued(ContactRequestReceivedMail::class, fn (ContactRequestReceivedMail $mail): bool => $mail->hasTo('team@shop.test') && $mail->locale === 'lt');
    }

    public function test_the_team_can_be_mailed_in_english_when_the_sender_says_so(): void
    {
        Recipient::factory()->create(['email' => 'team@shop.test', 'notification_types' => [NotificationType::OrderPaid]]);
        Mail::fake();
        $order = Order::factory()->paid()->create();

        app(RecipientNotifier::class)->notify(NotificationType::OrderPaid, new OrderPaidMail($order), 'en');

        Mail::assertQueued(OrderPaidMail::class, fn (OrderPaidMail $mail): bool => $mail->locale === 'en');
    }

    public function test_the_mail_gets_an_explicit_language_so_it_reads_the_same_wherever_it_is_built(): void
    {
        Recipient::factory()->create(['notification_types' => [NotificationType::OrderPaid]]);
        Mail::fake();

        app(RecipientNotifier::class)->notify(NotificationType::OrderPaid, new OrderPaidMail(Order::factory()->paid()->create()));

        Mail::assertQueued(OrderPaidMail::class, fn (OrderPaidMail $mail): bool => $mail->locale !== null);
    }

    public function test_customers_are_mailed_in_their_own_language(): void
    {
        Mail::fake();
        $order = Order::factory()->inLocale('en')->paid()->create();

        Mail::to($order->customer_email)->locale($order->locale)->queue(new OrderConfirmationMail($order));

        Mail::assertQueued(OrderConfirmationMail::class, fn (OrderConfirmationMail $mail): bool => $mail->locale === 'en');
    }
}
