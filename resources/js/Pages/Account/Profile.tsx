import { Link, useForm, usePage } from "@inertiajs/react";
import { AccountPage } from "@/components/account/AccountPage";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb } from "@/types";

interface ProfileProps {
  profile: {
    fullName: string;
    phone: string | null;
    locale: string;
    marketingConsent: boolean;
  };
  updateUrl: string;
  breadcrumbs: Breadcrumb[];
}

/** The order the fields appear in, which is the order focus goes to the first mistake. */
const fieldOrder = ["full_name", "phone", "locale"] as const;

/**
 * What the customer can change about themselves: their name, their phone
 * number, the language we write to them in, and whether we may send them
 * news. The email address has its own form, on the security page.
 */
export default function Profile({
  profile,
  updateUrl,
  breadcrumbs,
}: ProfileProps) {
  const t = useT();
  const { props, flash } = usePage();
  const { locale, account } = props;

  const form = useForm({
    full_name: profile.fullName,
    phone: profile.phone ?? "",
    locale: profile.locale,
    marketing_consent: profile.marketingConsent,
  });

  const errors: Record<string, string | undefined> = form.errors;

  function submit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    form.patch(updateUrl, {
      preserveScroll: true,
      onError: (errors) => {
        const firstInvalid = fieldOrder.find((name) => errors[name]);

        if (firstInvalid) {
          document.getElementById(firstInvalid)?.focus();
        }
      },
    });
  }

  return (
    <AccountPage
      breadcrumbs={breadcrumbs}
      title={t("account.profile.title")}
      description={t("account.profile.intro")}
    >
      {flash.accountStatus === "profile-updated" && (
        <p
          role="status"
          className="border-signal text-ink mb-8 border-l-2 py-1 pl-4 text-sm"
        >
          {t("account.profile.saved")}
        </p>
      )}

      {errors.page_expired && (
        <p role="alert" className="text-destructive mb-8 text-sm">
          {t("auth.pageExpired")}
        </p>
      )}

      <form
        className="space-y-7"
        onSubmit={submit}
        aria-busy={form.processing}
        noValidate
      >
        <Field
          id="full_name"
          label={t("account.profile.fullName")}
          error={form.errors.full_name}
          required
        >
          {(control) => (
            <Input
              {...control}
              value={form.data.full_name}
              onChange={(event) =>
                form.setData("full_name", event.target.value)
              }
              autoComplete="name"
            />
          )}
        </Field>

        <Field
          id="phone"
          label={`${t("account.profile.phone")} (${t("account.profile.optional")})`}
          error={form.errors.phone}
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

        <div className="space-y-2">
          <Field
            id="locale"
            label={t("account.profile.language")}
            error={form.errors.locale}
            required
          >
            {(control) => (
              <Select
                {...control}
                value={form.data.locale}
                onChange={(event) => form.setData("locale", event.target.value)}
              >
                {locale.available.map((option) => (
                  <option key={option.code} value={option.code}>
                    {option.name}
                  </option>
                ))}
              </Select>
            )}
          </Field>
          <p className="text-ink-soft text-sm">
            {t("account.profile.languageHint")}
          </p>
        </div>

        <div className="flex items-start gap-3">
          <input
            id="marketing_consent"
            type="checkbox"
            checked={form.data.marketing_consent}
            onChange={(event) =>
              form.setData("marketing_consent", event.target.checked)
            }
            className="accent-ink size-5 shrink-0"
          />
          <label htmlFor="marketing_consent" className="text-ink text-sm">
            {t("account.profile.marketing")}
          </label>
        </div>

        <p className="text-ink-soft text-sm">
          {t("account.profile.emailNote")}{" "}
          <Link
            href={account.securityUrl}
            className="text-ink underline underline-offset-4"
          >
            {t("account.nav.security")}
          </Link>
        </p>

        <div className="flex flex-wrap items-center gap-x-6 gap-y-3 pt-1">
          <Button type="submit" disabled={form.processing}>
            {form.processing
              ? t("account.profile.saving")
              : t("account.profile.save")}
          </Button>
          <p className="meta text-ink-soft">{t("auth.requiredHint")}</p>
        </div>
      </form>
    </AccountPage>
  );
}
