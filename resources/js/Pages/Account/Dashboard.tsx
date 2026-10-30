import { Link, usePage } from "@inertiajs/react";
import { AccountPage } from "@/components/account/AccountPage";
import { Button } from "@/components/ui/button";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb } from "@/types";

interface DashboardProps {
  profile: {
    fullName: string;
    email: string;
    phone: string | null;
    locale: string;
  };
  breadcrumbs: Breadcrumb[];
}

/**
 * The customer's account at a glance: who they are to us. The sections of the
 * account area are beside it, and the things that happen in the shop (their
 * orders) are added here as they are built.
 */
export default function Dashboard({ profile, breadcrumbs }: DashboardProps) {
  const t = useT();
  const { props } = usePage();
  const { account, locale } = props;

  const language = locale.available.find(
    (option) => option.code === profile.locale,
  );

  const details = [
    { label: t("account.dashboard.name"), value: profile.fullName },
    { label: t("account.dashboard.email"), value: profile.email },
    {
      label: t("account.dashboard.phone"),
      value: profile.phone ?? t("account.dashboard.notGiven"),
    },
    {
      label: t("account.dashboard.language"),
      value: language?.name ?? profile.locale,
    },
  ];

  return (
    <AccountPage
      breadcrumbs={breadcrumbs}
      title={t("account.greeting", { name: profile.fullName })}
    >
      <section aria-labelledby="details-heading" className="space-y-6">
        <h2 id="details-heading" className="display text-ink text-2xl">
          {t("account.dashboard.details")}
        </h2>

        <dl className="border-rule divide-rule divide-y border-y">
          {details.map((detail) => (
            <div
              key={detail.label}
              className="grid gap-1 py-4 sm:grid-cols-3 sm:gap-6"
            >
              <dt className="meta text-ink-soft">{detail.label}</dt>
              <dd className="text-ink break-words sm:col-span-2">
                {detail.value}
              </dd>
            </div>
          ))}
        </dl>

        <Button asChild variant="outline">
          <Link href={account.profileUrl}>
            {t("account.dashboard.editProfile")}
          </Link>
        </Button>
      </section>
    </AccountPage>
  );
}
