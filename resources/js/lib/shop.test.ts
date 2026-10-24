import { describe, expect, it } from "vitest";
import { clampQuantity, formatCents } from "./shop";

/** Intl separates the number and symbol with a (narrow) no-break space. */
const plain = (text: string) => text.replace(/\s/g, " ");

describe("clampQuantity", () => {
  it("leaves a value inside the range alone", () => {
    expect(clampQuantity(5, 1, 99)).toBe(5);
    expect(clampQuantity(1, 1, 99)).toBe(1);
    expect(clampQuantity(99, 1, 99)).toBe(99);
  });

  it("pulls values outside the range back to its edges", () => {
    expect(clampQuantity(0, 1, 99)).toBe(1);
    expect(clampQuantity(-4, 1, 99)).toBe(1);
    expect(clampQuantity(100, 1, 99)).toBe(99);
  });

  it("drops any fraction", () => {
    expect(clampQuantity(2.9, 1, 99)).toBe(2);
    expect(clampQuantity(0.5, 1, 99)).toBe(1);
  });

  it("falls back to the minimum for values that are not numbers", () => {
    expect(clampQuantity(Number.NaN, 1, 99)).toBe(1);
    expect(clampQuantity(Number.POSITIVE_INFINITY, 1, 99)).toBe(1);
  });
});

describe("formatCents", () => {
  it("formats whole cents as euros with two decimals", () => {
    expect(plain(formatCents(1999, "lt"))).toBe("19,99 €");
    expect(formatCents(1999, "en")).toBe("€19.99");
  });

  it("shows trailing zeros", () => {
    expect(formatCents(500, "en")).toBe("€5.00");
    expect(formatCents(1, "en")).toBe("€0.01");
  });

  it("is exact for amounts that floating point struggles with", () => {
    expect(formatCents(29, "en")).toBe("€0.29");
    expect(formatCents(10_000_000, "en")).toBe("€100,000.00");
  });
});
