import { ContactDetails } from "@/components/contact/ContactDetails";
import { InquiryForm } from "@/components/contact/InquiryForm";
import { Reveal } from "@/components/common/Reveal";
import { Container } from "@/components/layout/Container";
import { useT } from "@/hooks/use-t";
import type { SiteData } from "@/types";

interface InquirySectionProps {
  title: string;
  contactUrl: string;
  contact: SiteData["contact"];
}

/**
 * The page turns graphite here and stays that way through the footer. It is
 * the only tonal switch on the site, used once, at the point where reading
 * is supposed to become doing. The form sits on a plaster panel inside it,
 * both because a form needs the most legible surface available and because
 * it makes the enquiry read as a sheet handed across a dark desk.
 */
export function InquirySection({
  title,
  contactUrl,
  contact,
}: InquirySectionProps) {
  const t = useT();

  return (
    <section className="on-ink bg-ink text-bone pt-20 lg:pt-28">
      <Container size="wide">
        <div className="grid grid-cols-1 gap-x-10 gap-y-12 lg:grid-cols-12">
          <div className="lg:col-span-5">
            <Reveal>
              <h2 className="display text-bone text-[clamp(2.25rem,4.2vw,3.75rem)]">
                {title}
              </h2>
              <p className="text-bone-soft mt-6 max-w-[38ch] text-lg text-pretty">
                {t("contact.intro")}
              </p>
              <ContactDetails
                contact={contact}
                tone="dark"
                className="border-bone-soft/30 mt-12 border-t pt-10"
              />
            </Reveal>
          </div>

          <Reveal delay={100} className="lg:col-span-6 lg:col-start-7">
            <div className="bg-paper text-ink p-6 sm:p-10">
              <InquiryForm action={contactUrl} />
            </div>
          </Reveal>
        </div>
      </Container>
    </section>
  );
}
