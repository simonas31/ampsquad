import { Link, usePage } from "@inertiajs/react";
import { Menu, X } from "lucide-react";
import { Dialog } from "radix-ui";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { useActiveLink } from "@/hooks/use-active-link";
import { useNavUrl } from "@/hooks/use-nav-url";
import { useT } from "@/hooks/use-t";
import { cn, telHref } from "@/lib/utils";
import { LanguageSwitch } from "./LanguageSwitch";
import { Logo } from "./Logo";

/**
 * The small-screen menu is a full graphite sheet rather than a narrow
 * drawer: with four destinations there is no reason to crowd them, and at
 * display size the links become the same typographic material as the rest
 * of the site.
 */
export function MobileNav() {
  const t = useT();
  const [open, setOpen] = useState(false);
  const { navigation, site } = usePage().props;
  const getCurrent = useActiveLink();
  const contactUrl = useNavUrl("nav.contact", "/contact");
  const close = () => setOpen(false);

  return (
    <Dialog.Root open={open} onOpenChange={setOpen}>
      <Dialog.Trigger asChild>
        <Button
          variant="ghost"
          size="icon"
          className="lg:hidden"
          aria-label={t("common.openMenu")}
        >
          <Menu className="size-6" aria-hidden="true" />
        </Button>
      </Dialog.Trigger>

      <Dialog.Portal>
        <Dialog.Overlay className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:fade-in-0 data-[state=closed]:fade-out-0 fixed inset-0 z-50 bg-black/60" />
        <Dialog.Content
          aria-describedby={undefined}
          className="on-ink bg-ink text-bone data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:slide-in-from-top-4 data-[state=open]:fade-in data-[state=closed]:fade-out fixed inset-0 z-50 flex flex-col overflow-y-auto px-5 py-4 duration-300 sm:px-8"
        >
          <div className="flex h-12 items-center justify-between">
            <Dialog.Title className="sr-only">
              {t("common.mobileNavDescription")}
            </Dialog.Title>
            <Logo onNavigate={close} tone="dark" className="h-10" />
            <Dialog.Close asChild>
              <Button
                variant="ghost"
                size="icon"
                className="text-bone hover:bg-ink-raised"
                aria-label={t("common.closeMenu")}
              >
                <X className="size-6" aria-hidden="true" />
              </Button>
            </Dialog.Close>
          </div>

          <nav
            aria-label={t("common.mainNavigation")}
            className="mt-12 flex flex-col"
          >
            {navigation.map((link) => {
              const current = getCurrent(link);

              return (
                <Link
                  key={link.url}
                  href={link.url}
                  aria-current={current}
                  onClick={close}
                  className={cn(
                    "display border-bone-soft/25 border-b py-5 text-4xl transition-colors",
                    current ? "text-signal" : "text-bone hover:text-signal",
                  )}
                >
                  {t(link.labelKey)}
                </Link>
              );
            })}
          </nav>

          <Button asChild variant="accent" size="lg" className="mt-10">
            <Link href={contactUrl} onClick={close}>
              {t("common.requestQuote")}
            </Link>
          </Button>

          <div className="border-bone-soft/25 mt-auto space-y-4 border-t pt-8">
            <a
              href={telHref(site.contact.phone)}
              className="text-bone block text-xl hover:underline"
            >
              {site.contact.phone}
            </a>
            <a
              href={`mailto:${site.contact.email}`}
              className="text-bone-soft block break-all hover:underline"
            >
              {site.contact.email}
            </a>
            <LanguageSwitch tone="dark" labels="name" onNavigate={close} />
          </div>
        </Dialog.Content>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
