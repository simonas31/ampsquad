import { useT } from "@/hooks/use-t";
import { cn, telHref } from "@/lib/utils";
import type { SiteData } from "@/types";

interface ContactDetailsProps {
  contact: SiteData["contact"];
  tone?: "light" | "dark";
  className?: string;
}

/**
 * Phone, email and address as a definition list with mono labels. No icons:
 * the labels are already the shortest possible way to say what each line is,
 * and they translate, which a pictogram does not.
 */
export function ContactDetails({
  contact,
  tone = "light",
  className,
}: ContactDetailsProps) {
  const t = useT();
  const dark = tone === "dark";
  const labelClass = cn("meta", dark ? "text-bone-soft" : "text-ink-soft");
  const valueClass = cn(
    "mt-1.5 block text-lg",
    dark ? "text-bone" : "text-ink",
  );

  return (
    <dl className={cn("space-y-7", className)}>
      <div>
        <dt className={labelClass}>{t("contact.phone")}</dt>
        <dd>
          <a
            href={telHref(contact.phone)}
            className={cn(valueClass, "underline-offset-4 hover:underline")}
          >
            {contact.phone}
          </a>
        </dd>
      </div>
      <div>
        <dt className={labelClass}>{t("contact.email")}</dt>
        <dd>
          <a
            href={`mailto:${contact.email}`}
            className={cn(
              valueClass,
              "break-all underline-offset-4 hover:underline",
            )}
          >
            {contact.email}
          </a>
        </dd>
      </div>
      <div>
        <dt className={labelClass}>{t("contact.address")}</dt>
        <dd className={valueClass}>{contact.address}</dd>
      </div>
    </dl>
  );
}
