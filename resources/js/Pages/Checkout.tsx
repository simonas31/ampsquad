import { Link, useForm, usePage } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import {
  AddressChoice,
  type AddressChoiceKind,
} from "@/components/shop/AddressChoice";
import { AmountsSummary } from "@/components/shop/AmountsSummary";
import { ShippingOptions } from "@/components/shop/ShippingOptions";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input, Textarea } from "@/components/ui/input";
import { useT } from "@/hooks/use-t";
import { formatCents } from "@/lib/shop";
import type {
  Breadcrumb,
  CartData,
  CustomerAddress,
  ShippingMethodOption,
} from "@/types";

interface CheckoutProps {
  /** The customer signed in with a verified address; null for a guest. */
  customer: { fullName: string; email: string; phone: string | null } | null;
  /** Their saved addresses, the default first; none for a guest. */
  savedAddresses: CustomerAddress[];
  /** The one that is their default; null when they have none. */
  defaultAddressId: number | null;
  cart: CartData;
  shippingMethods: ShippingMethodOption[];
  submitUrl: string;
  rulesUrl: string | null;
  privacyUrl: string | null;
  breadcrumbs: Breadcrumb[];
}

/** The order the fields appear in, which is the order an error summary lists them. */
const fieldOrder = [
  "name",
  "email",
  "phone",
  "shipping_method_id",
  "address_id",
  "new_street",
  "new_city",
  "new_postal_code",
  "delivery_address",
  "note",
  "accept_terms",
] as const;

/**
 * Checkout: the visitor's details, how to get the order, and the button
 * that places it and sends them to pay. Every amount here was worked out by
 * the server; the form sends back what the visitor typed and chose, and the
 * fingerprint of what they were shown, which the server compares with what is
 * true when they press the button. If something changed, it refuses and sends
 * them back to the cart rather than charge something else.
 */
export default function Checkout({
  customer,
  savedAddresses,
  defaultAddressId,
  cart,
  shippingMethods,
  submitUrl,
  rulesUrl,
  privacyUrl,
  breadcrumbs,
}: CheckoutProps) {
  const t = useT();
  const { locale, cartSummary, account } = usePage().props;
  const cartUrl = cartSummary?.url ?? "/cart";
  const summaryRef = useRef<HTMLDivElement>(null);
  const [failure, setFailure] = useState<string>();

  // A customer's details start filled in, and their default address chosen.
  // What the server does with the choice is for it to decide: a guest's is
  // ignored, and a saved address is only ever named by id.
  const form = useForm({
    name: customer?.fullName ?? "",
    email: customer?.email ?? "",
    phone: customer?.phone ?? "",
    shipping_method_id: (shippingMethods[0]?.id ?? null) as number | null,
    delivery_address: "",
    address_choice: (defaultAddressId !== null
      ? "saved"
      : "new") as AddressChoiceKind,
    address_id: defaultAddressId,
    new_street: "",
    new_city: "",
    new_postal_code: "",
    save_address: false,
    note: "",
    accept_terms: false,
  });

  // The form's own fields, plus the messages the server sends under other
  // names (checkout: a refusal; page_expired: the session ran out).
  const errors: Record<string, string | undefined> = form.errors;

  const method = shippingMethods.find(
    (candidate) => candidate.id === form.data.shipping_method_id,
  );
  const needsAddress = method !== undefined && !method.isPickup;

  // Send keyboard and screen reader focus to the list of what went wrong, so
  // it is announced instead of sitting unseen above the fold.
  const errorKeys = Object.keys(errors).join();
  useEffect(() => {
    if (errorKeys || failure) {
      summaryRef.current?.focus();
    }
  }, [errorKeys, failure]);

  // The Back button from the payment page can restore this page exactly as it
  // was when it left, with the button stuck on "taking you to the payment
  // page". Ask the server again instead: it sends the visitor to their
  // waiting order, or back to the cart.
  useEffect(() => {
    const restored = (event: PageTransitionEvent) => {
      if (event.persisted) {
        window.location.reload();
      }
    };

    window.addEventListener("pageshow", restored);

    return () => window.removeEventListener("pageshow", restored);
  }, []);

  function submit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!method) {
      return;
    }

    setFailure(undefined);

    form.transform((data) => ({ ...data, fingerprint: method.fingerprint }));
    form.post(submitUrl, {
      preserveScroll: true,
      onError: (errors) => {
        if (errors.page_expired) {
          setFailure(t("shop.pageExpired"));
        }
      },
      // Stay on the form and say so, instead of swapping it for an error page.
      onHttpException: () => {
        setFailure(t("checkout.failed"));

        return false;
      },
      onNetworkError: () => setFailure(t("checkout.failed")),
    });
  }

  /** What to tell the visitor, in the order of the form; the server's own message for the rest. */
  const problems: Array<{ field: string | null; message: string }> = [
    ...fieldOrder
      .filter((field) => errors[field])
      .map((field) => ({ field, message: errors[field] as string })),
    ...(errors.checkout ? [{ field: null, message: errors.checkout }] : []),
    ...(failure ? [{ field: null, message: failure }] : []),
  ];

  function focusField(event: React.MouseEvent, field: string) {
    event.preventDefault();
    document.getElementById(field)?.focus();
  }

  const total = method?.totalInclVatCents ?? cart.totalInclVatCents;

  return (
    <>
      <PageHeader
        breadcrumbs={breadcrumbs}
        title={t("checkout.title")}
        description={t("checkout.intro")}
      />

      <Container size="wide" className="py-14 lg:py-20">
        {shippingMethods.length === 0 ? (
          <div className="border-ink border-t py-16">
            <p className="display text-ink-soft max-w-[24ch] text-3xl">
              {t("checkout.noMethods")}
            </p>
            <Button asChild className="mt-10">
              <Link href={cartUrl}>{t("checkout.backToCart")}</Link>
            </Button>
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-x-10 gap-y-12 lg:grid-cols-12">
            <form
              className="space-y-12 lg:col-span-7"
              onSubmit={submit}
              aria-busy={form.processing}
              noValidate
            >
              <div
                ref={summaryRef}
                tabIndex={-1}
                role="alert"
                className="outline-none"
              >
                {problems.length > 0 && (
                  <div className="border-destructive rounded-2xl border bg-white p-5">
                    <h2 className="text-destructive font-semibold">
                      {t("checkout.errorSummary")}
                    </h2>
                    <ul className="text-ink mt-2 list-disc space-y-1 pl-5 text-sm">
                      {problems.map(({ field, message }) => (
                        <li key={`${field}-${message}`}>
                          {field ? (
                            <a
                              href={`#${field}`}
                              onClick={(event) => focusField(event, field)}
                              className="underline underline-offset-4"
                            >
                              {message}
                            </a>
                          ) : (
                            message
                          )}
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>

              <section className="space-y-7" aria-labelledby="contact-heading">
                <h2 id="contact-heading" className="display text-ink text-2xl">
                  {t("checkout.contactSection")}
                </h2>

                {customer ? (
                  <p className="text-ink-soft text-sm">
                    {t("checkout.loggedInAs", { email: customer.email })}
                  </p>
                ) : (
                  <p className="text-ink-soft text-sm">
                    {t("checkout.haveAccount")}{" "}
                    <Link
                      href={`${account.loginUrl}?return=checkout`}
                      className="text-ink underline underline-offset-4"
                    >
                      {t("checkout.logInToFill")}
                    </Link>
                  </p>
                )}

                <Field
                  id="name"
                  label={t("checkout.name")}
                  error={form.errors.name}
                  required
                >
                  {(control) => (
                    <Input
                      {...control}
                      value={form.data.name}
                      onChange={(event) =>
                        form.setData("name", event.target.value)
                      }
                      autoComplete="name"
                    />
                  )}
                </Field>

                <div className="grid gap-7 sm:grid-cols-2">
                  <Field
                    id="email"
                    label={t("checkout.email")}
                    error={form.errors.email}
                    required
                  >
                    {(control) => (
                      <Input
                        {...control}
                        type="email"
                        value={form.data.email}
                        onChange={(event) =>
                          form.setData("email", event.target.value)
                        }
                        autoComplete="email"
                      />
                    )}
                  </Field>

                  <Field
                    id="phone"
                    label={t("checkout.phone")}
                    error={form.errors.phone}
                    required
                  >
                    {(control) => (
                      <Input
                        {...control}
                        type="tel"
                        value={form.data.phone}
                        onChange={(event) =>
                          form.setData("phone", event.target.value)
                        }
                        autoComplete="tel"
                      />
                    )}
                  </Field>
                </div>
              </section>

              <ShippingOptions
                id="shipping_method_id"
                methods={shippingMethods}
                value={form.data.shipping_method_id}
                onChange={(id) => form.setData("shipping_method_id", id)}
                error={form.errors.shipping_method_id}
              />

              {needsAddress && customer ? (
                <AddressChoice
                  addresses={savedAddresses}
                  value={{
                    choice: form.data.address_choice,
                    addressId: form.data.address_id,
                    street: form.data.new_street,
                    city: form.data.new_city,
                    postalCode: form.data.new_postal_code,
                    text: form.data.delivery_address,
                    save: form.data.save_address,
                  }}
                  onChange={(next) =>
                    form.setData((data) => ({
                      ...data,
                      address_choice: next.choice,
                      address_id: next.addressId,
                      new_street: next.street,
                      new_city: next.city,
                      new_postal_code: next.postalCode,
                      delivery_address: next.text,
                      save_address: next.save,
                    }))
                  }
                  errors={errors}
                />
              ) : needsAddress ? (
                <div className="space-y-2">
                  <Field
                    id="delivery_address"
                    label={t("checkout.address")}
                    error={form.errors.delivery_address}
                    required
                  >
                    {(control) => (
                      <Textarea
                        {...control}
                        rows={3}
                        value={form.data.delivery_address}
                        onChange={(event) =>
                          form.setData("delivery_address", event.target.value)
                        }
                        autoComplete="street-address"
                      />
                    )}
                  </Field>
                  <p className="text-ink-soft text-sm">
                    {t("checkout.addressHint")}
                  </p>
                </div>
              ) : (
                <p className="text-ink-soft text-sm">
                  {t("checkout.pickupHint")}
                </p>
              )}

              <Field
                id="note"
                label={`${t("checkout.note")} (${t("checkout.noteOptional")})`}
                error={form.errors.note}
              >
                {(control) => (
                  <Textarea
                    {...control}
                    rows={3}
                    value={form.data.note}
                    onChange={(event) =>
                      form.setData("note", event.target.value)
                    }
                  />
                )}
              </Field>
              <p className="text-ink-soft -mt-5 text-sm">
                {t("checkout.noteHint")}
              </p>

              <div className="space-y-2">
                <div className="flex items-start gap-3">
                  <input
                    id="accept_terms"
                    type="checkbox"
                    checked={form.data.accept_terms}
                    onChange={(event) =>
                      form.setData("accept_terms", event.target.checked)
                    }
                    required
                    aria-invalid={form.errors.accept_terms ? true : undefined}
                    aria-describedby={
                      form.errors.accept_terms
                        ? "accept_terms-error"
                        : undefined
                    }
                    className="accent-ink size-5 shrink-0"
                  />
                  <label htmlFor="accept_terms" className="text-ink text-sm">
                    <ConsentLabel rulesUrl={rulesUrl} privacyUrl={privacyUrl} />
                    <span className="text-ink ml-1" aria-hidden="true">
                      *
                    </span>
                  </label>
                </div>
                {form.errors.accept_terms && (
                  <p
                    id="accept_terms-error"
                    className="text-destructive text-sm"
                  >
                    {form.errors.accept_terms}
                  </p>
                )}
              </div>

              <div className="space-y-4">
                <Button
                  type="submit"
                  variant="accent"
                  size="lg"
                  className="w-full sm:w-auto"
                  disabled={form.processing || !method}
                >
                  {form.processing
                    ? t("checkout.redirecting")
                    : t("checkout.pay", {
                        total: formatCents(total, locale.current),
                      })}
                </Button>
                <p role="status" className="text-ink-soft max-w-[52ch] text-sm">
                  {form.processing
                    ? t("checkout.redirecting")
                    : t("checkout.payNote")}
                </p>
                <p className="meta text-ink-soft">
                  {t("checkout.requiredHint")}
                </p>
              </div>
            </form>

            <aside className="space-y-4 lg:col-span-5">
              <div className="lg:sticky lg:top-28">
                <AmountsSummary
                  heading={t("checkout.summary")}
                  lines={cart.items.map((line) => ({
                    key: line.id,
                    name: line.product.name,
                    quantity: line.quantity,
                    totalInclVatCents: line.lineTotalInclVatCents,
                    imageUrl: line.product.imageThumbUrl,
                  }))}
                  subtotalExclVatCents={cart.subtotalExclVatCents}
                  shipping={
                    method && {
                      name: method.name,
                      priceExclVatCents: method.priceExclVatCents,
                    }
                  }
                  vatRate={cart.vatRate}
                  vatCents={method?.vatCents ?? cart.vatCents}
                  totalInclVatCents={total}
                />
                <Button asChild variant="link" className="mt-4">
                  <Link href={cartUrl}>{t("checkout.backToCart")}</Link>
                </Button>
              </div>
            </aside>
          </div>
        )}
      </Container>
    </>
  );
}

/**
 * What the buyer must read before ordering, with each named document a link
 * to its page, in a new tab so the form is not lost. When the shop has not
 * made a page, its name is plain text.
 */
function ConsentLabel({
  rulesUrl,
  privacyUrl,
}: {
  rulesUrl: string | null;
  privacyUrl: string | null;
}) {
  const t = useT();

  if (!rulesUrl && !privacyUrl) {
    return <>{t("checkout.consentPlain")}</>;
  }

  const link = (url: string | null, text: string) =>
    url ? (
      <a
        href={url}
        target="_blank"
        rel="noreferrer"
        className="underline underline-offset-4"
      >
        {text}
      </a>
    ) : (
      text
    );

  // The sentence has the two names in the middle of it, so split it there.
  const [before, between, after] = t("checkout.consent", {
    rules: "\u0000",
    privacy: "\u0001",
  }).split(/\u0000|\u0001/);

  return (
    <>
      {before}
      {link(rulesUrl, t("checkout.rulesLink"))}
      {between}
      {link(privacyUrl, t("checkout.privacyLink"))}
      {after}
    </>
  );
}
