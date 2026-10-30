import { Field } from "@/components/ui/field";
import { Input, Textarea } from "@/components/ui/input";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";
import type { CustomerAddress } from "@/types";

export type AddressChoiceKind = "saved" | "new" | "text";

export interface AddressChoiceValue {
  choice: AddressChoiceKind;
  /** The saved address chosen; null when none is. */
  addressId: number | null;
  street: string;
  city: string;
  postalCode: string;
  /** The address or parcel locker typed as text. */
  text: string;
  /** Keep the new address in the customer's account. */
  save: boolean;
}

interface AddressChoiceProps {
  addresses: CustomerAddress[];
  value: AddressChoiceValue;
  onChange: (value: AddressChoiceValue) => void;
  /** The form's errors by field name, as the checkout page has them. */
  errors: Record<string, string | undefined>;
}

const cardClasses = (checked: boolean) =>
  cn(
    "space-y-4 rounded-2xl border bg-white p-4 transition-colors has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-ink/20",
    checked ? "border-ink ring-ink/10 ring-4" : "border-input hover:border-ink",
  );

/**
 * How a signed-in customer gives the delivery address: one of the addresses
 * they saved, a new one typed in parts (which can be kept in their account),
 * or text for a parcel locker or any other place. The same radio cards as the
 * delivery methods. A saved address is only named by its id: the server looks
 * it up among the customer's own and writes it out itself.
 */
export function AddressChoice({
  addresses,
  value,
  onChange,
  errors,
}: AddressChoiceProps) {
  const t = useT();
  const update = (patch: Partial<AddressChoiceValue>) =>
    onChange({ ...value, ...patch });

  return (
    <fieldset
      id="address_id"
      tabIndex={-1}
      aria-describedby={errors.address_id ? "address_id-error" : undefined}
      aria-invalid={errors.address_id ? true : undefined}
      className="space-y-3 outline-none"
    >
      <legend className="display text-ink text-2xl">
        {t("checkout.addressChoice.legend")}
      </legend>
      <p className="text-ink-soft text-sm">{t("checkout.addressChoice.hint")}</p>

      {addresses.map((address) => {
        const checked =
          value.choice === "saved" && value.addressId === address.id;
        const inputId = `address-saved-${address.id}`;

        return (
          <label
            key={address.id}
            htmlFor={inputId}
            className={cn("flex cursor-pointer gap-4", cardClasses(checked))}
          >
            <input
              id={inputId}
              type="radio"
              name="address_choice"
              checked={checked}
              onChange={() => update({ choice: "saved", addressId: address.id })}
              className="accent-ink mt-1 size-5 shrink-0"
            />
            <span className="min-w-0 flex-1 text-sm leading-relaxed">
              <span className="text-ink block font-semibold">
                {address.label ?? address.recipientName}
                {address.isDefault && (
                  <span className="meta bg-signal text-ink ml-3 rounded-full px-2.5 py-0.5">
                    {t("account.addresses.default")}
                  </span>
                )}
              </span>
              {address.label && (
                <span className="text-ink-soft block">
                  {address.recipientName}, {address.phone}
                </span>
              )}
              <span className="text-ink-soft block">
                {address.street}, {address.postalCode} {address.city}
              </span>
            </span>
          </label>
        );
      })}

      {errors.address_id && (
        <p id="address_id-error" className="text-destructive text-sm">
          {errors.address_id}
        </p>
      )}

      <div className={cardClasses(value.choice === "new")}>
        <label htmlFor="address-new" className="flex cursor-pointer gap-4">
          <input
            id="address-new"
            type="radio"
            name="address_choice"
            checked={value.choice === "new"}
            onChange={() => update({ choice: "new", addressId: null })}
            className="accent-ink mt-1 size-5 shrink-0"
          />
          <span className="text-ink font-semibold">
            {t("checkout.addressChoice.newOption")}
          </span>
        </label>

        {value.choice === "new" && (
          <div className="space-y-5 pl-9">
            <Field
              id="new_street"
              label={t("account.addresses.street")}
              error={errors.new_street}
              required
            >
              {(control) => (
                <Input
                  {...control}
                  value={value.street}
                  onChange={(event) => update({ street: event.target.value })}
                  autoComplete="street-address"
                />
              )}
            </Field>
            <div className="grid gap-5 sm:grid-cols-2">
              <Field
                id="new_city"
                label={t("account.addresses.city")}
                error={errors.new_city}
                required
              >
                {(control) => (
                  <Input
                    {...control}
                    value={value.city}
                    onChange={(event) => update({ city: event.target.value })}
                    autoComplete="address-level2"
                  />
                )}
              </Field>
              <Field
                id="new_postal_code"
                label={t("account.addresses.postalCode")}
                error={errors.new_postal_code}
                required
              >
                {(control) => (
                  <Input
                    {...control}
                    value={value.postalCode}
                    onChange={(event) =>
                      update({ postalCode: event.target.value })
                    }
                    autoComplete="postal-code"
                  />
                )}
              </Field>
            </div>
            <p className="text-ink-soft text-sm">
              {t("checkout.addressChoice.newHint")}
            </p>
            <div className="flex items-start gap-3">
              <input
                id="save_address"
                type="checkbox"
                checked={value.save}
                onChange={(event) => update({ save: event.target.checked })}
                className="accent-ink size-5 shrink-0"
              />
              <label htmlFor="save_address" className="text-ink text-sm">
                {t("checkout.addressChoice.save")}
              </label>
            </div>
          </div>
        )}
      </div>

      <div className={cardClasses(value.choice === "text")}>
        <label htmlFor="address-text" className="flex cursor-pointer gap-4">
          <input
            id="address-text"
            type="radio"
            name="address_choice"
            checked={value.choice === "text"}
            onChange={() => update({ choice: "text", addressId: null })}
            className="accent-ink mt-1 size-5 shrink-0"
          />
          <span className="text-ink font-semibold">
            {t("checkout.addressChoice.textOption")}
          </span>
        </label>

        {value.choice === "text" && (
          <div className="space-y-2 pl-9">
            <Field
              id="delivery_address"
              label={t("checkout.address")}
              error={errors.delivery_address}
              required
            >
              {(control) => (
                <Textarea
                  {...control}
                  rows={3}
                  value={value.text}
                  onChange={(event) => update({ text: event.target.value })}
                />
              )}
            </Field>
            <p className="text-ink-soft text-sm">{t("checkout.addressHint")}</p>
          </div>
        )}
      </div>
    </fieldset>
  );
}
