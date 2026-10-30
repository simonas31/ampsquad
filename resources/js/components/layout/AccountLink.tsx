import { Link, usePage } from "@inertiajs/react";
import { UserRound } from "lucide-react";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";

/**
 * The header's way into the customer's account, or to the login page for a
 * visitor who is not signed in.
 */
export function AccountLink({ className }: { className?: string }) {
  const t = useT();
  const { account } = usePage().props;
  const label = account.customer ? t("common.myAccount") : t("common.logIn");

  return (
    <Link
      href={account.customer ? account.dashboardUrl : account.loginUrl}
      aria-label={label}
      title={label}
      className={cn(
        "text-ink hover:bg-concrete relative inline-flex size-11 shrink-0 items-center justify-center rounded-full transition-colors",
        className,
      )}
    >
      <UserRound className="size-5" aria-hidden="true" />
      {account.customer && (
        <span
          aria-hidden="true"
          className="bg-signal absolute top-2.5 right-2.5 size-2 rounded-full"
        />
      )}
    </Link>
  );
}
