import { Link, useForm, usePage } from "@inertiajs/react";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb } from "@/types";

interface ResetPasswordProps {
  token: string;
  email: string;
  submitUrl: string;
  breadcrumbs: Breadcrumb[];
}

/** Must match Customer::PASSWORD_MIN_LENGTH on the server. */
const PASSWORD_MIN_LENGTH = 10;

/**
 * The page the link in the mail opens: the address (from the link, shown but
 * not editable), and the new password to choose. A link that has run out or
 * been used gets the server's answer under the address, with a way to ask
 * for another.
 */
export default function ResetPassword({
  token,
  email,
  submitUrl,
  breadcrumbs,
}: ResetPasswordProps) {
  const t = useT();
  const { account } = usePage().props;

  const form = useForm({
    token,
    email,
    password: "",
    password_confirmation: "",
  });

  const errors: Record<string, string | undefined> = form.errors;

  function submit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    form.post(submitUrl, {
      onError: (errors) => {
        const firstInvalid = (
          ["email", "password", "password_confirmation"] as const
        ).find((name) => errors[name]);

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
        title={t("auth.reset.title")}
        description={t("auth.reset.intro")}
        size="narrow"
      />

      <Container size="narrow" className="py-14 lg:py-20">
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
          <Field id="email" label={t("auth.email")} error={form.errors.email}>
            {(control) => (
              <Input
                {...control}
                type="email"
                value={form.data.email}
                readOnly
                autoComplete="username"
              />
            )}
          </Field>

          <div className="space-y-2">
            <Field
              id="password"
              label={t("auth.reset.password")}
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
            label={t("auth.reset.passwordConfirmation")}
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

          <div className="flex flex-wrap items-center gap-x-6 gap-y-3 pt-1">
            <Button
              type="submit"
              variant="accent"
              size="lg"
              disabled={form.processing}
            >
              {form.processing
                ? t("auth.reset.submitting")
                : t("auth.reset.submit")}
            </Button>
            <p className="meta text-ink-soft">{t("auth.requiredHint")}</p>
          </div>
        </form>

        <p className="mt-12 text-sm">
          <Link
            href={account.loginUrl}
            className="text-ink underline underline-offset-4"
          >
            {t("auth.forgot.backToLogin")}
          </Link>
        </p>
      </Container>
    </>
  );
}
