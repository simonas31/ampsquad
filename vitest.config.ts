import path from "path";
import { defineConfig } from "vitest/config";

// Separate from vite.config.ts on purpose: the unit tests exercise plain
// modules and need neither the Laravel, React nor Tailwind plugins.
export default defineConfig({
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "resources/js"),
    },
  },
  test: {
    environment: "node",
    include: ["resources/js/**/*.test.ts"],
  },
});
