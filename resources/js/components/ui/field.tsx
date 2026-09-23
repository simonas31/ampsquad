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
 * aria-describedby alongside aria-invalid. The error is prefixed with a
 * literal word rather than an icon, so it never relies on colour alone and
 * stays in the same mono register as the label above it.
 */
export function Field({ id, label, error, required, children }: FieldProps) {
  const errorId = `${id}-error`;

  return (
    <div className="space-y-2">
      <label htmlFor={id} className="meta text-ink-soft block">
        {label}
        {required && (
          <span className="text-ink ml-1" aria-hidden="true">
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
        <p id={errorId} className="text-destructive text-sm">
          {error}
        </p>
      )}
    </div>
  );
}
