import { Link, usePage } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { useState } from "react";
import { AccountPage } from "@/components/account/AccountPage";
import { Button } from "@/components/ui/button";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb, CustomerAddress } from "@/types";

interface IndexProps {
  addresses: CustomerAddress[];
  limit: number;
  createUrl: string;
  breadcrumbs: Breadcrumb[];
}

/**
 * The customer's saved delivery addresses, the default first. Each can be
 * changed, made the default or deleted; deleting asks once more, in place.
 */
export default function Index({
  addresses,
  limit,
  createUrl,
  breadcrumbs,
}: IndexProps) {
  const t = useT();
  const { flash } = usePage();
  const atLimit = addresses.length >= limit;

  const statusMessages: Partial<Record<string, string>> = {
    "address-saved": t("account.addresses.saved"),
    "address-deleted": t("account.addresses.deleted"),
    "address-default-set": t("account.addresses.defaultSet"),
  };
  const statusMessage = flash.accountStatus
    ? statusMessages[flash.accountStatus]
    : undefined;

  return (
    <AccountPage
      breadcrumbs={breadcrumbs}
      title={t("account.addresses.title")}
      description={t("account.addresses.intro")}
    >
      {statusMessage && (
        <p
          role="status"
          className="border-signal text-ink mb-8 border-l-2 py-1 pl-4 text-sm"
        >
          {statusMessage}
        </p>
      )}

      {addresses.length === 0 ? (
        <div className="border-ink space-y-3 border-t py-10">
          <p className="display text-ink-soft max-w-[24ch] text-3xl">
            {t("account.addresses.empty")}
          </p>
          <p className="text-ink-soft">{t("account.addresses.emptyHint")}</p>
        </div>
      ) : (
        <ul className="space-y-5">
          {addresses.map((address) => (
            <li key={address.id}>
              <AddressCard address={address} />
            </li>
          ))}
        </ul>
      )}

      <div className="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3">
        {atLimit ? (
          <p className="text-ink-soft text-sm">
            {t("account.addresses.limitReached")}
          </p>
        ) : (
          <Button asChild variant="accent">
            <Link href={createUrl}>
              <Plus aria-hidden="true" />
              {t("account.addresses.add")}
            </Link>
          </Button>
        )}
        {addresses.length > 0 && (
          <p className="meta text-ink-soft">
            {t("account.addresses.count", {
              count: addresses.length,
              max: limit,
            })}
          </p>
        )}
      </div>
    </AccountPage>
  );
}

function AddressCard({ address }: { address: CustomerAddress }) {
  const t = useT();
  const [confirming, setConfirming] = useState(false);

  return (
    <article
      aria-label={address.label ?? address.recipientName}
      className="border-rule rounded-2xl border bg-white p-5 sm:p-6"
    >
      <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
        <h2 className="display text-ink text-xl">
          {address.label ?? address.recipientName}
        </h2>
        {address.isDefault && (
          <span className="meta bg-signal text-ink rounded-full px-3 py-1">
            {t("account.addresses.default")}
          </span>
        )}
      </div>

      <address className="text-ink mt-3 text-base leading-relaxed not-italic">
        {address.label && <div>{address.recipientName}</div>}
        <div>{address.phone}</div>
        <div>{address.street}</div>
        <div>
          {address.postalCode} {address.city}
        </div>
        <div>{address.countryName}</div>
      </address>

      {confirming ? (
        <div className="mt-5 flex flex-wrap items-center gap-3">
          <p role="alert" className="text-ink text-sm font-medium">
            {t("account.addresses.confirmDelete")}
          </p>
          <Button asChild size="sm" variant="outline">
            <Link
              href={address.deleteUrl}
              method="delete"
              as="button"
              preserveScroll
            >
              {t("account.addresses.confirmYes")}
            </Link>
          </Button>
          <Button
            type="button"
            size="sm"
            variant="ghost"
            onClick={() => setConfirming(false)}
          >
            {t("account.addresses.confirmNo")}
          </Button>
        </div>
      ) : (
        <div className="mt-5 flex flex-wrap items-center gap-3">
          <Button asChild size="sm" variant="outline">
            <Link href={address.editUrl}>{t("account.addresses.edit")}</Link>
          </Button>
          {!address.isDefault && (
            <Button asChild size="sm" variant="secondary">
              <Link
                href={address.defaultUrl}
                method="patch"
                as="button"
                preserveScroll
              >
                {t("account.addresses.makeDefault")}
              </Link>
            </Button>
          )}
          <Button
            type="button"
            size="sm"
            variant="ghost"
            onClick={() => setConfirming(true)}
          >
            {t("account.addresses.delete")}
          </Button>
        </div>
      )}
    </article>
  );
}
