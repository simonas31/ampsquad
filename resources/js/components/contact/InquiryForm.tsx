import { useForm, usePage } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input, Textarea } from "@/components/ui/input";
import { useT } from "@/hooks/use-t";

const fieldOrder = ["name", "email", "phone", "message"] as const;

/**
 * The enquiry form, shared by the home page's closing band and the contact
 * page. Both post to the same `contact.store` route, so the form takes the
 * action as a prop rather than assuming the current path: on the home page
 * the current path has no POST route of its own.
 */
export function InquiryForm({ action }: { action: string }) {
  const t = useT();
  const { flash } = usePage();

  const form = useForm({
    name: "",
    email: "",
    phone: "",
    message: "",
    company: "", // honeypot — real visitors never see or fill this in
  });

  function submit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    form.post(action, {
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
    <div>
      <div role="status">
        {flash.contactSubmitted && (
          <p className="border-signal text-ink mb-8 border-l-2 py-1 pl-4 text-sm">
            {t("contact.success")}
          </p>
        )}
      </div>

      <form className="space-y-7" onSubmit={submit}>
        <div className="grid gap-7 sm:grid-cols-2">
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
                onChange={(event) => form.setData("name", event.target.value)}
                autoComplete="name"
              />
            )}
          </Field>

          <Field id="phone" label={t("contact.phone")} error={form.errors.phone}>
            {(control) => (
              <Input
                {...control}
                type="tel"
                value={form.data.phone}
                onChange={(event) => form.setData("phone", event.target.value)}
                autoComplete="tel"
              />
            )}
          </Field>
        </div>

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
              onChange={(event) => form.setData("email", event.target.value)}
              autoComplete="email"
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
              rows={5}
              value={form.data.message}
              onChange={(event) => form.setData("message", event.target.value)}
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
            onChange={(event) => form.setData("company", event.target.value)}
          />
        </div>

        <div className="flex flex-wrap items-center gap-x-6 gap-y-3 pt-1">
          <Button
            type="submit"
            variant="accent"
            size="lg"
            disabled={form.processing}
          >
            {form.processing ? t("contact.sending") : t("contact.submit")}
          </Button>
          <p className="meta text-ink-soft">{t("contact.requiredHint")}</p>
        </div>
      </form>
    </div>
  );
}
