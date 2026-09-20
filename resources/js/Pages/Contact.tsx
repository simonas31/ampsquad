import { useForm, usePage } from "@inertiajs/react";
import { CircleCheck, Mail, MapPin, Phone } from "lucide-react";
import { Reveal } from "@/components/common/Reveal";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Field } from "@/components/ui/field";
import { Input, Textarea } from "@/components/ui/input";
import { useT } from "@/hooks/use-t";
import { telHref } from "@/lib/utils";
import type { Breadcrumb } from "@/types";

const fieldOrder = ["name", "email", "phone", "message"] as const;

export default function Contact({
  breadcrumbs,
}: {
  breadcrumbs: Breadcrumb[];
}) {
  const t = useT();
  const {
    flash,
    props: { site },
  } = usePage();

  const form = useForm({
    name: "",
    email: "",
    phone: "",
    message: "",
    company: "", // honeypot — real visitors never see or fill this in
  });

  function submit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    form.post(window.location.pathname, {
      preserveScroll: true,
      onSuccess: () => form.reset(),
      // Send keyboard/screen reader focus to the first field that
      // failed, so the error is announced instead of sitting unseen.
      onError: (errors) => {
        const firstInvalid = fieldOrder.find((name) => name in errors);

        if (firstInvalid) {
          document.getElementById(firstInvalid)?.focus();
        }
      },
    });
  }

  return (
    <>
      <PageHeader
        breadcrumbs={breadcrumbs}
        title={t("nav.contact")}
        description={t("contact.intro")}
      />

      <Container className="py-10 lg:py-14">
        <div className="grid gap-8 lg:grid-cols-[3fr_2fr] lg:items-start">
          <Reveal>
            <Card className="p-6 sm:p-8">
              <div role="status">
                {flash.contactSubmitted && (
                  <div className="border-success bg-success/10 text-foreground mb-8 flex items-start gap-3 rounded-md border p-4">
                    <CircleCheck
                      className="text-success mt-0.5 size-5 shrink-0"
                      aria-hidden="true"
                    />
                    <p className="text-sm font-medium">
                      {t("contact.success")}
                    </p>
                  </div>
                )}
              </div>

              <form className="space-y-5" onSubmit={submit}>
                <p className="text-muted-foreground text-sm">
                  {t("contact.requiredHint")}
                </p>

                <Field
                  id="name"
                  label={t("contact.name")}
                  error={form.errors.name}
                  required
                >
                  {(control) => (
                    <Input
                      {...control}
                      value={form.data.name}
                      onChange={(event) =>
                        form.setData("name", event.target.value)
                      }
                      autoComplete="name"
                    />
                  )}
                </Field>

                <Field
                  id="email"
                  label={t("contact.email")}
                  error={form.errors.email}
                  required
                >
                  {(control) => (
                    <Input
                      {...control}
                      type="email"
                      value={form.data.email}
                      onChange={(event) =>
                        form.setData("email", event.target.value)
                      }
                      autoComplete="email"
                    />
                  )}
                </Field>

                <Field
                  id="phone"
                  label={t("contact.phone")}
                  error={form.errors.phone}
                >
                  {(control) => (
                    <Input
                      {...control}
                      type="tel"
                      value={form.data.phone}
                      onChange={(event) =>
                        form.setData("phone", event.target.value)
                      }
                      autoComplete="tel"
                    />
                  )}
                </Field>

                <Field
                  id="message"
                  label={t("contact.message")}
                  error={form.errors.message}
                  required
                >
                  {(control) => (
                    <Textarea
                      {...control}
                      rows={6}
                      value={form.data.message}
                      onChange={(event) =>
                        form.setData("message", event.target.value)
                      }
                    />
                  )}
                </Field>

                {/* Honeypot: hidden from real visitors, bots fill it in blindly. */}
                <div className="sr-only" aria-hidden="true">
                  <label htmlFor="company">Company</label>
                  <input
                    id="company"
                    type="text"
                    tabIndex={-1}
                    autoComplete="off"
                    value={form.data.company}
                    onChange={(event) =>
                      form.setData("company", event.target.value)
                    }
                  />
                </div>

                <Button
                  type="submit"
                  variant="accent"
                  size="lg"
                  className="w-full sm:w-auto"
                  disabled={form.processing}
                >
                  {form.processing ? t("contact.sending") : t("contact.submit")}
                </Button>
              </form>
            </Card>
          </Reveal>

          <Reveal delay={100}>
            <aside className="surface-navy bg-navy text-navy-foreground rounded-lg p-6 sm:p-8">
              <h2 className="text-xl text-white">{t("contact.details")}</h2>
              <address className="mt-6 flex flex-col gap-5 not-italic">
                <a
                  href={telHref(site.contact.phone)}
                  className="flex items-start gap-3 hover:underline"
                >
                  <Phone
                    className="text-accent mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                  />
                  <span>
                    <span className="text-navy-muted block text-sm">
                      {t("contact.phone")}
                    </span>
                    <span className="font-semibold">{site.contact.phone}</span>
                  </span>
                </a>
                <a
                  href={`mailto:${site.contact.email}`}
                  className="flex items-start gap-3 hover:underline"
                >
                  <Mail
                    className="text-accent mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                  />
                  <span>
                    <span className="text-navy-muted block text-sm">
                      {t("contact.email")}
                    </span>
                    <span className="font-semibold break-all">
                      {site.contact.email}
                    </span>
                  </span>
                </a>
                <div className="flex items-start gap-3">
                  <MapPin
                    className="text-accent mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                  />
                  <span>
                    <span className="text-navy-muted block text-sm">
                      {t("contact.address")}
                    </span>
                    <span className="font-semibold">
                      {site.contact.address}
                    </span>
                  </span>
                </div>
              </address>
            </aside>
          </Reveal>
        </div>
      </Container>
    </>
  );
}
