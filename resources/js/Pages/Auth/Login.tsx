import { Link, useForm, usePage } from "@inertiajs/react";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb } from "@/types";

interface LoginProps {
  submitUrl: string;
  forgotPasswordUrl: string;
  breadcrumbs: Breadcrumb[];
}

/**
 * Customer login. A wrong email or password gets the same answer, so the
 * form never tells whether an address has an account. The password is
 * cleared after every try; the email stays.
 */
export default function Login({
  submitUrl,
  forgotPasswordUrl,
  breadcrumbs,
}: LoginProps) {
  const t = useT();
  const { props, flash } = usePage();
  const { account } = props;

  const form = useForm({ email: "", password: "", remember: false });

  // Besides the form's own fields, the server answers a session that ran out
  // under another name (page_expired).
  const errors: Record<string, string | undefined> = form.errors;

  // What happened elsewhere that sent the customer here to log in.
  const statusMessages: Partial<Record<string, string>> = {
    "email-verified": t("auth.login.emailVerified"),
    "password-reset": t("auth.login.passwordReset"),
    "password-changed": t("auth.login.passwordChanged"),
    "email-changed": t("auth.login.emailChanged"),
    "email-change-failed": t("auth.login.emailChangeFailed"),
  };
  const statusMessage = flash.accountStatus
    ? statusMessages[flash.accountStatus]
    : undefined;

  function submit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    form.post(submitUrl, {
      onError: (errors) => {
        // Send keyboard and screen reader focus to the first field that failed.
        const firstInvalid = (["email", "password"] as const).find(
          (name) => errors[name],
        );

        if (firstInvalid) {
          document.getElementById(firstInvalid)?.focus();
        }
      },
      onFinish: () => form.reset("password"),
    });
  }

  return (
    <>
      <PageHeader
        breadcrumbs={breadcrumbs}
        title={t("auth.login.title")}
        description={t("auth.login.intro")}
        size="narrow"
      />

      <Container size="narrow" className="py-14 lg:py-20">
        <div role="status">
          {statusMessage && (
            <p className="border-signal text-ink mb-8 border-l-2 py-1 pl-4 text-sm">
              {statusMessage}
            </p>
          )}
        </div>

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
                onChange={(event) => form.setData("email", event.target.value)}
                autoComplete="email"
              />
            )}
          </Field>

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
                  autoComplete="current-password"
                />
              )}
            </Field>
            <p className="text-sm">
              <Link
                href={forgotPasswordUrl}
                className="text-ink-soft hover:text-ink underline underline-offset-4"
              >
                {t("auth.login.forgot")}
              </Link>
            </p>
          </div>

          <div className="flex items-center gap-3">
            <input
              id="remember"
              type="checkbox"
              checked={form.data.remember}
              onChange={(event) =>
                form.setData("remember", event.target.checked)
              }
              className="accent-ink size-5 shrink-0"
            />
            <label htmlFor="remember" className="text-ink text-sm">
              {t("auth.login.remember")}
            </label>
          </div>

          <div className="flex flex-wrap items-center gap-x-6 gap-y-3 pt-1">
            <Button
              type="submit"
              variant="accent"
              size="lg"
              disabled={form.processing}
            >
              {form.processing
                ? t("auth.login.submitting")
                : t("auth.login.submit")}
            </Button>
            <p className="meta text-ink-soft">{t("auth.requiredHint")}</p>
          </div>
        </form>

        <p className="text-ink-soft mt-12 text-sm">
          {t("auth.login.noAccount")}{" "}
          <Link
            href={account.registerUrl}
            className="text-ink underline underline-offset-4"
          >
            {t("auth.login.createAccount")}
          </Link>
        </p>
      </Container>
    </>
  );
}
