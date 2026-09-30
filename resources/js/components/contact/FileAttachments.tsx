import { Paperclip, X } from "lucide-react";
import { useId, useRef, useState, type DragEvent } from "react";
import { Button } from "@/components/ui/button";
import { useT } from "@/hooks/use-t";
import {
  ATTACHMENT_ACCEPT,
  ATTACHMENT_LIMITS,
  formatFileSize,
  mergeAttachments,
  type AttachmentProblem,
} from "@/lib/attachments";
import { cn } from "@/lib/utils";

interface FileAttachmentsProps {
  id: string;
  files: File[];
  onChange: (files: File[]) => void;
  /** Messages returned by the server for `attachments` and `attachments.*`. */
  errors: string[];
}

/**
 * Optional file picker for the enquiry form: a dashed drop area with a
 * button, and the chosen files listed underneath so each can be removed
 * before sending. The native input stays in the DOM (screen-reader only)
 * so the label, the keyboard and the file dialog all keep working; the
 * visible button just opens it. Files are checked here against the same
 * limits the server enforces, and a rejected file is reported without
 * discarding the rest of the selection.
 */
export function FileAttachments({
  id,
  files,
  onChange,
  errors,
}: FileAttachmentsProps) {
  const t = useT();
  const inputRef = useRef<HTMLInputElement>(null);
  const hintId = useId();
  const errorId = `${id}-error`;
  const [isDragging, setIsDragging] = useState(false);
  const [problems, setProblems] = useState<AttachmentProblem[]>([]);

  const limits = {
    count: ATTACHMENT_LIMITS.maxFiles,
    size: ATTACHMENT_LIMITS.maxSizeMb,
  };

  function addFiles(incoming: File[]) {
    const merged = mergeAttachments(files, incoming);

    setProblems(merged.problems);
    onChange(merged.files);
  }

  function describe(problem: AttachmentProblem): string {
    switch (problem.kind) {
      case "tooMany":
        return t("contact.attachments.tooMany", limits);
      case "tooLarge":
        return t("contact.attachments.tooLarge", {
          ...limits,
          name: problem.name,
        });
      case "badType":
        return t("contact.attachments.badType", { name: problem.name });
    }
  }

  function handleDrop(event: DragEvent<HTMLDivElement>) {
    event.preventDefault();
    setIsDragging(false);
    addFiles(Array.from(event.dataTransfer.files));
  }

  const messages = [...problems.map(describe), ...errors];

  return (
    <div className="space-y-2">
      <label htmlFor={id} className="meta text-ink-soft block">
        {t("contact.attachments.label")}{" "}
        <span className="font-normal">
          ({t("contact.attachments.optional")})
        </span>
      </label>

      <div
        onDragOver={(event) => {
          event.preventDefault();
          setIsDragging(true);
        }}
        onDragLeave={() => setIsDragging(false)}
        onDrop={handleDrop}
        className={cn(
          "border-input flex flex-col items-start gap-3 rounded-2xl border border-dashed bg-white p-5 transition-colors",
          isDragging && "border-ink bg-plaster",
        )}
      >
        <input
          ref={inputRef}
          id={id}
          type="file"
          multiple
          accept={ATTACHMENT_ACCEPT}
          tabIndex={-1}
          aria-describedby={
            messages.length > 0 ? `${hintId} ${errorId}` : hintId
          }
          aria-invalid={messages.length > 0 ? true : undefined}
          className="sr-only"
          onChange={(event) => {
            addFiles(Array.from(event.target.files ?? []));
            // Clear the native value so choosing the same file again after
            // removing it still fires a change event.
            event.target.value = "";
          }}
        />

        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => inputRef.current?.click()}
        >
          <Paperclip aria-hidden="true" />
          {t("contact.attachments.add")}
        </Button>

        <p id={hintId} className="text-ink-soft text-sm">
          {t("contact.attachments.hint", limits)}
        </p>

        {files.length > 0 && (
          <ul
            aria-label={t("contact.attachments.list")}
            className="divide-rule w-full divide-y"
          >
            {files.map((file) => (
              <li
                key={`${file.name}-${file.size}-${file.lastModified}`}
                className="flex items-center justify-between gap-3 py-2"
              >
                <span className="text-ink min-w-0 truncate text-sm">
                  {file.name}
                  <span className="text-ink-soft ml-2 tabular-nums">
                    {formatFileSize(file.size)}
                  </span>
                </span>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="size-8 shrink-0"
                  aria-label={t("contact.attachments.remove", {
                    name: file.name,
                  })}
                  onClick={() => {
                    setProblems([]);
                    onChange(files.filter((item) => item !== file));
                  }}
                >
                  <X aria-hidden="true" />
                </Button>
              </li>
            ))}
          </ul>
        )}
      </div>

      {messages.length > 0 && (
        <ul id={errorId} className="text-destructive space-y-1 text-sm">
          {messages.map((message) => (
            <li key={message}>{message}</li>
          ))}
        </ul>
      )}
    </div>
  );
}
