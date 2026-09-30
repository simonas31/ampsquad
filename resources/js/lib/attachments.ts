/**
 * Client-side limits for files attached to an enquiry. They mirror
 * ContactRequest::MAX_ATTACHMENTS, MAX_ATTACHMENT_KILOBYTES and
 * ATTACHMENT_EXTENSIONS on the server, which stays the authority; checking
 * here only spares the visitor a failed upload.
 *
 * Kept free of React so the rules can be exercised on their own.
 */

export const ATTACHMENT_LIMITS = {
  maxFiles: 5,
  maxSizeMb: 10,
  extensions: [
    "jpg",
    "jpeg",
    "png",
    "webp",
    "heic",
    "pdf",
    "doc",
    "docx",
    "xls",
    "xlsx",
    "dwg",
    "dxf",
    "zip",
  ],
} as const;

/** Value for an <input type="file" accept> attribute. */
export const ATTACHMENT_ACCEPT = ATTACHMENT_LIMITS.extensions
  .map((extension) => `.${extension}`)
  .join(",");

export type AttachmentProblem =
  | { kind: "tooMany" }
  | { kind: "tooLarge"; name: string }
  | { kind: "badType"; name: string };

export interface MergedAttachments {
  files: File[];
  problems: AttachmentProblem[];
}

function extensionOf(name: string): string {
  const dot = name.lastIndexOf(".");

  return dot === -1 ? "" : name.slice(dot + 1).toLowerCase();
}

/**
 * Adds newly chosen files to the ones already attached. A file that is too
 * large, of a disallowed type, already attached, or that would exceed the
 * file limit is skipped and reported, so one bad file never discards the
 * rest of the selection.
 */
export function mergeAttachments(
  current: File[],
  incoming: File[],
): MergedAttachments {
  const maxBytes = ATTACHMENT_LIMITS.maxSizeMb * 1024 * 1024;
  const files = [...current];
  const problems: AttachmentProblem[] = [];
  let reportedTooMany = false;

  for (const file of incoming) {
    const isDuplicate = files.some(
      (existing) =>
        existing.name === file.name &&
        existing.size === file.size &&
        existing.lastModified === file.lastModified,
    );

    if (isDuplicate) {
      continue;
    }

    if (
      !(ATTACHMENT_LIMITS.extensions as readonly string[]).includes(
        extensionOf(file.name),
      )
    ) {
      problems.push({ kind: "badType", name: file.name });
      continue;
    }

    if (file.size > maxBytes) {
      problems.push({ kind: "tooLarge", name: file.name });
      continue;
    }

    if (files.length >= ATTACHMENT_LIMITS.maxFiles) {
      if (!reportedTooMany) {
        problems.push({ kind: "tooMany" });
        reportedTooMany = true;
      }
      continue;
    }

    files.push(file);
  }

  return { files, problems };
}

/** "820 KB", "3.4 MB": a short size for the attached file list. */
export function formatFileSize(bytes: number): string {
  if (bytes < 1024 * 1024) {
    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  }

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
