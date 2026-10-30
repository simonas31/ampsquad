import { AccountPage } from "@/components/account/AccountPage";
import { AddressForm } from "@/components/account/AddressForm";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb, CustomerAddress } from "@/types";

interface EditProps {
  address: CustomerAddress;
  indexUrl: string;
  breadcrumbs: Breadcrumb[];
}

/**
 * The form for changing an address. Orders already placed keep the address
 * they were sent to, so nothing here reaches back into them.
 */
export default function Edit({ address, indexUrl, breadcrumbs }: EditProps) {
  const t = useT();

  return (
    <AccountPage
      breadcrumbs={breadcrumbs}
      title={t("account.addresses.editTitle")}
      description={t("account.addresses.formIntro")}
    >
      <AddressForm
        initial={{
          label: address.label ?? "",
          recipientName: address.recipientName,
          phone: address.phone,
          street: address.street,
          city: address.city,
          postalCode: address.postalCode,
          makeDefault: false,
        }}
        method="put"
        url={address.updateUrl}
        cancelUrl={indexUrl}
        isDefault={address.isDefault}
      />
    </AccountPage>
  );
}
