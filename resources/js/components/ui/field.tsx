import { CircleAlert } from "lucide-react";
import type { ReactNode } from "react";

interface FieldControlProps {
  id: string;
  required?: boolean;
  "aria-invalid": true | undefined;
  "aria-describedby": string | undefined;
}

interface FieldProps {
  id: string;
  label: string;
  error?: string;
  required?: boolean;
  /** Receives the attributes that tie the control to its label and error message. */
  children: (control: FieldControlProps) => ReactNode;
}

/**
 * Label + control + error message, wired together for assistive tech:
 * the label targets the control by id, and the error is exposed through
 * aria-describedby alongside aria-invalid. The error also carries an icon,
 * so it never relies on color alone.
 */
export function Field({ id, label, error, required, children }: FieldProps) {
  const errorId = `${id}-error`;

  return (
    <div className="space-y-2">
      <label htmlFor={id} className="block text-sm font-semibold">
        {label}
        {required && (
          <span className="text-destructive ml-0.5" aria-hidden="true">
            *
          </span>
        )}
      </label>
      {children({
        id,
        required,
        "aria-invalid": error ? true : undefined,
        "aria-describedby": error ? errorId : undefined,
      })}
      {error && (
        <p
          id={errorId}
          className="text-destructive flex items-start gap-1.5 text-sm"
        >
          <CircleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
          {error}
        </p>
      )}
    </div>
  );
}
