import { Link, useForm, usePage } from "@inertiajs/react";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb } from "@/types";

interface RegisterProps {
  submitUrl: string;
  breadcrumbs: Breadcrumb[];
}

/** The order the fields appear in, which is the order focus goes to the first mistake. */
const fieldOrder = [
  "full_name",
  "email",
  "phone",
  "password",
  "password_confirmation",
] as const;

/** Must match Customer::PASSWORD_MIN_LENGTH on the server. */
const PASSWORD_MIN_LENGTH = 10;

/**
 * Customer registration. The account is made at once and the customer is
 * signed in, but its area stays closed until they follow the link in the
 * mail the server sends.
 */
export default function Register({ submitUrl, breadcrumbs }: RegisterProps) {
  const t = useT();
  const { account } = usePage().props;

  const form = useForm({
    full_name: "",
    email: "",
    phone: "",
    password: "",
    password_confirmation: "",
    marketing_consent: false,
    company: "", // honeypot: real visitors never see or fill this in
  });

  const errors: Record<string, string | undefined> = form.errors;

  function submit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    form.post(submitUrl, {
      onError: (errors) => {
        const firstInvalid = fieldOrder.find((name) => errors[name]);

        if (firstInvalid) {
          document.getElementById(firstInvalid)?.focus();
        }
      },
      onFinish: () => form.reset("password", "password_confirmation"),
    });
  }

  return (
    <>
      <PageHeader
        breadcrumbs={breadcrumbs}
        title={t("auth.register.title")}
        description={t("auth.register.intro")}
        size="narrow"
      />

      <Container size="narrow" className="py-14 lg:py-20">
        <div role="alert">
          {errors.page_expired && (
            <p className="text-destructive mb-8 text-sm">
              {t("auth.pageExpired")}
            </p>
          )}
        </div>

        <form
          className="space-y-7"
          onSubmit={submit}
          aria-busy={form.processing}
          noValidate
        >
          <Field
            id="full_name"
            label={t("auth.register.fullName")}
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

          <div className="grid gap-7 sm:grid-cols-2">
            <Field
              id="email"
              label={t("auth.email")}
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
              label={`${t("auth.register.phone")} (${t("auth.register.optional")})`}
              error={form.errors.phone}
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

          <div className="space-y-2">
            <Field
              id="password"
              label={t("auth.password")}
              error={form.errors.password}
              required
            >
              {(control) => (
                <Input
                  {...control}
                  type="password"
                  value={form.data.password}
                  onChange={(event) =>
                    form.setData("password", event.target.value)
                  }
                  autoComplete="new-password"
                />
              )}
            </Field>
            <p className="text-ink-soft text-sm">
              {t("auth.register.passwordHint", { count: PASSWORD_MIN_LENGTH })}
            </p>
          </div>

          <Field
            id="password_confirmation"
            label={t("auth.register.passwordConfirmation")}
            error={form.errors.password_confirmation}
            required
          >
            {(control) => (
              <Input
                {...control}
                type="password"
                value={form.data.password_confirmation}
                onChange={(event) =>
                  form.setData("password_confirmation", event.target.value)
                }
                autoComplete="new-password"
              />
            )}
          </Field>

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
              {t("auth.register.marketing")}
            </label>
          </div>

          {/* Honeypot: hidden from real visitors, bots fill it in blindly. */}
          <div className="sr-only" aria-hidden="true">
            <label htmlFor="company">Company</label>
            <input
              id="company"
              type="text"
              tabIndex={-1}
              autoComplete="off"
              value={form.data.company}
              onChange={(event) => form.setData("company", event.target.value)}
            />
          </div>

          <div className="flex flex-wrap items-center gap-x-6 gap-y-3 pt-1">
            <Button
              type="submit"
              variant="accent"
              size="lg"
              disabled={form.processing}
            >
              {form.processing
                ? t("auth.register.submitting")
                : t("auth.register.submit")}
            </Button>
            <p className="meta text-ink-soft">{t("auth.requiredHint")}</p>
          </div>
        </form>

        <p className="text-ink-soft mt-12 text-sm">
          {t("auth.register.haveAccount")}{" "}
          <Link
            href={account.loginUrl}
            className="text-ink underline underline-offset-4"
          >
            {t("auth.register.logIn")}
          </Link>
        </p>
      </Container>
    </>
  );
}
