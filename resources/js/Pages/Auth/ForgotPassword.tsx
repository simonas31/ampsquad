import { Link, useForm, usePage } from "@inertiajs/react";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb } from "@/types";

interface ForgotPasswordProps {
  submitUrl: string;
  breadcrumbs: Breadcrumb[];
}

/**
 * Asks for the address of an account and has the server mail a link. The
 * answer is the same whether or not the address has an account, so the page
 * only ever says that a link was sent if there is one.
 */
export default function ForgotPassword({
  submitUrl,
  breadcrumbs,
}: ForgotPasswordProps) {
  const t = useT();
  const { props, flash } = usePage();
  const { account } = props;

  const form = useForm({ email: "" });
  const errors: Record<string, string | undefined> = form.errors;

  function submit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    form.post(submitUrl, {
      preserveScroll: true,
      onError: (errors) => {
        if (errors.email) {
          document.getElementById("email")?.focus();
        }
      },
    });
  }

  return (
    <>
      <PageHeader
        breadcrumbs={breadcrumbs}
        title={t("auth.forgot.title")}
        description={t("auth.forgot.intro")}
        size="narrow"
      />

      <Container size="narrow" className="py-14 lg:py-20">
        {flash.accountStatus === "reset-link-sent" && (
          <p
            role="status"
            className="border-signal text-ink mb-8 border-l-2 py-1 pl-4 text-sm"
          >
            {t("auth.forgot.sent")}
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

          <div className="flex flex-wrap items-center gap-x-6 gap-y-3 pt-1">
            <Button
              type="submit"
              variant="accent"
              size="lg"
              disabled={form.processing}
            >
              {form.processing
                ? t("auth.forgot.submitting")
                : t("auth.forgot.submit")}
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
