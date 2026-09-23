import { Link } from "@inertiajs/react";
import { ChevronRight } from "lucide-react";
import { Fragment } from "react";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb } from "@/types";

export function Breadcrumbs({ items }: { items: Breadcrumb[] }) {
  const t = useT();

  return (
    <nav aria-label={t("common.breadcrumb")}>
      <ol className="meta text-ink-soft flex flex-wrap items-center gap-x-2 gap-y-1">
        {items.map((item, index) => (
          <Fragment key={index}>
            <li>
              {item.url ? (
                <Link
                  href={item.url}
                  className="hover:text-ink underline-offset-4 transition-colors hover:underline"
                >
                  {item.label}
                </Link>
              ) : (
                <span
                  aria-current="page"
                  className="text-ink"
                >
                  {item.label}
                </span>
              )}
            </li>
            {index < items.length - 1 && (
              <li aria-hidden="true" className="flex">
                <ChevronRight className="size-3" />
              </li>
            )}
          </Fragment>
        ))}
      </ol>
    </nav>
  );
}
