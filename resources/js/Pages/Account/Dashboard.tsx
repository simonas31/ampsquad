import { Link, usePage } from "@inertiajs/react";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { Button } from "@/components/ui/button";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb } from "@/types";

interface DashboardProps {
  breadcrumbs: Breadcrumb[];
}

/**
 * The customer's account. For now it only greets them and signs them out;
 * the sections of the account area are added to it as they are built.
 */
export default function Dashboard({ breadcrumbs }: DashboardProps) {
  const t = useT();
  const { account } = usePage().props;

  return (
    <>
      <PageHeader
        breadcrumbs={breadcrumbs}
        title={t("account.greeting", {
          name: account.customer?.fullName ?? "",
        })}
        description={t("account.loggedInAs", {
          email: account.customer?.email ?? "",
        })}
        size="narrow"
      />

      <Container size="narrow" className="py-14 lg:py-20">
        <Button asChild variant="outline">
          <Link href={account.logoutUrl} method="post" as="button">
            {t("common.logOut")}
          </Link>
        </Button>
      </Container>
    </>
  );
}
