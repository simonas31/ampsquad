import { Link } from "@inertiajs/react";
import { ArrowRight, Zap } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import type { Project } from "@/types";

export function ContentCard({ project }: { project: Project }) {
  return (
    <Link href={project.url} className="group block h-full">
      <Card className="group-hover:shadow-card-hover flex h-full flex-col transition-all duration-300 group-hover:-translate-y-1">
        <div className="bg-secondary relative aspect-4/3 overflow-hidden">
          {project.featuredImageThumbUrl ? (
            <img
              src={project.featuredImageThumbUrl}
              alt=""
              loading="lazy"
              className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
            />
          ) : (
            <Zap
              className="text-primary/15 absolute inset-0 m-auto size-16"
              aria-hidden="true"
            />
          )}
        </div>
        <div className="flex flex-1 flex-col gap-3 p-5">
          <Badge variant="accent">{project.category.name}</Badge>
          <h3 className="text-primary text-lg leading-snug group-hover:underline group-hover:underline-offset-4">
            {project.title}
          </h3>
          {project.excerpt && (
            <p className="text-muted-foreground line-clamp-2 text-sm">
              {project.excerpt}
            </p>
          )}
          <ArrowRight
            className="text-accent-text mt-auto size-5 transition-transform duration-200 group-hover:translate-x-1"
            aria-hidden="true"
          />
        </div>
      </Card>
    </Link>
  );
}
