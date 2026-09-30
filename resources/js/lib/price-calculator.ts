/**
 * Pricing rules for the price calculator. The total is a sum of independent
 * line items, but two of them depend on the property type: the panel (skydas)
 * has its own price per type, and facade lighting is only offered for
 * cottages and houses. All amounts are whole euros, excluding VAT, and cover
 * labour only (materials are not priced).
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
  apartment: 600,
  cottage: 750,
  house: 1000,
};

export const PANEL_PRICES: Record<PropertyType, Record<PanelType, number>> = {
  apartment: { standard: 200, secure: 250, maximum: 300 },
  cottage: { standard: 250, secure: 300, maximum: 350 },
  house: { standard: 300, secure: 370, maximum: 500 },
};

/** Property types missing here (apartments) cannot have facade lighting. */
export const FACADE_LIGHTING_PRICES: Partial<Record<PropertyType, number>> = {
  cottage: 155,
  house: 275,
};

export const ROOM_PRICE = 300;
export const BATHROOM_PRICE = 250;

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

/** The facade lighting price for a property type, or null when it is not offered. */
export function facadeLightingPrice(propertyType: PropertyType): number | null {
  return FACADE_LIGHTING_PRICES[propertyType] ?? null;
}

/**
 * Switches the property type and drops any extra the new type cannot have,
 * so a facade lighting choice never outlives a switch back to an apartment.
 */
export function selectPropertyType(
  selection: PriceSelection,
  propertyType: PropertyType,
): PriceSelection {
  return {
    ...selection,
    propertyType,
    facadeLighting:
      selection.facadeLighting && facadeLightingPrice(propertyType) !== null,
  };
}

/**
 * The priced parts of a selection, in display order. Optional extras that
 * are switched off, or not available for the property type, contribute no
 * line.
 */
export function calculatePriceLines(selection: PriceSelection): PriceLine[] {
  const rooms = clampCount(selection.rooms, ROOM_LIMITS);
  const bathrooms = clampCount(selection.bathrooms, BATHROOM_LIMITS);
  const facadePrice = facadeLightingPrice(selection.propertyType);

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

  if (selection.facadeLighting && facadePrice !== null) {
    lines.push({ key: "facadeLighting", amount: facadePrice });
  }

  lines.push({
    key: "panel",
    amount: PANEL_PRICES[selection.propertyType][selection.panel],
  });

  return lines;
}

export function calculateTotal(selection: PriceSelection): number {
  return calculatePriceLines(selection).reduce(
    (total, line) => total + line.amount,
    0,
  );
}
