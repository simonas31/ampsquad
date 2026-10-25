import { describe, expect, it } from "vitest";
import type { ShopFilters } from "@/types";
import { clampQuantity, formatCents, hasActiveFilters, shopUrl } from "./shop";

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

const noFilters: ShopFilters = {
  search: "",
  category: null,
  manufacturer: null,
  inStock: false,
  minPrice: "",
  maxPrice: "",
  sort: "featured",
};

describe("shopUrl", () => {
  it("keeps the plain shop URL when nothing is filtered", () => {
    expect(shopUrl("/shop", noFilters)).toBe("/shop");
    expect(shopUrl("/shop", {})).toBe("/shop");
  });

  it("writes each filter under the name the server reads", () => {
    expect(
      shopUrl("/shop", {
        search: "switch",
        category: "tinklo-iranga",
        manufacturer: 12,
        inStock: true,
        minPrice: "10",
        maxPrice: "99.50",
        sort: "price_desc",
      }),
    ).toBe(
      "/shop?q=switch&category=tinklo-iranga&manufacturer=12&in_stock=1&min_price=10&max_price=99.50&sort=price_desc",
    );
  });

  it("leaves out empty and default values", () => {
    expect(
      shopUrl("/shop", {
        ...noFilters,
        search: "   ",
        minPrice: " ",
        sort: "featured",
        category: "kabeliai",
      }),
    ).toBe("/shop?category=kabeliai");
  });

  it("encodes what the visitor typed", () => {
    expect(shopUrl("/shop", { search: "kronšteinas & 100%" })).toBe(
      "/shop?q=kron%C5%A1teinas+%26+100%25",
    );
  });

  it("keeps the locale prefix of the base", () => {
    expect(shopUrl("/en/shop", { inStock: true })).toBe("/en/shop?in_stock=1");
  });
});

describe("hasActiveFilters", () => {
  it("is false for the plain shop", () => {
    expect(hasActiveFilters(noFilters)).toBe(false);
  });

  it("is true when anything narrows or reorders it", () => {
    expect(hasActiveFilters({ ...noFilters, search: "x" })).toBe(true);
    expect(hasActiveFilters({ ...noFilters, category: "a" })).toBe(true);
    expect(hasActiveFilters({ ...noFilters, manufacturer: 3 })).toBe(true);
    expect(hasActiveFilters({ ...noFilters, inStock: true })).toBe(true);
    expect(hasActiveFilters({ ...noFilters, maxPrice: "5" })).toBe(true);
    expect(hasActiveFilters({ ...noFilters, sort: "name" })).toBe(true);
  });
});
