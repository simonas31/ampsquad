import {
  lazy,
  Suspense,
  type ComponentType,
  type LazyExoticComponent,
  type SVGProps,
} from "react";

type IconComponent = ComponentType<SVGProps<SVGSVGElement>>;

// Lazy, code-split module loader — nothing here is bundled into the page's
// main chunk. Only the specific icon a category actually references gets
// fetched, as its own small chunk, the first time it's rendered.
//
// Scoped to the outline set only (the "heroicon-o-*" convention Filament
// admins are told to use, see lang/en/admin.php `icon_helper`) rather than
// all three style variants: globbing all of outline+solid+mini generates an
// import map covering ~2700 icons, which alone added ~200kb to this page's
// chunk. Outline-only keeps that manifest small. Solid/mini names parse but
// resolve to no icon until there's a real need for them.
const OUTLINE_ICONS = import.meta.glob<{ default: IconComponent }>(
  "../../../node_modules/@heroicons/react/24/outline/esm/*.js",
);

const ICON_NAME_PATTERN = /^heroicon-(o|s|m)-(.+)$/;

// lazy() must be called once per icon, not per render — a fresh component
// identity every render would remount the icon on every update.
const lazyIcons = new Map<string, LazyExoticComponent<IconComponent> | null>();

function resolveLazyIcon(
  icon: string,
): LazyExoticComponent<IconComponent> | null {
  const cached = lazyIcons.get(icon);
  if (cached !== undefined) {
    return cached;
  }

  const match = ICON_NAME_PATTERN.exec(icon);
  let resolved: LazyExoticComponent<IconComponent> | null = null;

  if (match?.[1] === "o") {
    const componentName = `${match[2]
      .split("-")
      .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
      .join("")}Icon`;
    const path = Object.keys(OUTLINE_ICONS).find((key) =>
      key.endsWith(`/${componentName}.js`),
    );

    if (path) {
      resolved = lazy(OUTLINE_ICONS[path]);
    }
  }

  lazyIcons.set(icon, resolved);

  return resolved;
}

interface HeroIconProps {
  /** blade-heroicons style name (e.g. "heroicon-o-bolt") as entered in the admin. */
  name: string | null | undefined;
  className?: string;
}

/**
 * Renders a Heroicon from its blade-heroicons name. Renders nothing for
 * anything that doesn't parse or doesn't exist in the outline set, so a
 * mistyped admin value never crashes the page.
 */
export function HeroIcon({ name, className }: HeroIconProps) {
  const Icon = name ? resolveLazyIcon(name) : null;

  if (!Icon) {
    return null;
  }

  return (
    <Suspense fallback={<span className={className} aria-hidden="true" />}>
      <Icon className={className} aria-hidden="true" />
    </Suspense>
  );
}
