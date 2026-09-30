import { Link, usePage } from "@inertiajs/react";
import { ArrowUpRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useActiveLink } from "@/hooks/use-active-link";
import { useNavUrl } from "@/hooks/use-nav-url";
import { useT } from "@/hooks/use-t";
import { cn, telHref } from "@/lib/utils";
import { Container } from "./Container";
import { LanguageSwitch } from "./LanguageSwitch";
import { Logo } from "./Logo";
import { MobileNav } from "./MobileNav";

/**
 * A white pill that floats over the page rather than a bar across it. The
 * sticky wrapper is zero-height on purpose: it keeps the pill pinned to the
 * top while taking no space in the flow, so the home page's photograph can
 * run underneath it. Every other page reserves the room itself (see
 * AppLayout).
 */
export function AppHeader() {
  const t = useT();
  const { navigation, site } = usePage().props;
  const getCurrent = useActiveLink();
  const contactUrl = useNavUrl("nav.contact", "/contact");

  return (
    <header className="pointer-events-none sticky top-0 z-40 h-0 w-full">
      <Container size="wide" className="pt-3 lg:pt-4">
        <div className="shadow-ink/15 pointer-events-auto flex h-16 items-center justify-between gap-4 rounded-full bg-white pr-3 pl-5 shadow-xl lg:h-[4.5rem] lg:gap-6 lg:pl-7">
          <Logo className="h-10 lg:h-12" />

          <nav
            aria-label={t("common.mainNavigation")}
            className="hidden items-center gap-6 lg:flex xl:gap-8"
          >
            {navigation.map((link) => {
              const current = getCurrent(link);

              return (
                <Link
                  key={link.url}
                  href={link.url}
                  aria-current={current}
                  className={cn(
                    "relative py-2 text-[0.95rem] font-medium whitespace-nowrap transition-colors",
                    current ? "text-ink" : "text-ink-soft hover:text-ink",
                  )}
                >
                  {t(link.labelKey)}
                  {current && (
                    <span
                      className="bg-signal absolute inset-x-0 -bottom-0.5 h-0.5 rounded-full"
                      aria-hidden="true"
                    />
                  )}
                </Link>
              );
            })}
          </nav>

          <div className="flex items-center gap-4 lg:gap-5">
            <a
              href={telHref(site.contact.phone)}
              className="text-ink border-rule hidden border-l pl-5 text-[0.95rem] font-medium whitespace-nowrap transition-colors hover:underline hover:underline-offset-4 2xl:inline-block"
            >
              {site.contact.phone}
            </a>
            <LanguageSwitch className="hidden lg:flex" />
            <Button
              asChild
              className="hidden sm:inline-flex lg:hidden xl:inline-flex"
            >
              <Link href={contactUrl}>
                {t("common.requestQuote")}
                <ArrowUpRight aria-hidden="true" />
              </Link>
            </Button>
            <MobileNav />
          </div>
        </div>
      </Container>
    </header>
  );
}
