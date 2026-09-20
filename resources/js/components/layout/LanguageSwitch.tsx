import { Link, usePage } from "@inertiajs/react";
import { cn } from "@/lib/utils";
import { useT } from "@/hooks/use-t";

interface LanguageSwitchProps {
  className?: string;
  /** `dark` for navy surfaces, `light` for the white/grey page chrome. */
  tone?: "dark" | "light";
  onNavigate?: () => void;
}

const TONES = {
  dark: {
    nav: "gap-1",
    link: "rounded px-2 py-1",
    active: "bg-white/15 text-white",
    inactive: "text-navy-muted hover:text-white",
  },
  light: {
    nav: "bg-background gap-0.5 rounded-full border p-0.5",
    link: "min-w-11 rounded-full px-3 py-0.5 text-center",
    active: "bg-primary text-primary-foreground",
    inactive: "text-muted-foreground hover:bg-secondary hover:text-primary",
  },
} as const;

/**
 * Inline locale list — with only a couple of locales, showing every option
 * beats hiding them in a dropdown. Each link is marked with its own `lang`
 * so screen readers pronounce the native name correctly.
 */
export function LanguageSwitch({
  className,
  tone = "dark",
  onNavigate,
}: LanguageSwitchProps) {
  const t = useT();
  const { locale } = usePage().props;
  const styles = TONES[tone];

  return (
    <nav
      aria-label={t("common.language")}
      className={cn("flex items-center", styles.nav, className)}
    >
      {locale.available.map((option) => {
        const isCurrent = option.code === locale.current;

        return (
          <Link
            key={option.code}
            href={option.url}
            lang={option.code}
            hrefLang={option.code}
            onClick={onNavigate}
            aria-current={isCurrent ? "true" : undefined}
            className={cn(
              "text-sm font-semibold transition-colors",
              styles.link,
              isCurrent ? styles.active : styles.inactive,
            )}
          >
            {option.name}
          </Link>
        );
      })}
    </nav>
  );
}
