import { Link, usePage } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
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
  const sentinelRef = useRef<HTMLDivElement>(null);
  const [isPinned, setIsPinned] = useState(false);

  // The header floats free at the top of the page and only draws its rule
  // once it has actually pinned. A one-pixel sentinel above it reports that
  // through IntersectionObserver rather than a scroll listener, so nothing
  // runs on the scroll thread.
  useEffect(() => {
    const node = sentinelRef.current;

    if (!node) {
      return;
    }

    const observer = new IntersectionObserver(
      ([entry]) => setIsPinned(!entry?.isIntersecting),
      { threshold: 0 },
    );

    observer.observe(node);

    return () => observer.disconnect();
  }, []);

  return (
    <>
      <div ref={sentinelRef} aria-hidden="true" className="h-px" />

      <header
        className={cn(
          "bg-paper sticky top-0 z-40 w-full border-b transition-colors duration-300",
          isPinned ? "border-rule" : "border-transparent",
        )}
      >
        <Container
          size="wide"
          className="flex h-16 items-center justify-between gap-6 lg:h-20 lg:gap-10"
        >
          <Logo className="h-11 lg:h-14" />

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
                    "meta relative py-2 transition-colors",
                    current ? "text-ink" : "text-ink-soft hover:text-ink",
                  )}
                >
                  {t(link.labelKey)}
                  {current && (
                    <span
                      className="bg-signal absolute inset-x-0 bottom-0 h-0.5"
                      aria-hidden="true"
                    />
                  )}
                </Link>
              );
            })}
          </nav>

          <div className="flex items-center gap-4 lg:gap-6">
            <a
              href={telHref(site.contact.phone)}
              className="meta text-ink-soft hover:text-ink hidden transition-colors xl:inline-block"
            >
              {site.contact.phone}
            </a>
            <LanguageSwitch className="hidden lg:flex" />
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
