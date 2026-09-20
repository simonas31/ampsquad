import { Link } from "@inertiajs/react";
import { Zap } from "lucide-react";
import { useNavUrl } from "@/hooks/use-nav-url";

interface LogoProps {
  onNavigate?: () => void;
}

/** Wordmark; inherits its text color so it works on light and navy surfaces. */
export function Logo({ onNavigate }: LogoProps) {
  const homeUrl = useNavUrl("nav.home", "/");

  return (
    <Link
      href={homeUrl}
      onClick={onNavigate}
      className="inline-flex items-center gap-2.5 text-xl font-bold tracking-tight"
    >
      <span className="bg-accent text-accent-foreground flex size-8 items-center justify-center rounded-md">
        <Zap className="size-5" aria-hidden="true" />
      </span>
      AmpSquad
    </Link>
  );
}
