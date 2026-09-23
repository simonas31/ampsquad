import { Link, usePage } from "@inertiajs/react";
import { Reveal } from "@/components/common/Reveal";
import { Pagination } from "@/components/content/Pagination";
import { ProjectRow } from "@/components/content/ProjectRow";
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

const filterClasses =
  "meta inline-flex min-h-10 items-center border px-4 whitespace-nowrap transition-colors";

export default function Index({
  projects,
  categories,
  breadcrumbs,
}: IndexProps) {
  const t = useT();
  const { url } = usePage();
  const [path, query = ""] = url.split("?");
  const activeCategory = new URLSearchParams(query).get("category");

  const filterStyle = (isActive: boolean) =>
    cn(
      filterClasses,
      isActive
        ? "border-ink bg-ink text-bone"
        : "border-rule text-ink-soft hover:border-ink hover:text-ink",
    );

  return (
    <>
      <PageHeader breadcrumbs={breadcrumbs} title={t("nav.projects")}>
        {categories.length > 0 && (
          <nav
            aria-label={t("content.filterByCategory")}
            className="-mx-5 mt-10 overflow-x-auto px-5 pb-1 sm:mx-0 sm:px-0"
          >
            <ul className="flex gap-2 sm:flex-wrap">
              <li>
                <Link
                  href={path}
                  preserveScroll
                  aria-current={activeCategory === null ? "true" : undefined}
                  className={filterStyle(activeCategory === null)}
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
                      className={filterStyle(isActive)}
                    >
                      {category.name}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </nav>
        )}
      </PageHeader>

      <Container size="wide" className="py-14 lg:py-20">
        {projects.data.length > 0 ? (
          <>
            <Reveal>
              <ul>
                {projects.data.map((project) => (
                  <ProjectRow key={project.id} project={project} />
                ))}
              </ul>
            </Reveal>

            <div className="mt-14">
              <Pagination pagination={projects} />
            </div>
          </>
        ) : (
          <div className="border-ink border-t py-20">
            <p className="display text-ink-soft max-w-[18ch] text-3xl sm:text-4xl">
              {t("content.noProjects")}
            </p>
          </div>
        )}
      </Container>
    </>
  );
}
