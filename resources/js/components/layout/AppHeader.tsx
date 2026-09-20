import { Link, usePage } from "@inertiajs/react";
import { Mail, Phone } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useActiveLink } from "@/hooks/use-active-link";
import { useNavUrl } from "@/hooks/use-nav-url";
import { useT } from "@/hooks/use-t";
import { cn, telHref } from "@/lib/utils";
import { Container } from "./Container";
import { LanguageSwitch } from "./LanguageSwitch";
import { Logo } from "./Logo";
import { MobileNav } from "./MobileNav";

export function AppHeader() {
  const t = useT();
  const { navigation, site } = usePage().props;
  const getCurrent = useActiveLink();
  const contactUrl = useNavUrl("nav.contact", "/contact");

  return (
    <>
      <div className="surface-navy bg-navy text-navy-foreground hidden lg:block">
        <Container className="flex h-10 items-center justify-between text-sm">
          <div className="flex items-center gap-6">
            <a
              href={telHref(site.contact.phone)}
              className="text-navy-muted flex items-center gap-2 font-medium hover:text-white"
            >
              <Phone className="text-accent size-3.5" aria-hidden="true" />
              {site.contact.phone}
            </a>
            <a
              href={`mailto:${site.contact.email}`}
              className="text-navy-muted flex items-center gap-2 font-medium hover:text-white"
            >
              <Mail className="text-accent size-3.5" aria-hidden="true" />
              {site.contact.email}
            </a>
          </div>
          <LanguageSwitch />
        </Container>
      </div>

      <header className="bg-background/95 supports-backdrop-filter:bg-background/85 sticky top-0 z-40 w-full border-b backdrop-blur">
        <Container className="text-primary flex h-16 items-center justify-between gap-6 lg:h-20">
          <Logo />

          <nav
            aria-label={t("common.mainNavigation")}
            className="hidden items-center gap-8 lg:flex"
          >
            {navigation.map((link) => {
              const current = getCurrent(link);

              return (
                <Link
                  key={link.url}
                  href={link.url}
                  aria-current={current}
                  className={cn(
                    "relative py-2 text-sm font-semibold transition-colors",
                    current
                      ? "text-primary"
                      : "text-muted-foreground hover:text-primary",
                  )}
                >
                  {t(link.labelKey)}
                  {current && (
                    <span
                      className="bg-accent absolute inset-x-0 -bottom-px h-0.5 rounded-full"
                      aria-hidden="true"
                    />
                  )}
                </Link>
              );
            })}
          </nav>

          <div className="flex items-center gap-2">
            <Button asChild variant="accent" className="hidden sm:inline-flex">
              <Link href={contactUrl}>{t("common.requestQuote")}</Link>
            </Button>
            <MobileNav />
          </div>
        </Container>
      </header>
    </>
  );
}
