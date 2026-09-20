import { Link, usePage } from "@inertiajs/react";
import { cn } from "@/lib/utils";
import { useT } from "@/hooks/use-t";

interface LanguageSwitchProps {
  className?: string;
  onNavigate?: () => void;
}

/**
 * Inline locale list — with only a couple of locales, showing every option
 * beats hiding them in a dropdown. Each link is marked with its own `lang`
 * so screen readers pronounce the native name correctly.
 */
export function LanguageSwitch({ className, onNavigate }: LanguageSwitchProps) {
  const t = useT();
  const { locale } = usePage().props;

  return (
    <nav
      aria-label={t("common.language")}
      className={cn("flex items-center gap-1", className)}
    >
      {locale.available.map((option) => (
        <Link
          key={option.code}
          href={option.url}
          lang={option.code}
          hrefLang={option.code}
          onClick={onNavigate}
          aria-current={option.code === locale.current ? "true" : undefined}
          className={cn(
            "rounded px-2 py-1 text-sm font-semibold transition-colors",
            option.code === locale.current
              ? "bg-white/15 text-white"
              : "text-navy-muted hover:text-white",
          )}
        >
          {option.name}
        </Link>
      ))}
    </nav>
  );
}
