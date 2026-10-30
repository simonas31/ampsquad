import { Check, Copy } from "lucide-react";
import { useEffect, useState } from "react";
import { useT } from "@/hooks/use-t";

/**
 * A product's SKU with a button that copies it, for people who order by
 * code. It sits beside a product's link, never inside it, so the card does
 * not nest interactive elements. Where the browser offers no clipboard (an
 * insecure page) the button does nothing and the code stays selectable.
 */
export function CopySku({ sku }: { sku: string }) {
  const t = useT();
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    if (!copied) {
      return;
    }

    const timer = window.setTimeout(() => setCopied(false), 1500);

    return () => window.clearTimeout(timer);
  }, [copied]);

  async function copy() {
    try {
      await navigator.clipboard.writeText(sku);
      setCopied(true);
    } catch {
      setCopied(false);
    }
  }

  return (
    <p className="text-ink-soft flex items-center gap-1 text-xs">
      <span className="min-w-0 truncate">
        {t("shop.sku")}: <span className="font-mono">{sku}</span>
      </span>
      <button
        type="button"
        onClick={copy}
        aria-label={t("shop.copySku", { sku })}
        className="hover:text-ink hover:bg-concrete flex size-6 shrink-0 items-center justify-center rounded transition-colors"
      >
        {copied ? (
          <Check className="size-3.5" aria-hidden="true" />
        ) : (
          <Copy className="size-3.5" aria-hidden="true" />
        )}
      </button>
      <span role="status" className="sr-only">
        {copied ? t("shop.skuCopied") : ""}
      </span>
    </p>
  );
}
