import { describe, expect, it } from "vitest";
import {
  BATHROOM_LIMITS,
  DEFAULT_SELECTION,
  PANEL_PRICES,
  PANEL_TYPES,
  PROPERTY_TYPES,
  PROPERTY_TYPE_PRICES,
  ROOM_LIMITS,
  calculatePriceLines,
  calculateTotal,
  clampCount,
  facadeLightingPrice,
  selectPropertyType,
  type PriceSelection,
  type PropertyType,
} from "./price-calculator";

function selection(overrides: Partial<PriceSelection> = {}): PriceSelection {
  return { ...DEFAULT_SELECTION, ...overrides };
}

describe("base prices", () => {
  it.each<[PropertyType, number]>([
    ["apartment", 600],
    ["cottage", 750],
    ["house", 1000],
  ])("prices a %s at %d", (propertyType, price) => {
    expect(PROPERTY_TYPE_PRICES[propertyType]).toBe(price);
  });

  it("prices each room at 300 and each bathroom at 250", () => {
    const lines = calculatePriceLines(
      selection({ rooms: 3, bathrooms: 2 }),
    );

    expect(lines.find((line) => line.key === "rooms")).toMatchObject({
      amount: 900,
      quantity: 3,
      unitPrice: 300,
    });
    expect(lines.find((line) => line.key === "bathrooms")).toMatchObject({
      amount: 500,
      quantity: 2,
      unitPrice: 250,
    });
  });
});

describe("panel prices depend on the property type", () => {
  it.each<[PropertyType, [number, number, number]]>([
    ["apartment", [200, 250, 300]],
    ["cottage", [250, 300, 350]],
    ["house", [300, 370, 500]],
  ])("%s: standard, secure and maximum", (propertyType, expected) => {
    expect(PANEL_TYPES.map((panel) => PANEL_PRICES[propertyType][panel])).toEqual(
      expected,
    );
  });

  it("uses the price of the chosen panel for the chosen property type", () => {
    const lines = calculatePriceLines(
      selection({ propertyType: "house", panel: "secure" }),
    );

    expect(lines.find((line) => line.key === "panel")?.amount).toBe(370);
  });

  it("defines a price for every property type and panel combination", () => {
    for (const propertyType of PROPERTY_TYPES) {
      for (const panel of PANEL_TYPES) {
        expect(PANEL_PRICES[propertyType][panel]).toBeGreaterThan(0);
      }
    }
  });
});

describe("facade lighting", () => {
  it("is not offered for an apartment", () => {
    expect(facadeLightingPrice("apartment")).toBeNull();
  });

  it.each<[PropertyType, number]>([
    ["cottage", 155],
    ["house", 275],
  ])("costs %s buyers %d", (propertyType, price) => {
    expect(facadeLightingPrice(propertyType)).toBe(price);
  });

  it("adds a line for a cottage or house when switched on", () => {
    const cottage = calculatePriceLines(
      selection({ propertyType: "cottage", facadeLighting: true }),
    );
    const house = calculatePriceLines(
      selection({ propertyType: "house", facadeLighting: true }),
    );

    expect(cottage.find((line) => line.key === "facadeLighting")?.amount).toBe(
      155,
    );
    expect(house.find((line) => line.key === "facadeLighting")?.amount).toBe(
      275,
    );
  });

  it("adds no line when switched off", () => {
    const lines = calculatePriceLines(
      selection({ propertyType: "house", facadeLighting: false }),
    );

    expect(lines.some((line) => line.key === "facadeLighting")).toBe(false);
  });

  it("ignores a stale facade lighting flag on an apartment", () => {
    const lines = calculatePriceLines(
      selection({ propertyType: "apartment", facadeLighting: true }),
    );

    expect(lines.some((line) => line.key === "facadeLighting")).toBe(false);
  });

  it("is switched off when the selection moves to an apartment", () => {
    const house = selection({ propertyType: "house", facadeLighting: true });

    expect(selectPropertyType(house, "apartment").facadeLighting).toBe(false);
  });

  it("keeps the choice when moving between a cottage and a house", () => {
    const cottage = selection({
      propertyType: "cottage",
      facadeLighting: true,
    });

    expect(selectPropertyType(cottage, "house")).toMatchObject({
      propertyType: "house",
      facadeLighting: true,
    });
  });
});

describe("total", () => {
  it("sums the default apartment selection", () => {
    // 600 + 3 × 300 + 1 × 250 + 200 (standard panel)
    expect(calculateTotal(DEFAULT_SELECTION)).toBe(1950);
  });

  it("sums a house with every option", () => {
    // 1000 + 4 × 300 + 2 × 250 + 275 (facade) + 500 (maximum panel)
    expect(
      calculateTotal({
        propertyType: "house",
        rooms: 4,
        bathrooms: 2,
        facadeLighting: true,
        panel: "maximum",
      }),
    ).toBe(3475);
  });

  it("sums a cottage with a secure panel and no extras", () => {
    // 750 + 2 × 300 + 0 × 250 + 300 (secure panel)
    expect(
      calculateTotal(
        selection({
          propertyType: "cottage",
          rooms: 2,
          bathrooms: 0,
          panel: "secure",
        }),
      ),
    ).toBe(1650);
  });

  it("clamps counts to their limits", () => {
    const lines = calculatePriceLines(selection({ rooms: 99, bathrooms: -4 }));

    expect(lines.find((line) => line.key === "rooms")?.quantity).toBe(
      ROOM_LIMITS.max,
    );
    expect(lines.find((line) => line.key === "bathrooms")?.quantity).toBe(
      BATHROOM_LIMITS.min,
    );
  });
});

describe("clampCount", () => {
  it("rounds to a whole number inside the limits", () => {
    expect(clampCount(2.6, ROOM_LIMITS)).toBe(3);
    expect(clampCount(0, ROOM_LIMITS)).toBe(ROOM_LIMITS.min);
    expect(clampCount(11, ROOM_LIMITS)).toBe(ROOM_LIMITS.max);
  });

  it("falls back to the minimum for non-numbers", () => {
    expect(clampCount(Number.NaN, ROOM_LIMITS)).toBe(ROOM_LIMITS.min);
    expect(clampCount(Number.POSITIVE_INFINITY, ROOM_LIMITS)).toBe(
      ROOM_LIMITS.min,
    );
  });
});
