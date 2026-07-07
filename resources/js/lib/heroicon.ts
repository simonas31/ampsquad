import { defineAsyncComponent, type Component } from "vue";

// Lazy, code-split module loader — nothing here is bundled into the page's
// main chunk. Only the specific icon a category actually references gets
// fetched, as its own small chunk, the first time it's rendered.
//
// Scoped to the outline set only (the "heroicon-o-*" convention Filament
// admins are told to use, see lang/en/admin.php `icon_helper`) rather than
// all three style variants: globbing all of outline+solid+mini generates an
// import map covering ~2700 icons, which alone added ~200kb to this page's
// chunk. Outline-only keeps that manifest small. Solid/mini names parse but
// resolve to null (rendered as no icon) until there's a real need for them.
const OUTLINE_ICONS = import.meta.glob<{ default: Component }>(
    "../../../node_modules/@heroicons/vue/24/outline/esm/*.js",
);

const ICON_NAME_PATTERN = /^heroicon-(o|s|m)-(.+)$/;

/**
 * Resolves a blade-heroicons style name (e.g. "heroicon-o-bolt" — the format
 * Filament admins enter) to the matching @heroicons/vue component, loaded on
 * demand. Returns null for anything that doesn't parse or doesn't exist in
 * the target set, so a mistyped admin value never crashes the page —
 * callers should render nothing when this returns null.
 */
export function resolveHeroicon(icon: string | null | undefined): Component | null {
    if (!icon) {
        return null;
    }

    const match = ICON_NAME_PATTERN.exec(icon);
    if (!match || match[1] !== "o") {
        return null;
    }

    const name = match[2];
    const componentName = `${name
        .split("-")
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join("")}Icon`;

    const path = Object.keys(OUTLINE_ICONS).find((key) => key.endsWith(`/${componentName}.js`));
    if (!path) {
        return null;
    }

    return defineAsyncComponent(() => OUTLINE_ICONS[path]().then((mod) => mod.default));
}
