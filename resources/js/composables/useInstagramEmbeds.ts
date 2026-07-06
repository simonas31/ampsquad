declare global {
    interface Window {
        instgrm?: { Embeds: { process: () => void } };
    }
}

let scriptPromise: Promise<void> | null = null;

/**
 * Instagram's embed.js reads `instagram-media` blockquotes present in the DOM
 * at the moment it loads (or when `.process()` is called again) and swaps
 * them for the real rendered post — it isn't a plain iframe src. Loaded once
 * and reused, since re-injecting the script on every carousel mount would
 * re-fetch it needlessly across SPA navigations.
 */
export function useInstagramEmbeds(): { reprocess: () => void } {
    function loadScript(): Promise<void> {
        if (window.instgrm) {
            return Promise.resolve();
        }

        scriptPromise ??= new Promise((resolve) => {
            const script = document.createElement("script");
            script.src = "https://www.instagram.com/embed.js";
            script.async = true;
            script.onload = () => resolve();
            document.body.appendChild(script);
        });

        return scriptPromise;
    }

    function reprocess(): void {
        void loadScript().then(() => window.instgrm?.Embeds.process());
    }

    return { reprocess };
}
