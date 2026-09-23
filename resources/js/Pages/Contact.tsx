import { usePage } from "@inertiajs/react";
import { Reveal } from "@/components/common/Reveal";
import { ContactDetails } from "@/components/contact/ContactDetails";
import { InquiryForm } from "@/components/contact/InquiryForm";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb } from "@/types";

export default function Contact({
  breadcrumbs,
}: {
  breadcrumbs: Breadcrumb[];
}) {
  const t = useT();
  const {
    url,
    props: { site },
  } = usePage();

  return (
    <>
      <PageHeader
        breadcrumbs={breadcrumbs}
        title={t("nav.contact")}
        description={t("contact.intro")}
      />

      <Container size="wide" className="py-16 lg:py-24">
        <div className="grid grid-cols-1 gap-x-10 gap-y-14 lg:grid-cols-12">
          <Reveal className="lg:col-span-7">
            <InquiryForm action={url.split("?")[0]} />
          </Reveal>

          <Reveal delay={100} className="lg:col-span-4 lg:col-start-9">
            <h2 className="display text-ink border-ink border-t pt-8 text-2xl">
              {t("contact.details")}
            </h2>
            <ContactDetails contact={site.contact} className="mt-10" />
          </Reveal>
        </div>
      </Container>
    </>
  );
}
