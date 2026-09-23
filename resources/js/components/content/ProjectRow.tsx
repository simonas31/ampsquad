import { Link } from "@inertiajs/react";
import { Figure } from "@/components/common/Figure";
import { projectFrame } from "@/lib/project-image";
import type { Project } from "@/types";

/**
 * A project as a ruled index entry rather than a card. The photograph is
 * held back on wide screens and uncovers on hover or keyboard focus, so a
 * long list reads as a typographic index first and a gallery second. On
 * small screens there is no hover to depend on, so the photograph leads
 * the entry instead.
 */
export function ProjectRow({ project }: { project: Project }) {
  const frame = projectFrame(project, "row", { thumbnail: true });
  const year = project.completedAt?.slice(0, 4);

  return (
    <li className="border-rule group border-t last:border-b">
      <Link
        href={project.url}
        className="grid grid-cols-1 items-center gap-x-10 gap-y-4 py-7 lg:grid-cols-12 lg:py-8"
      >
        <Figure
          frame={frame}
          alt=""
          ratio="16 / 9"
          zoom
          sizes="(min-width: 1024px) 22vw, 100vw"
          className="lg:col-span-3 lg:order-last lg:opacity-0 lg:transition-opacity lg:duration-500 lg:group-hover:opacity-100 lg:group-focus-within:opacity-100"
        />

        {/* One column for where the project sits in time: the year it was
            completed, or its status while it still has none. */}
        <p className="meta text-ink-soft lg:col-span-1">
          {year ?? project.category.name}
        </p>

        <h3 className="display text-ink text-3xl underline-offset-[10px] group-hover:underline group-hover:decoration-signal group-hover:decoration-[3px] group-focus-within:underline group-focus-within:decoration-signal lg:col-span-5 lg:text-4xl">
          {project.title}
        </h3>

        <p className="meta text-ink-soft lg:col-span-3">{project.location}</p>
      </Link>
    </li>
  );
}
