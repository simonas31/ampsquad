import { Link } from "@inertiajs/react";
import { Figure } from "@/components/common/Figure";
import { projectFrame } from "@/lib/project-image";
import type { Project } from "@/types";

/**
 * The opening project of the index: one large frame, with the title and
 * particulars set against its bottom edge in the remaining columns. It is
 * the only project on the page given this much room, which is what makes
 * the rows beneath it read as a sequence rather than a grid.
 */
export function ProjectLead({ project }: { project: Project }) {
  const frame = projectFrame(project, "lead");
  const year = project.completedAt?.slice(0, 4);

  return (
    <article className="group">
      <Link
        href={project.url}
        className="grid grid-cols-1 gap-x-10 gap-y-7 lg:grid-cols-12"
      >
        <Figure
          frame={frame}
          alt=""
          ratio="3 / 2"
          zoom
          sizes="(min-width: 1024px) 66vw, 100vw"
          className="lg:col-span-8"
        />

        <div className="flex flex-col justify-end lg:col-span-4">
          <div className="meta text-ink-soft border-ink flex flex-wrap gap-x-5 gap-y-1 border-t pt-4">
            <span>{project.category.name}</span>
            {project.location && <span>{project.location}</span>}
            {year && <span>{year}</span>}
          </div>

          <h3 className="display text-ink mt-6 text-4xl underline-offset-[10px] group-hover:underline group-hover:decoration-signal group-hover:decoration-[3px] group-focus-within:underline group-focus-within:decoration-signal lg:text-5xl">
            {project.title}
          </h3>

          {project.excerpt && (
            <p className="text-ink-soft mt-5 max-w-[44ch] text-pretty">
              {project.excerpt}
            </p>
          )}
        </div>
      </Link>
    </article>
  );
}
