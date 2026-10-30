import { Link, useForm, usePage } from "@inertiajs/react";
import { AccountPage } from "@/components/account/AccountPage";
import { OrderRow } from "@/components/account/OrderRow";
import { Pagination } from "@/components/content/Pagination";
import { Button } from "@/components/ui/button";
import { useNavUrl } from "@/hooks/use-nav-url";
import { useT } from "@/hooks/use-t";
import type { AccountOrderSummary, Breadcrumb, Paginated } from "@/types";

interface IndexProps {
  orders: Paginated<AccountOrderSummary>;
  /** Guest orders placed with the account's address that are not in it yet. */
  linkableCount: number;
  linkUrl: string;
  breadcrumbs: Breadcrumb[];
}

/**
 * The customer's orders, newest first. Where an order still waits for its
 * payment and the time to pay is open, the way to pay is on its row. Earlier
 * guest orders placed with the account's address can be added to it on
 * request.
 */
export default function Index({
  orders,
  linkableCount,
  linkUrl,
  breadcrumbs,
}: IndexProps) {
  const t = useT();
  const { props, flash } = usePage();
  const { account, locale } = props;
  const shopUrl = useNavUrl("nav.shop", "/shop");
  const linkForm = useForm({});

  return (
    <AccountPage
      breadcrumbs={breadcrumbs}
      title={t("account.orders.title")}
      description={t("account.orders.intro")}
    >
      {flash.accountStatus === "orders-linked" && (
        <p
          role="status"
          className="border-signal text-ink mb-8 border-l-2 py-1 pl-4 text-sm"
        >
          {flash.linkedOrders
            ? t("account.orders.linked", { count: flash.linkedOrders })
            : t("account.orders.linkedNone")}
        </p>
      )}

      {linkableCount > 0 && (
        <section
          aria-labelledby="link-heading"
          className="border-rule mb-8 space-y-3 rounded-2xl border bg-white p-5 sm:p-6"
        >
          <h2 id="link-heading" className="display text-ink text-xl">
            {t("account.orders.linkTitle")}
          </h2>
          <p className="text-ink-soft max-w-[60ch]">
            {t("account.orders.linkText", {
              count: linkableCount,
              email: account.customer?.email ?? "",
            })}
          </p>
          <Button
            type="button"
            variant="outline"
            disabled={linkForm.processing}
            onClick={() => linkForm.post(linkUrl)}
          >
            {linkForm.processing
              ? t("account.orders.linking")
              : t("account.orders.linkButton")}
          </Button>
        </section>
      )}

      {orders.data.length === 0 ? (
        <div className="border-ink space-y-4 border-t py-10">
          <p className="display text-ink-soft max-w-[24ch] text-3xl">
            {t("account.orders.empty")}
          </p>
          <p className="text-ink-soft">{t("account.orders.emptyHint")}</p>
          <Button asChild className="mt-2">
            <Link href={shopUrl}>{t("account.orders.browseShop")}</Link>
          </Button>
        </div>
      ) : (
        <ul className="space-y-4">
          {orders.data.map((order) => (
            <li key={order.number}>
              <OrderRow order={order} localeCode={locale.current} />
            </li>
          ))}
        </ul>
      )}

      <div className="mt-10">
        <Pagination pagination={orders} />
      </div>
    </AccountPage>
  );
}
