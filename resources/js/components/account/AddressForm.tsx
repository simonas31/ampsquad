import { Link, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { useT } from "@/hooks/use-t";

export interface AddressFormValues {
  label: string;
  recipientName: string;
  phone: string;
  street: string;
  city: string;
  postalCode: string;
  makeDefault: boolean;
}

interface AddressFormProps {
  initial: AddressFormValues;
  /** POST makes a new address, PUT changes the one at `url`. */
  method: "post" | "put";
  url: string;
  /** Where the form's "Cancel" goes. */
  cancelUrl: string;
  /** True when this address is already the default: there is nothing to tick. */
  isDefault: boolean;
}

/** The order the fields appear in, which is the order focus goes to the first mistake. */
const fieldOrder = [
  "label",
  "recipient_name",
  "phone",
  "street",
  "city",
  "postal_code",
] as const;

/**
 * The form for a new address and for changing one. Only Lithuania is
 * delivered to, so the country is stated and sent, not asked for. The server
 * refuses a customer who already keeps the most addresses (`limit`), which
 * can only happen when they fill in a form that was opened before.
 */
export function AddressForm({
  initial,
  method,
  url,
  cancelUrl,
  isDefault,
}: AddressFormProps) {
  const t = useT();

  const form = useForm({
    label: initial.label,
    recipient_name: initial.recipientName,
    phone: initial.phone,
    street: initial.street,
    city: initial.city,
    postal_code: initial.postalCode,
    country: "LT",
    make_default: initial.makeDefault,
  });

  const errors: Record<string, string | undefined> = form.errors;

  function submit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    form.submit(method, url, {
      onError: (errors) => {
        const firstInvalid = fieldOrder.find((name) => errors[name]);

        if (firstInvalid) {
          document.getElementById(firstInvalid)?.focus();
        }
      },
    });
  }

  return (
    <form
      className="space-y-7"
      onSubmit={submit}
      aria-busy={form.processing}
      noValidate
    >
      {errors.limit && (
        <p role="alert" className="text-destructive text-sm">
          {errors.limit}
        </p>
      )}
      {errors.page_expired && (
        <p role="alert" className="text-destructive text-sm">
          {t("auth.pageExpired")}
        </p>
      )}

      <div className="space-y-2">
        <Field
          id="label"
          label={`${t("account.addresses.label")} (${t("account.addresses.labelOptional")})`}
          error={form.errors.label}
        >
          {(control) => (
            <Input
              {...control}
              value={form.data.label}
              onChange={(event) => form.setData("label", event.target.value)}
            />
          )}
        </Field>
        <p className="text-ink-soft text-sm">{t("account.addresses.labelHint")}</p>
      </div>

      <div className="grid gap-7 sm:grid-cols-2">
        <Field
          id="recipient_name"
          label={t("account.addresses.recipientName")}
          error={form.errors.recipient_name}
          required
        >
          {(control) => (
            <Input
              {...control}
              value={form.data.recipient_name}
              onChange={(event) =>
                form.setData("recipient_name", event.target.value)
              }
              autoComplete="name"
            />
          )}
        </Field>

        <Field
          id="phone"
          label={t("account.addresses.phone")}
          error={form.errors.phone}
          required
        >
          {(control) => (
            <Input
              {...control}
              type="tel"
              value={form.data.phone}
              onChange={(event) => form.setData("phone", event.target.value)}
              autoComplete="tel"
            />
          )}
        </Field>
      </div>

      <Field
        id="street"
        label={t("account.addresses.street")}
        error={form.errors.street}
        required
      >
        {(control) => (
          <Input
            {...control}
            value={form.data.street}
            onChange={(event) => form.setData("street", event.target.value)}
            autoComplete="street-address"
          />
        )}
      </Field>

      <div className="grid gap-7 sm:grid-cols-2">
        <Field
          id="city"
          label={t("account.addresses.city")}
          error={form.errors.city}
          required
        >
          {(control) => (
            <Input
              {...control}
              value={form.data.city}
              onChange={(event) => form.setData("city", event.target.value)}
              autoComplete="address-level2"
            />
          )}
        </Field>

        <Field
          id="postal_code"
          label={t("account.addresses.postalCode")}
          error={form.errors.postal_code}
          required
        >
          {(control) => (
            <Input
              {...control}
              value={form.data.postal_code}
              onChange={(event) =>
                form.setData("postal_code", event.target.value)
              }
              autoComplete="postal-code"
            />
          )}
        </Field>
      </div>

      <p className="text-ink-soft text-sm">
        {t("account.addresses.country")}:{" "}
        <span className="text-ink">{t("account.addresses.lithuania")}</span>
      </p>

      {isDefault ? (
        <p className="text-ink-soft text-sm">{t("account.addresses.isDefault")}</p>
      ) : (
        <div className="flex items-start gap-3">
          <input
            id="make_default"
            type="checkbox"
            checked={form.data.make_default}
            onChange={(event) =>
              form.setData("make_default", event.target.checked)
            }
            className="accent-ink size-5 shrink-0"
          />
          <label htmlFor="make_default" className="text-ink text-sm">
            {t("account.addresses.makeDefault")}
          </label>
        </div>
      )}

      <div className="flex flex-wrap items-center gap-x-4 gap-y-3 pt-1">
        <Button type="submit" disabled={form.processing}>
          {form.processing
            ? t("account.addresses.saving")
            : t("account.addresses.save")}
        </Button>
        <Button asChild variant="ghost">
          <Link href={cancelUrl}>{t("account.addresses.cancel")}</Link>
        </Button>
        <p className="meta text-ink-soft">{t("auth.requiredHint")}</p>
      </div>
    </form>
  );
}
