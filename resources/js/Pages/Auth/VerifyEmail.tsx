import { useForm, usePage } from "@inertiajs/react";
import { Loader2 } from "lucide-react";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { Button } from "@/components/ui/button";
import { useLogout } from "@/hooks/use-logout";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb } from "@/types";

interface VerifyEmailProps {
  resendUrl: string;
  breadcrumbs: Breadcrumb[];
}

/**
 * Where a customer waits after registering, until they follow the link in
 * the mail. They can ask for the link again, or sign out.
 */
export default function VerifyEmail({
  resendUrl,
  breadcrumbs,
}: VerifyEmailProps) {
  const t = useT();
  const { props, flash } = usePage();
  const { account } = props;
  const form = useForm({});
  const { logout, processing: loggingOut } = useLogout();

  return (
    <>
      <PageHeader
        breadcrumbs={breadcrumbs}
        title={t("auth.verify.title")}
        size="narrow"
      />

      <Container size="narrow" className="space-y-8 py-14 lg:py-20">
        <p className="text-ink max-w-[52ch] text-lg text-pretty">
          {t("auth.verify.text", { email: account.customer?.email ?? "" })}
        </p>
        <p className="text-ink-soft max-w-[52ch] text-pretty">
          {t("auth.verify.spam")}
        </p>

        {flash.accountStatus === "verification-link-sent" && (
          <p
            role="status"
            className="border-signal text-ink border-l-2 py-1 pl-4 text-sm"
          >
            {t("auth.verify.sent")}
          </p>
        )}

        <div className="flex flex-wrap items-center gap-4">
          <Button
            type="button"
            variant="accent"
            disabled={form.processing}
            onClick={() => form.post(resendUrl, { preserveScroll: true })}
          >
            {form.processing
              ? t("auth.verify.sending")
              : t("auth.verify.resend")}
          </Button>
          <Button
            type="button"
            variant="outline"
            disabled={loggingOut}
            aria-busy={loggingOut || undefined}
            onClick={logout}
          >
            {loggingOut && (
              <Loader2 className="animate-spin" aria-hidden="true" />
            )}
            {t("auth.verify.logOut")}
          </Button>
        </div>
      </Container>
    </>
  );
}
