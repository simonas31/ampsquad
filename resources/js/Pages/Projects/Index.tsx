import { Link, usePage } from "@inertiajs/react";
import { FolderSearch } from "lucide-react";
import { Reveal } from "@/components/common/Reveal";
import { ContentCard } from "@/components/content/ContentCard";
import { Pagination } from "@/components/content/Pagination";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";
import type { Breadcrumb, Category, Paginated, Project } from "@/types";

interface IndexProps {
  projects: Paginated<Project>;
  categories: Category[];
  breadcrumbs: Breadcrumb[];
}

const chipClasses =
  "inline-flex min-h-10 items-center rounded-full border px-4 text-sm font-semibold whitespace-nowrap transition-colors";

export default function Index({
  projects,
  categories,
  breadcrumbs,
}: IndexProps) {
  const t = useT();
  const { url } = usePage();
  const [path, query = ""] = url.split("?");
  const activeCategory = new URLSearchParams(query).get("category");

  return (
    <>
      <PageHeader breadcrumbs={breadcrumbs} title={t("nav.projects")} />

      <Container className="py-10 lg:py-14">
        {categories.length > 0 && (
          <nav
            aria-label={t("content.filterByCategory")}
            className="-mx-4 mb-10 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0"
          >
            <ul className="flex gap-2 sm:flex-wrap">
              <li>
                <Link
                  href={path}
                  preserveScroll
                  aria-current={activeCategory === null ? "true" : undefined}
                  className={cn(
                    chipClasses,
                    activeCategory === null
                      ? "bg-primary border-primary text-primary-foreground"
                      : "border-input hover:bg-secondary",
                  )}
                >
                  {t("content.allCategories")}
                </Link>
              </li>
              {categories.map((category) => {
                const isActive = activeCategory === String(category.id);

                return (
                  <li key={category.id}>
                    <Link
                      href={path}
                      data={{ category: category.id }}
                      preserveScroll
                      aria-current={isActive ? "true" : undefined}
                      className={cn(
                        chipClasses,
                        isActive
                          ? "bg-primary border-primary text-primary-foreground"
                          : "border-input hover:bg-secondary",
                      )}
                    >
                      {category.name}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </nav>
        )}

        {projects.data.length > 0 ? (
          <ul className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {projects.data.map((project, index) => (
              <li key={project.id}>
                <Reveal delay={(index % 6) * 75} className="h-full">
                  <ContentCard project={project} />
                </Reveal>
              </li>
            ))}
          </ul>
        ) : (
          <div className="flex flex-col items-center gap-3 py-16 text-center">
            <span className="bg-secondary text-muted-foreground flex size-14 items-center justify-center rounded-full">
              <FolderSearch className="size-6" aria-hidden="true" />
            </span>
            <p className="text-muted-foreground">{t("content.noProjects")}</p>
          </div>
        )}

        <div className="mt-12">
          <Pagination pagination={projects} />
        </div>
      </Container>
    </>
  );
}
