import { AccountPage } from "@/components/account/AccountPage";
import { AddressForm } from "@/components/account/AddressForm";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb } from "@/types";

interface CreateProps {
  /** The customer's own name and phone, as a start: most addresses are their own. */
  initial: { recipientName: string; phone: string | null };
  /** The first address a customer saves becomes their default whatever they tick. */
  isFirst: boolean;
  storeUrl: string;
  indexUrl: string;
  breadcrumbs: Breadcrumb[];
}

/**
 * The form for a new address.
 */
export default function Create({
  initial,
  isFirst,
  storeUrl,
  indexUrl,
  breadcrumbs,
}: CreateProps) {
  const t = useT();

  return (
    <AccountPage
      breadcrumbs={breadcrumbs}
      title={t("account.addresses.newTitle")}
      description={t("account.addresses.formIntro")}
    >
      <AddressForm
        initial={{
          label: "",
          recipientName: initial.recipientName,
          phone: initial.phone ?? "",
          street: "",
          city: "",
          postalCode: "",
          makeDefault: isFirst,
        }}
        method="post"
        url={storeUrl}
        cancelUrl={indexUrl}
        isDefault={false}
      />
    </AccountPage>
  );
}
