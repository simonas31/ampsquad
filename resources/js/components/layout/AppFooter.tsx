import { Link, usePage } from "@inertiajs/react";
import { useT } from "@/hooks/use-t";
import { telHref } from "@/lib/utils";
import { Container } from "./Container";

/**
 * The tail of the graphite passage that closes every page. Three ruled
 * columns of particulars, then the wordmark set as large as the viewport
 * allows, which is the only place on the site where type is used purely as
 * a surface.
 */
export function AppFooter() {
  const t = useT();
  const { navigation, site } = usePage().props;
  const socialLinks = [
    { label: "Facebook", url: site.social.facebook },
    { label: "Instagram", url: site.social.instagram },
    { label: "LinkedIn", url: site.social.linkedin },
  ].filter((link): link is { label: string; url: string } => Boolean(link.url));

  const linkClass =
    "text-bone-soft hover:text-bone underline-offset-4 transition-colors hover:underline";

  return (
    <footer className="on-ink bg-ink text-bone mt-auto">
      <Container size="wide" className="pt-16 lg:pt-24">
        <div className="grid gap-x-10 gap-y-12 lg:grid-cols-12">
          <div className="lg:col-span-5">
            <p className="max-w-[34ch] text-xl text-pretty lg:text-2xl">
              {t("footer.tagline")}
            </p>
          </div>

          <nav
            aria-label={t("footer.quickLinks")}
            className="border-bone-soft/30 border-t pt-5 lg:col-span-3"
          >
            <h2 className="meta text-bone-soft mb-5">
              {t("footer.quickLinks")}
            </h2>
            <ul className="flex flex-col gap-3">
              {navigation.map((link) => (
                <li key={link.url}>
                  <Link href={link.url} className={linkClass}>
                    {t(link.labelKey)}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>

          <div className="border-bone-soft/30 border-t pt-5 lg:col-span-4">
            <h2 className="meta text-bone-soft mb-5">{t("footer.contact")}</h2>
            <address className="flex flex-col gap-3 not-italic">
              <a href={telHref(site.contact.phone)} className={linkClass}>
                {site.contact.phone}
              </a>
              <a href={`mailto:${site.contact.email}`} className={linkClass}>
                {site.contact.email}
              </a>
              <span className="text-bone-soft">{site.contact.address}</span>
            </address>

            {socialLinks.length > 0 && (
              <>
                <h2 className="meta text-bone-soft mt-8 mb-5">
                  {t("footer.followUs")}
                </h2>
                <ul className="flex flex-wrap gap-x-6 gap-y-2">
                  {socialLinks.map((link) => (
                    <li key={link.label}>
                      <a
                        href={link.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={linkClass}
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
              </>
            )}
          </div>
        </div>

        <p className="meta text-bone-soft border-bone-soft/30 mt-16 border-t pt-5">
          &copy; {new Date().getFullYear()} AmpSquad. {t("footer.rights")}
        </p>
      </Container>

      <p
        aria-hidden="true"
        className="display text-ink-raised overflow-hidden px-5 pt-8 text-[clamp(4rem,17vw,16rem)] leading-[0.78] whitespace-nowrap select-none sm:px-8 lg:px-12"
      >
        AmpSquad
      </p>
    </footer>
  );
}
