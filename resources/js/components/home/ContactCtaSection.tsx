import { Link } from "@inertiajs/react";
import { Mail, MapPin, Phone } from "lucide-react";
import { Reveal } from "@/components/common/Reveal";
import { Button } from "@/components/ui/button";
import { telHref } from "@/lib/utils";
import type { SiteData } from "@/types";

interface ContactCtaSectionProps {
  title: string;
  buttonLabel: string;
  contactUrl: string;
  contact: SiteData["contact"];
}

export function ContactCtaSection({
  title,
  buttonLabel,
  contactUrl,
  contact,
}: ContactCtaSectionProps) {
  const contactItem = "flex items-center gap-3 text-sm font-medium";
  const iconWrapper =
    "bg-white/10 text-accent flex size-9 shrink-0 items-center justify-center rounded-full";

  return (
    <section className="px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
      <Reveal>
        <div className="surface-navy bg-navy text-navy-foreground relative mx-auto max-w-7xl overflow-hidden rounded-2xl px-6 py-14 sm:px-12 lg:py-16">
          <div
            className="bg-blueprint absolute inset-0 mask-[radial-gradient(ellipse_at_bottom_left,black,transparent_70%)]"
            aria-hidden="true"
          />
          <div className="relative grid gap-10 lg:grid-cols-[1.2fr_1fr] lg:items-center">
            <div>
              <span
                className="bg-accent mb-5 block h-1 w-12 rounded-full"
                aria-hidden="true"
              />
              <h2 className="max-w-xl text-3xl text-white sm:text-4xl">
                {title}
              </h2>
              <Button asChild variant="accent" size="lg" className="mt-8">
                <Link href={contactUrl}>{buttonLabel}</Link>
              </Button>
            </div>

            <address className="flex flex-col gap-4 not-italic">
              <a
                href={telHref(contact.phone)}
                className={`${contactItem} hover:underline`}
              >
                <span className={iconWrapper}>
                  <Phone className="size-4" aria-hidden="true" />
                </span>
                {contact.phone}
              </a>
              <a
                href={`mailto:${contact.email}`}
                className={`${contactItem} hover:underline`}
              >
                <span className={iconWrapper}>
                  <Mail className="size-4" aria-hidden="true" />
                </span>
                {contact.email}
              </a>
              <span className={contactItem}>
                <span className={iconWrapper}>
                  <MapPin className="size-4" aria-hidden="true" />
                </span>
                {contact.address}
              </span>
            </address>
          </div>
        </div>
      </Reveal>
    </section>
  );
}
