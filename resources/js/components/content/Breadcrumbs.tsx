import { Link } from "@inertiajs/react";
import { ChevronRight } from "lucide-react";
import { Fragment } from "react";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb } from "@/types";

export function Breadcrumbs({ items }: { items: Breadcrumb[] }) {
  const t = useT();

  return (
    <nav aria-label={t("common.breadcrumb")}>
      <ol className="text-muted-foreground flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm">
        {items.map((item, index) => (
          <Fragment key={index}>
            <li>
              {item.url ? (
                <Link
                  href={item.url}
                  className="hover:text-primary underline-offset-4 hover:underline"
                >
                  {item.label}
                </Link>
              ) : (
                <span
                  aria-current="page"
                  className="text-foreground font-semibold"
                >
                  {item.label}
                </span>
              )}
            </li>
            {index < items.length - 1 && (
              <li aria-hidden="true" className="flex">
                <ChevronRight className="size-3.5" />
              </li>
            )}
          </Fragment>
        ))}
      </ol>
    </nav>
  );
}
