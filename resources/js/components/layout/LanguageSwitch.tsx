import { Link, usePage } from "@inertiajs/react";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";

interface LanguageSwitchProps {
  className?: string;
  /** `dark` inside the graphite passage, `light` on plaster chrome. */
  tone?: "dark" | "light";
  /** `code` for the compact header, `name` where there is room to spell it. */
  labels?: "code" | "name";
  onNavigate?: () => void;
}

const TONES = {
  dark: {
    active: "text-bone",
    inactive: "text-bone-soft hover:text-bone",
    divider: "bg-bone-soft/40",
  },
  light: {
    active: "text-ink",
    inactive: "text-ink-soft hover:text-ink",
    divider: "bg-rule",
  },
} as const;

/**
 * Inline locale list - with only a couple of locales, showing every option
 * beats hiding them in a dropdown. Set in the metadata register and split by
 * a hairline rather than boxed, so it reads as an annotation on the header
 * instead of a second button competing with the quote request. Each link is
 * marked with its own `lang` so screen readers pronounce the native name
 * correctly.
 */
export function LanguageSwitch({
  className,
  tone = "light",
  labels = "code",
  onNavigate,
}: LanguageSwitchProps) {
  const t = useT();
  const { locale } = usePage().props;
  const styles = TONES[tone];

  return (
    <nav
      aria-label={t("common.language")}
      className={cn("flex items-center gap-2.5", className)}
    >
      {locale.available.map((option, index) => {
        const isCurrent = option.code === locale.current;

        return (
          <span key={option.code} className="flex items-center gap-2.5">
            {index > 0 && (
              <span
                aria-hidden="true"
                className={cn("h-3 w-px", styles.divider)}
              />
            )}
            <Link
              href={option.url}
              lang={option.code}
              hrefLang={option.code}
              onClick={onNavigate}
              aria-current={isCurrent ? "true" : undefined}
              className={cn(
                "meta transition-colors",
                isCurrent ? styles.active : styles.inactive,
              )}
            >
              {labels === "code" ? option.code : option.name}
            </Link>
          </span>
        );
      })}
    </nav>
  );
}
