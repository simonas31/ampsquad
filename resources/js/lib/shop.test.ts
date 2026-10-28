import { describe, expect, it } from "vitest";
import type { CartData, OrderStatus, ShopFilters } from "@/types";
import {
  canCheckout,
  clampQuantity,
  formatCents,
  formatClock,
  hasActiveFilters,
  ORDER_POLL_LIMIT,
  orderStage,
  shopUrl,
  shouldPollOrder,
} from "./shop";

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
  sort: "availability",
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
        sort: "availability",
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

const cartWith = (changes: Partial<CartData> = {}): CartData => ({
  items: [{} as CartData["items"][number]],
  subtotalExclVatCents: 1000,
  vatRate: 21,
  vatCents: 210,
  totalInclVatCents: 1210,
  totalQuantity: 1,
  hasReducedItems: false,
  hasUnavailableItems: false,
  clearUrl: "/cart",
  checkoutUrl: "/checkout",
  ...changes,
});

describe("canCheckout", () => {
  it("lets a cart with something to order go on", () => {
    expect(canCheckout(cartWith())).toBe(true);
  });

  it("stops an empty cart", () => {
    expect(canCheckout(cartWith({ items: [], totalQuantity: 0 }))).toBe(false);
  });

  it("stops a cart whose only lines count for nothing", () => {
    expect(canCheckout(cartWith({ totalQuantity: 0 }))).toBe(false);
  });

  it("stops a cart with a line that is cut short or gone, since checkout would refuse it", () => {
    expect(canCheckout(cartWith({ hasReducedItems: true }))).toBe(false);
    expect(canCheckout(cartWith({ hasUnavailableItems: true }))).toBe(false);
  });
});

describe("orderStage", () => {
  it("calls an order paid from the moment the server says so, shipped or not", () => {
    expect(orderStage("paid")).toBe("paid");
    expect(orderStage("shipped")).toBe("paid");
  });

  it("calls an order that waits for its payment waiting", () => {
    expect(orderStage("pending_payment")).toBe("waiting");
  });

  it("calls an order that expired or was cancelled closed", () => {
    expect(orderStage("expired")).toBe("closed");
    expect(orderStage("cancelled")).toBe("closed");
  });

  it("keeps a refund apart from an order that was never paid", () => {
    expect(orderStage("refunded")).toBe("refunded");
  });

  it("has a stage for every status", () => {
    const statuses: OrderStatus[] = ["pending_payment", "paid", "shipped", "cancelled", "expired", "refunded"];

    expect(statuses.map(orderStage).every(Boolean)).toBe(true);
  });
});

describe("shouldPollOrder", () => {
  it("asks again while the order waits, up to the limit", () => {
    expect(shouldPollOrder("pending_payment", 0)).toBe(true);
    expect(shouldPollOrder("pending_payment", ORDER_POLL_LIMIT - 1)).toBe(true);
  });

  it("gives up at the limit", () => {
    expect(shouldPollOrder("pending_payment", ORDER_POLL_LIMIT)).toBe(false);
  });

  it("never asks about an order that is not waiting", () => {
    for (const status of ["paid", "shipped", "cancelled", "expired", "refunded"] as const) {
      expect(shouldPollOrder(status, 0)).toBe(false);
    }
  });
});

describe("formatClock", () => {
  it("shows the time of day on a 24 hour clock", () => {
    expect(formatClock("2026-10-28T14:35:00Z", "en", "UTC")).toBe("14:35");
    expect(formatClock("2026-10-28T14:35:00Z", "lt", "UTC")).toBe("14:35");
  });

  it("pads the hour", () => {
    expect(formatClock("2026-10-28T07:05:00Z", "en", "UTC")).toBe("07:05");
  });

  it("follows the time zone", () => {
    expect(formatClock("2026-10-28T14:35:00Z", "en", "Europe/Vilnius")).toBe("16:35");
  });
});
