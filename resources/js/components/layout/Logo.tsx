import { Link } from "@inertiajs/react";
import { useNavUrl } from "@/hooks/use-nav-url";
import { cn } from "@/lib/utils";

interface LogoProps {
  onNavigate?: () => void;
  /** `dark` swaps to the white-wordmark lockup for the graphite passage. */
  tone?: "light" | "dark";
  className?: string;
}

/**
 * The company lockup. Two artwork files rather than one recoloured by CSS,
 * because the mark is two-tone: on plaster the second half of the mark and
 * the strapline are navy, on graphite they are white. Set the display size
 * with a height class on the link; the image tracks it.
 */
export function Logo({ onNavigate, tone = "light", className }: LogoProps) {
  const homeUrl = useNavUrl("nav.home", "/");

  return (
    <Link
      href={homeUrl}
      onClick={onNavigate}
      className={cn("inline-flex shrink-0 items-center", className)}
    >
      <img
        src={
          tone === "dark"
            ? "/images/ampsquad-logo-dark.png"
            : "/images/ampsquad-logo.png"
        }
        alt="AmpSquad"
        width={337}
        height={240}
        className="h-full w-auto"
      />
    </Link>
  );
}
