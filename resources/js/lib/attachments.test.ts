import { describe, expect, it } from "vitest";
import {
  ATTACHMENT_LIMITS,
  formatFileSize,
  mergeAttachments,
} from "./attachments";

function file(name: string, sizeBytes = 1024, lastModified = 1): File {
  const content = new Uint8Array(sizeBytes);

  return new File([content], name, { lastModified });
}

describe("mergeAttachments", () => {
  it("accepts allowed files up to the limit", () => {
    const incoming = [file("plan.pdf"), file("photo.JPG"), file("layout.dwg")];

    const { files, problems } = mergeAttachments([], incoming);

    expect(files).toHaveLength(3);
    expect(problems).toEqual([]);
  });

  it("skips a disallowed type and keeps the rest", () => {
    const { files, problems } = mergeAttachments(
      [],
      [file("plan.pdf"), file("virus.exe")],
    );

    expect(files.map((item) => item.name)).toEqual(["plan.pdf"]);
    expect(problems).toEqual([{ kind: "badType", name: "virus.exe" }]);
  });

  it("skips a file without an extension", () => {
    const { files, problems } = mergeAttachments([], [file("README")]);

    expect(files).toEqual([]);
    expect(problems).toEqual([{ kind: "badType", name: "README" }]);
  });

  it("skips a file over the size limit", () => {
    const tooBig = file(
      "scan.pdf",
      ATTACHMENT_LIMITS.maxSizeMb * 1024 * 1024 + 1,
    );

    const { files, problems } = mergeAttachments([], [tooBig]);

    expect(files).toEqual([]);
    expect(problems).toEqual([{ kind: "tooLarge", name: "scan.pdf" }]);
  });

  it("accepts a file exactly at the size limit", () => {
    const atLimit = file("scan.pdf", ATTACHMENT_LIMITS.maxSizeMb * 1024 * 1024);

    expect(mergeAttachments([], [atLimit]).files).toHaveLength(1);
  });

  it("stops at the file limit and reports it once", () => {
    const current = Array.from({ length: ATTACHMENT_LIMITS.maxFiles - 1 }, (_, i) =>
      file(`existing-${i}.png`),
    );

    const { files, problems } = mergeAttachments(current, [
      file("a.png"),
      file("b.png"),
      file("c.png"),
    ]);

    expect(files).toHaveLength(ATTACHMENT_LIMITS.maxFiles);
    expect(problems).toEqual([{ kind: "tooMany" }]);
  });

  it("ignores a file that is already attached", () => {
    const first = file("plan.pdf", 2048, 5);

    const { files, problems } = mergeAttachments(
      [first],
      [file("plan.pdf", 2048, 5)],
    );

    expect(files).toHaveLength(1);
    expect(problems).toEqual([]);
  });

  it("does not change the list it was given", () => {
    const current = [file("a.png")];

    mergeAttachments(current, [file("b.png")]);

    expect(current).toHaveLength(1);
  });
});

describe("formatFileSize", () => {
  it("shows small files in kilobytes, at least 1", () => {
    expect(formatFileSize(10)).toBe("1 KB");
    expect(formatFileSize(820 * 1024)).toBe("820 KB");
  });

  it("shows larger files in megabytes", () => {
    expect(formatFileSize(3.4 * 1024 * 1024)).toBe("3.4 MB");
  });
});
