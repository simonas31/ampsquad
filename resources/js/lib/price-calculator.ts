/**
 * Pricing rules for the homepage price calculator. Every option is a plain
 * addition — none of them changes the price of another — so the total is a
 * sum of independent line items. All amounts are whole euros, excluding VAT.
 *
 * Kept free of React and i18n so the rules can be read, and exercised, on
 * their own.
 */

export const PROPERTY_TYPES = ["apartment", "cottage", "house"] as const;
export const PANEL_TYPES = ["standard", "secure", "maximum"] as const;

export type PropertyType = (typeof PROPERTY_TYPES)[number];
export type PanelType = (typeof PANEL_TYPES)[number];

export interface PriceSelection {
  propertyType: PropertyType;
  rooms: number;
  bathrooms: number;
  facadeLighting: boolean;
  panel: PanelType;
}

export interface CountLimits {
  min: number;
  max: number;
}

export const PROPERTY_TYPE_PRICES: Record<PropertyType, number> = {
  apartment: 610,
  cottage: 715,
  house: 960,
};

export const PANEL_PRICES: Record<PanelType, number> = {
  standard: 216,
  secure: 360,
  maximum: 504,
};

export const ROOM_PRICE = 297;
export const BATHROOM_PRICE = 250;
export const FACADE_LIGHTING_PRICE = 144;

export const ROOM_LIMITS: CountLimits = { min: 1, max: 10 };
export const BATHROOM_LIMITS: CountLimits = { min: 0, max: 5 };

export const DEFAULT_SELECTION: PriceSelection = {
  propertyType: "apartment",
  rooms: 3,
  bathrooms: 1,
  facadeLighting: false,
  panel: "standard",
};

export type PriceLineKey =
  | "propertyType"
  | "rooms"
  | "bathrooms"
  | "facadeLighting"
  | "panel";

export interface PriceLine {
  key: PriceLineKey;
  amount: number;
  /** Set for per-unit lines (rooms, bathrooms). */
  quantity?: number;
  unitPrice?: number;
}

/** Snaps a count to a whole number inside the limits; non-numbers fall back to the minimum. */
export function clampCount(value: number, { min, max }: CountLimits): number {
  if (!Number.isFinite(value)) {
    return min;
  }

  return Math.min(max, Math.max(min, Math.round(value)));
}

/**
 * The priced parts of a selection, in display order. Optional extras that
 * are switched off contribute no line.
 */
export function calculatePriceLines(selection: PriceSelection): PriceLine[] {
  const rooms = clampCount(selection.rooms, ROOM_LIMITS);
  const bathrooms = clampCount(selection.bathrooms, BATHROOM_LIMITS);

  const lines: PriceLine[] = [
    {
      key: "propertyType",
      amount: PROPERTY_TYPE_PRICES[selection.propertyType],
    },
    {
      key: "rooms",
      amount: rooms * ROOM_PRICE,
      quantity: rooms,
      unitPrice: ROOM_PRICE,
    },
    {
      key: "bathrooms",
      amount: bathrooms * BATHROOM_PRICE,
      quantity: bathrooms,
      unitPrice: BATHROOM_PRICE,
    },
  ];

  if (selection.facadeLighting) {
    lines.push({ key: "facadeLighting", amount: FACADE_LIGHTING_PRICE });
  }

  lines.push({ key: "panel", amount: PANEL_PRICES[selection.panel] });

  return lines;
}

export function calculateTotal(selection: PriceSelection): number {
  return calculatePriceLines(selection).reduce(
    (total, line) => total + line.amount,
    0,
  );
}
