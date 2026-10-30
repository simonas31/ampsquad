import { Link, usePage } from "@inertiajs/react";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";

const itemClasses =
  "block rounded-full px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors lg:rounded-xl lg:py-3";

/**
 * The sections of the account area: a row of pills that scrolls sideways on
 * a small screen, a column beside the page on a large one. Signing out is the
 * last entry, so it is always to hand.
 */
export function AccountNav() {
  const t = useT();
  const { props, url } = usePage();
  const { account } = props;
  const currentPath = url.split("?")[0].replace(/\/$/, "");

  const items = [
    { href: account.dashboardUrl, label: t("account.nav.overview") },
    { href: account.profileUrl, label: t("account.nav.profile") },
    { href: account.addressesUrl, label: t("account.nav.addresses") },
    { href: account.securityUrl, label: t("account.nav.security") },
  ];

  return (
    <nav aria-label={t("account.nav.label")}>
      <ul className="-mx-5 flex gap-2 overflow-x-auto px-5 pb-2 sm:-mx-8 sm:px-8 lg:mx-0 lg:flex-col lg:gap-1 lg:overflow-visible lg:px-0 lg:pb-0">
        {items.map((item) => {
          const path = item.href.replace(/\/$/, "");
          // The overview matches only itself; the other sections also match
          // their own pages (the form for a new address is still "Addresses").
          const current =
            currentPath === path ||
            (item.href !== account.dashboardUrl &&
              currentPath.startsWith(`${path}/`));

          return (
            <li key={item.href}>
              <Link
                href={item.href}
                aria-current={current ? "page" : undefined}
                className={cn(
                  itemClasses,
                  current
                    ? "bg-ink text-bone"
                    : "text-ink-soft hover:bg-concrete hover:text-ink",
                )}
              >
                {item.label}
              </Link>
            </li>
          );
        })}
        <li className="lg:border-rule lg:mt-4 lg:border-t lg:pt-4">
          <Link
            href={account.logoutUrl}
            method="post"
            as="button"
            className={cn(
              itemClasses,
              "text-ink-soft hover:bg-concrete hover:text-ink lg:w-full lg:text-left",
            )}
          >
            {t("common.logOut")}
          </Link>
        </li>
      </ul>
    </nav>
  );
}
