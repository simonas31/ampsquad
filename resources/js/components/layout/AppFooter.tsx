import { Link, usePage } from "@inertiajs/react";
import { Mail, MapPin, Phone } from "lucide-react";
import { useT } from "@/hooks/use-t";
import { cn, telHref } from "@/lib/utils";
import { Container } from "./Container";
import { Logo } from "./Logo";

const footerLink =
  "text-navy-muted text-sm transition-colors hover:text-white hover:underline underline-offset-4";

export function AppFooter() {
  const t = useT();
  const { navigation, site } = usePage().props;
  const socialLinks = [
    { label: "Facebook", url: site.social.facebook },
    { label: "Instagram", url: site.social.instagram },
    { label: "LinkedIn", url: site.social.linkedin },
  ].filter((link): link is { label: string; url: string } => Boolean(link.url));

  return (
    <footer className="surface-navy bg-navy text-navy-foreground mt-auto">
      <Container className="py-14">
        <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1.5fr]">
          <div className="space-y-4">
            <Logo />
            <p className="text-navy-muted max-w-xs text-sm">
              {t("footer.tagline")}
            </p>
            {socialLinks.length > 0 && (
              <div>
                <h2 className="mb-2 text-sm font-semibold">
                  {t("footer.followUs")}
                </h2>
                <ul className="flex flex-wrap gap-x-4 gap-y-1">
                  {socialLinks.map((link) => (
                    <li key={link.label}>
                      <a
                        href={link.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={footerLink}
                      >
                        {link.label}
                        <span className="sr-only">
                          {" "}
                          {t("common.opensInNewTab")}
                        </span>
                      </a>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>

          <div className="space-y-4">
            <h2 className="text-sm font-semibold tracking-wide uppercase">
              {t("footer.quickLinks")}
            </h2>
            <nav aria-label={t("footer.quickLinks")}>
              <ul className="flex flex-col gap-2.5">
                {navigation.map((link) => (
                  <li key={link.url}>
                    <Link href={link.url} className={footerLink}>
                      {t(link.labelKey)}
                    </Link>
                  </li>
                ))}
              </ul>
            </nav>
          </div>

          <div className="space-y-4">
            <h2 className="text-sm font-semibold tracking-wide uppercase">
              {t("footer.contact")}
            </h2>
            <address className="flex flex-col gap-3 not-italic">
              <a
                href={telHref(site.contact.phone)}
                className={cn(footerLink, "flex items-center gap-3")}
              >
                <Phone
                  className="text-accent size-4 shrink-0"
                  aria-hidden="true"
                />
                {site.contact.phone}
              </a>
              <a
                href={`mailto:${site.contact.email}`}
                className={cn(footerLink, "flex items-center gap-3")}
              >
                <Mail
                  className="text-accent size-4 shrink-0"
                  aria-hidden="true"
                />
                {site.contact.email}
              </a>
              <span className="text-navy-muted flex items-start gap-3 text-sm">
                <MapPin
                  className="text-accent mt-0.5 size-4 shrink-0"
                  aria-hidden="true"
                />
                {site.contact.address}
              </span>
            </address>
          </div>
        </div>

        <div className="mt-12 border-t border-white/15 pt-6">
          <p className="text-navy-muted text-sm">
            &copy; {new Date().getFullYear()} AmpSquad. {t("footer.rights")}
          </p>
        </div>
      </Container>
    </footer>
  );
}
