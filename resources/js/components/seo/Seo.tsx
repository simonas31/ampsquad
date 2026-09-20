import { Head } from "@inertiajs/react";
import type { SeoData } from "@/types";

export function Seo({ seo }: { seo: SeoData }) {
  const twitterCard = seo.ogImage ? "summary_large_image" : "summary";

  return (
    <Head title={seo.title}>
      {seo.description && <meta name="description" content={seo.description} />}
      {seo.noindex && <meta name="robots" content="noindex, nofollow" />}
      {seo.canonical && <link rel="canonical" href={seo.canonical} />}

      {seo.alternates.map((alternate) => (
        <link
          key={alternate.locale}
          rel="alternate"
          hrefLang={alternate.locale}
          href={alternate.url}
        />
      ))}

      <meta property="og:title" content={seo.title} />
      {seo.description && (
        <meta property="og:description" content={seo.description} />
      )}
      <meta property="og:type" content={seo.ogType} />
      {seo.canonical && <meta property="og:url" content={seo.canonical} />}
      {seo.ogImage && <meta property="og:image" content={seo.ogImage} />}

      <meta name="twitter:card" content={twitterCard} />
      <meta name="twitter:title" content={seo.title} />
      {seo.description && (
        <meta name="twitter:description" content={seo.description} />
      )}
      {seo.ogImage && <meta name="twitter:image" content={seo.ogImage} />}

      {seo.jsonLd.map((schema, index) => (
        <script key={index} type="application/ld+json">
          {JSON.stringify(schema)}
        </script>
      ))}
    </Head>
  );
}
