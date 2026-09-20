import { Link, usePage } from "@inertiajs/react";
import { Globe, Mail, Phone } from "lucide-react";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { useActiveLink } from "@/hooks/use-active-link";
import { useNavUrl } from "@/hooks/use-nav-url";
import { useT } from "@/hooks/use-t";
import { cn, telHref } from "@/lib/utils";
import { Container } from "./Container";
import { LanguageSwitch } from "./LanguageSwitch";
import { Logo } from "./Logo";
import { MobileNav } from "./MobileNav";

const topBarLinkClass =
  "text-muted-foreground hover:text-primary flex h-full items-center gap-2 rounded-sm font-medium underline-offset-4 transition-colors hover:underline";

export function AppHeader() {
  const t = useT();
  const { navigation, site } = usePage().props;
  const getCurrent = useActiveLink();
  const contactUrl = useNavUrl("nav.contact", "/contact");
  const [isScrolled, setIsScrolled] = useState(false);

  useEffect(() => {
    const onScroll = () => setIsScrolled(window.scrollY > 8);

    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });

    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  return (
    <>
      <div className="bg-surface hidden border-b lg:block">
        <Container className="flex h-11 items-center justify-between text-sm">
          <div className="flex h-full items-center gap-4">
            <a href={telHref(site.contact.phone)} className={topBarLinkClass}>
              <Phone className="text-accent-text size-4" aria-hidden="true" />
              <span className="tabular-nums">{site.contact.phone}</span>
            </a>
            <span className="bg-border h-4 w-px" aria-hidden="true" />
            <a href={`mailto:${site.contact.email}`} className={topBarLinkClass}>
              <Mail className="text-accent-text size-4" aria-hidden="true" />
              {site.contact.email}
            </a>
          </div>
          <div className="text-muted-foreground flex items-center gap-2">
            <Globe className="size-4" aria-hidden="true" />
            <LanguageSwitch tone="light" />
          </div>
        </Container>
      </div>

      <header
        className={cn(
          "bg-background/95 supports-backdrop-filter:bg-background/85 sticky top-0 z-40 w-full border-b backdrop-blur transition-shadow",
          isScrolled && "shadow-card",
        )}
      >
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
