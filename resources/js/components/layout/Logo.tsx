import { Link } from "@inertiajs/react";
import { useNavUrl } from "@/hooks/use-nav-url";
import { cn } from "@/lib/utils";

interface LogoProps {
  onNavigate?: () => void;
  className?: string;
}

/**
 * Wordmark only. The name is set in Archivo at its widest optical width,
 * which is the same typographic device the display headlines use, so the
 * brand reads as part of the page rather than as a badge stuck on it. It
 * inherits its colour, so it works on plaster and on graphite alike.
 */
export function Logo({ onNavigate, className }: LogoProps) {
  const homeUrl = useNavUrl("nav.home", "/");

  return (
    <Link
      href={homeUrl}
      onClick={onNavigate}
      className={cn("display inline-block text-2xl leading-none", className)}
    >
      AmpSquad
    </Link>
  );
}
