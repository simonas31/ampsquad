import { createI18n } from "vue-i18n";
import en from "./en.json";
import lt from "./lt.json";

export const i18n = createI18n({
    legacy: false,
    locale: "lt",
    fallbackLocale: "en",
    messages: { lt, en },
});
