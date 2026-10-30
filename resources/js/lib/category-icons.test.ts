import { Cable, Cctv, Network, Package, Sun, Zap } from "lucide-react";
import { describe, expect, it } from "vitest";
import { categoryIcon } from "./category-icons";

describe("categoryIcon", () => {
  it("finds the icon for a Lithuanian name whatever its inflection", () => {
    expect(categoryIcon("Vaizdo stebėjimo sistemos")).toBe(Cctv);
    expect(categoryIcon("Tinklo įranga")).toBe(Network);
    expect(categoryIcon("Kabeliai ir laidai")).toBe(Cable);
  });

  it("finds the icon for an English name", () => {
    expect(categoryIcon("Video surveillance")).toBe(Cctv);
    expect(categoryIcon("Network equipment")).toBe(Network);
  });

  it("ignores case", () => {
    expect(categoryIcon("TINKLO ĮRANGA")).toBe(Network);
  });

  it("lets the more specific word win over a general one", () => {
    expect(categoryIcon("Saulės elektrinės")).toBe(Sun);
    expect(categoryIcon("Elektros įranga")).toBe(Zap);
  });

  it("falls back to a plain box for a name it does not know", () => {
    expect(categoryIcon("Įvairios prekės")).toBe(Package);
    expect(categoryIcon("")).toBe(Package);
  });
});
