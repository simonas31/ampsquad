import { Link, usePage } from "@inertiajs/react";
import {
  KeyRound,
  LayoutDashboard,
  Loader2,
  LogOut,
  MapPin,
  Package,
  UserRound,
  type LucideIcon,
} from "lucide-react";
import { useLogout } from "@/hooks/use-logout";
import { usePendingPath } from "@/hooks/use-pending-path";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";

const itemClasses =
  "flex items-center gap-2.5 rounded-full px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors lg:rounded-xl lg:py-3";

/**
 * The sections of the account area: a row of pills that scrolls sideways on
 * a small screen, a column beside the page on a large one. Each has an icon,
 * which turns into a spinner while the page it leads to is loading, so a
 * press is seen to have worked. Signing out is the last entry, so it is
 * always to hand.
 */
export function AccountNav() {
  const t = useT();
  const { props, url } = usePage();
  const { account } = props;
  const pendingPath = usePendingPath();
  const { logout, processing: loggingOut } = useLogout();
  const currentPath = url.split("?")[0].replace(/\/$/, "");

  const items: Array<{ href: string; label: string; Icon: LucideIcon }> = [
    {
      href: account.dashboardUrl,
      label: t("account.nav.overview"),
      Icon: LayoutDashboard,
    },
    { href: account.ordersUrl, label: t("account.nav.orders"), Icon: Package },
    {
      href: account.profileUrl,
      label: t("account.nav.profile"),
      Icon: UserRound,
    },
    {
      href: account.addressesUrl,
      label: t("account.nav.addresses"),
      Icon: MapPin,
    },
    {
      href: account.securityUrl,
      label: t("account.nav.security"),
      Icon: KeyRound,
    },
  ];

  return (
    <nav aria-label={t("account.nav.label")}>
      <ul className="-mx-5 flex gap-2 overflow-x-auto px-5 pb-2 sm:-mx-8 sm:px-8 lg:mx-0 lg:flex-col lg:gap-1 lg:overflow-visible lg:px-0 lg:pb-0">
        {items.map(({ href, label, Icon }) => {
          const path = href.replace(/\/$/, "");
          // The overview matches only itself; the other sections also match
          // their own pages (the form for a new address is still "Addresses").
          const current =
            currentPath === path ||
            (href !== account.dashboardUrl &&
              currentPath.startsWith(`${path}/`));
          const loading = pendingPath === path;

          return (
            <li key={href}>
              <Link
                href={href}
                aria-current={current ? "page" : undefined}
                aria-busy={loading || undefined}
                className={cn(
                  itemClasses,
                  current
                    ? "bg-ink text-bone"
                    : "text-ink-soft hover:bg-concrete hover:text-ink",
                )}
              >
                {loading ? (
                  <Loader2
                    className="size-4 shrink-0 animate-spin"
                    aria-hidden="true"
                  />
                ) : (
                  <Icon className="size-4 shrink-0" aria-hidden="true" />
                )}
                {label}
                {loading && (
                  <span className="sr-only">{t("common.loading")}</span>
                )}
              </Link>
            </li>
          );
        })}
        <li className="lg:border-rule lg:mt-4 lg:border-t lg:pt-4">
          <button
            type="button"
            onClick={logout}
            disabled={loggingOut}
            aria-busy={loggingOut || undefined}
            className={cn(
              itemClasses,
              "text-ink-soft hover:bg-concrete hover:text-ink w-full disabled:cursor-wait disabled:opacity-70 lg:text-left",
            )}
          >
            {loggingOut ? (
              <Loader2
                className="size-4 shrink-0 animate-spin"
                aria-hidden="true"
              />
            ) : (
              <LogOut className="size-4 shrink-0" aria-hidden="true" />
            )}
            {t("common.logOut")}
            {loggingOut && (
              <span className="sr-only">{t("common.loading")}</span>
            )}
          </button>
        </li>
      </ul>
    </nav>
  );
}
