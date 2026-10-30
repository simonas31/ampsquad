import {
  BatteryCharging,
  BellRing,
  Cable,
  Cctv,
  DoorOpen,
  Hammer,
  HardDrive,
  KeyRound,
  Network,
  Package,
  PlugZap,
  Server,
  ShieldCheck,
  Sun,
  Wrench,
  Zap,
  type LucideIcon,
} from "lucide-react";

/**
 * Which icon stands for a category. Categories come from the catalog import
 * and carry no icon of their own, so the name decides: the first rule with a
 * word that occurs in it wins, in lt and en. Words are stems ("stebėj") so
 * every inflection matches. The order matters where a name holds two
 * ("Saulės elektrinės" is solar, not electrical). Anything else gets the
 * plain box.
 */
const rules: { icon: LucideIcon; words: string[] }[] = [
  { icon: Cctv, words: ["stebėj", "kamer", "video", "surveillance", "cctv"] },
  { icon: BellRing, words: ["gaisr", "signaliz", "fire", "alarm"] },
  { icon: DoorOpen, words: ["domofon", "intercom"] },
  { icon: KeyRound, words: ["priei", "access control"] },
  { icon: ShieldCheck, words: ["apsaug", "saugum", "secur", "protect"] },
  { icon: HardDrive, words: ["duomen", "saugojim", "storage"] },
  { icon: Network, words: ["tinkl", "network", "wi-fi", "wifi"] },
  { icon: Sun, words: ["saul", "solar"] },
  { icon: PlugZap, words: ["įkrov", "ikrov", "elektromobil", "charging"] },
  { icon: BatteryCharging, words: ["akumuliat", "maitinim", "baterij", "battery", "batteries", "power supply"] },
  { icon: Server, words: ["spint", "stovai", "cabinet", "rack"] },
  { icon: Cable, words: ["kabel", "laid", "cable"] },
  { icon: Wrench, words: ["montav", "kronšt", "mounting", "bracket"] },
  { icon: Hammer, words: ["įrank", "irank", "tool"] },
  { icon: Zap, words: ["elektr", "energ", "electric", "energy"] },
];

export function categoryIcon(name: string): LucideIcon {
  const lowered = name.toLocaleLowerCase();

  return (
    rules.find(({ words }) => words.some((word) => lowered.includes(word)))
      ?.icon ?? Package
  );
}
