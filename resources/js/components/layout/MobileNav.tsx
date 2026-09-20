import { Link, usePage } from "@inertiajs/react";
import { Mail, Menu, Phone, X } from "lucide-react";
import { Dialog } from "radix-ui";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { useActiveLink } from "@/hooks/use-active-link";
import { useNavUrl } from "@/hooks/use-nav-url";
import { useT } from "@/hooks/use-t";
import { cn, telHref } from "@/lib/utils";
import { LanguageSwitch } from "./LanguageSwitch";
import { Logo } from "./Logo";

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
        <Dialog.Overlay className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:fade-in-0 data-[state=closed]:fade-out-0 fixed inset-0 z-50 bg-black/50" />
        <Dialog.Content
          aria-describedby={undefined}
          className="surface-navy bg-navy text-navy-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:slide-in-from-right data-[state=closed]:slide-out-to-right fixed inset-y-0 right-0 z-50 flex w-[min(22rem,100%)] flex-col gap-8 overflow-y-auto p-6 shadow-xl duration-300"
        >
          <div className="flex items-center justify-between">
            <Dialog.Title className="sr-only">
              {t("common.mobileNavDescription")}
            </Dialog.Title>
            <Logo onNavigate={close} />
            <Dialog.Close asChild>
              <Button
                variant="ghost"
                size="icon"
                className="text-navy-foreground hover:bg-white/10"
                aria-label={t("common.closeMenu")}
              >
                <X className="size-6" aria-hidden="true" />
              </Button>
            </Dialog.Close>
          </div>

          <nav
            aria-label={t("common.mainNavigation")}
            className="flex flex-col gap-1"
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
                    "rounded-md px-3 py-3 text-lg font-semibold transition-colors",
                    current
                      ? "bg-white/10 text-white"
                      : "text-navy-muted hover:bg-white/5 hover:text-white",
                  )}
                >
                  {t(link.labelKey)}
                </Link>
              );
            })}
          </nav>

          <Button asChild variant="accent" size="lg">
            <Link href={contactUrl} onClick={close}>
              {t("common.requestQuote")}
            </Link>
          </Button>

          <div className="mt-auto space-y-4 border-t border-white/15 pt-6">
            <a
              href={telHref(site.contact.phone)}
              className="flex items-center gap-3 text-sm font-medium hover:underline"
            >
              <Phone className="text-accent size-4" aria-hidden="true" />
              {site.contact.phone}
            </a>
            <a
              href={`mailto:${site.contact.email}`}
              className="flex items-center gap-3 text-sm font-medium hover:underline"
            >
              <Mail className="text-accent size-4" aria-hidden="true" />
              {site.contact.email}
            </a>
            <LanguageSwitch className="-ml-2" onNavigate={close} />
          </div>
        </Dialog.Content>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
