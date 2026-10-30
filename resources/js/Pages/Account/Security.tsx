import { useForm, usePage } from "@inertiajs/react";
import { AccountPage } from "@/components/account/AccountPage";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb } from "@/types";

interface SecurityProps {
  /** The address waiting for its link to be followed, if any. */
  pendingEmail: string | null;
  emailUrl: string;
  passwordUrl: string;
  deleteUrl: string;
  /** How long a deleted account's details are kept. */
  retentionYears: number;
  breadcrumbs: Breadcrumb[];
}

/** Must match Customer::PASSWORD_MIN_LENGTH on the server. */
const PASSWORD_MIN_LENGTH = 10;

/**
 * Where a customer changes the email address of their account and their
 * password. Both ask for the current password. The new address only takes
 * over when its link is followed; a new password logs the customer out
 * everywhere, which the form says before it is sent.
 */
export default function Security({
  pendingEmail,
  emailUrl,
  passwordUrl,
  deleteUrl,
  retentionYears,
  breadcrumbs,
}: SecurityProps) {
  const t = useT();
  const { props, flash } = usePage();
  const { account } = props;

  const emailForm = useForm({ email: "", current_password: "" });
  const passwordForm = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
  });
  const deleteForm = useForm({ current_password: "", confirm: false });

  const emailErrors: Record<string, string | undefined> = emailForm.errors;
  const passwordErrors: Record<string, string | undefined> =
    passwordForm.errors;
  const deleteErrors: Record<string, string | undefined> = deleteForm.errors;

  function submitEmail(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    // Both forms report a wrong "current_password", so each has its own bag
    // of errors, or a mistake in one would show under the other.
    emailForm.post(emailUrl, {
      errorBag: "email",
      preserveScroll: true,
      onSuccess: () => emailForm.reset(),
      onError: (errors) => {
        const firstInvalid = (["email", "current_password"] as const).find(
          (name) => errors[name],
        );

        if (firstInvalid) {
          document.getElementById(`email-${firstInvalid}`)?.focus();
        }
      },
      onFinish: () => emailForm.reset("current_password"),
    });
  }

  function submitPassword(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    passwordForm.put(passwordUrl, {
      errorBag: "password",
      preserveScroll: true,
      onError: (errors) => {
        const firstInvalid = (
          ["current_password", "password", "password_confirmation"] as const
        ).find((name) => errors[name]);

        if (firstInvalid) {
          document.getElementById(`password-${firstInvalid}`)?.focus();
        }
      },
      onFinish: () => passwordForm.reset(),
    });
  }

  function submitDelete(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    deleteForm.delete(deleteUrl, {
      errorBag: "delete",
      preserveScroll: true,
      onError: (errors) => {
        const firstInvalid = (["current_password", "confirm"] as const).find(
          (name) => errors[name],
        );

        if (firstInvalid) {
          document.getElementById(`delete-${firstInvalid}`)?.focus();
        }
      },
      onFinish: () => deleteForm.reset("current_password"),
    });
  }

  return (
    <AccountPage
      breadcrumbs={breadcrumbs}
      title={t("account.security.title")}
      description={t("account.security.intro")}
    >
      <div className="space-y-20">
        <div role="status">
          {flash.accountStatus === "email-changed" && (
            <p className="border-signal text-ink border-l-2 py-1 pl-4 text-sm">
              {t("account.security.emailChanged")}
            </p>
          )}
          {flash.accountStatus === "email-change-failed" && (
            <p className="border-signal text-ink border-l-2 py-1 pl-4 text-sm">
              {t("account.security.emailChangeFailed")}
            </p>
          )}
        </div>

        <section aria-labelledby="email-heading" className="space-y-7">
          <h2 id="email-heading" className="display text-ink text-2xl">
            {t("account.security.emailSection")}
          </h2>
          <p className="text-ink-soft">
            {t("account.security.currentEmail", {
              email: account.customer?.email ?? "",
            })}
          </p>

          <div role="status">
            {flash.accountStatus === "email-change-requested" && (
              <p className="border-signal text-ink border-l-2 py-1 pl-4 text-sm">
                {t("account.security.emailRequested")}
              </p>
            )}
          </div>
          {pendingEmail && (
            <p className="text-ink border-l-2 border-current py-1 pl-4 text-sm">
              {t("account.security.pending", { email: pendingEmail })}
            </p>
          )}

          <div role="alert">
            {emailErrors.page_expired && (
              <p className="text-destructive text-sm">
                {t("auth.pageExpired")}
              </p>
            )}
          </div>

          <form
            className="space-y-7"
            onSubmit={submitEmail}
            aria-busy={emailForm.processing}
            noValidate
          >
            <div className="space-y-2">
              <Field
                id="email-email"
                label={t("account.security.newEmail")}
                error={emailForm.errors.email}
                required
              >
                {(control) => (
                  <Input
                    {...control}
                    type="email"
                    value={emailForm.data.email}
                    onChange={(event) =>
                      emailForm.setData("email", event.target.value)
                    }
                    autoComplete="email"
                  />
                )}
              </Field>
              <p className="text-ink-soft text-sm">
                {t("account.security.emailHint")}
              </p>
            </div>

            <Field
              id="email-current_password"
              label={t("account.security.currentPassword")}
              error={emailForm.errors.current_password}
              required
            >
              {(control) => (
                <Input
                  {...control}
                  type="password"
                  value={emailForm.data.current_password}
                  onChange={(event) =>
                    emailForm.setData("current_password", event.target.value)
                  }
                  autoComplete="current-password"
                />
              )}
            </Field>

            <Button type="submit" disabled={emailForm.processing}>
              {emailForm.processing
                ? t("account.security.sending")
                : t("account.security.sendLink")}
            </Button>
          </form>
        </section>

        <section aria-labelledby="password-heading" className="space-y-7">
          <h2 id="password-heading" className="display text-ink text-2xl">
            {t("account.security.passwordSection")}
          </h2>
          <p className="border-signal text-ink border-l-2 py-1 pl-4 text-sm">
            {t("account.security.passwordNotice")}
          </p>

          <div role="alert">
            {passwordErrors.page_expired && (
              <p className="text-destructive text-sm">
                {t("auth.pageExpired")}
              </p>
            )}
          </div>

          <form
            className="space-y-7"
            onSubmit={submitPassword}
            aria-busy={passwordForm.processing}
            noValidate
          >
            <Field
              id="password-current_password"
              label={t("account.security.currentPassword")}
              error={passwordForm.errors.current_password}
              required
            >
              {(control) => (
                <Input
                  {...control}
                  type="password"
                  value={passwordForm.data.current_password}
                  onChange={(event) =>
                    passwordForm.setData("current_password", event.target.value)
                  }
                  autoComplete="current-password"
                />
              )}
            </Field>

            <div className="space-y-2">
              <Field
                id="password-password"
                label={t("account.security.newPassword")}
                error={passwordForm.errors.password}
                required
              >
                {(control) => (
                  <Input
                    {...control}
                    type="password"
                    value={passwordForm.data.password}
                    onChange={(event) =>
                      passwordForm.setData("password", event.target.value)
                    }
                    autoComplete="new-password"
                  />
                )}
              </Field>
              <p className="text-ink-soft text-sm">
                {t("account.security.passwordHint", {
                  count: PASSWORD_MIN_LENGTH,
                })}
              </p>
            </div>

            <Field
              id="password-password_confirmation"
              label={t("account.security.newPasswordConfirmation")}
              error={passwordForm.errors.password_confirmation}
              required
            >
              {(control) => (
                <Input
                  {...control}
                  type="password"
                  value={passwordForm.data.password_confirmation}
                  onChange={(event) =>
                    passwordForm.setData(
                      "password_confirmation",
                      event.target.value,
                    )
                  }
                  autoComplete="new-password"
                />
              )}
            </Field>

            <div className="flex flex-wrap items-center gap-x-6 gap-y-3">
              <Button type="submit" disabled={passwordForm.processing}>
                {passwordForm.processing
                  ? t("account.security.saving")
                  : t("account.security.changePassword")}
              </Button>
              <p className="meta text-ink-soft">{t("auth.requiredHint")}</p>
            </div>
          </form>
        </section>

        <section aria-labelledby="delete-heading" className="space-y-7">
          <h2 id="delete-heading" className="display text-ink text-2xl">
            {t("account.delete.title")}
          </h2>
          <p className="text-ink-soft max-w-[60ch]">
            {t("account.delete.text", { years: retentionYears })}
          </p>

          <div role="alert">
            {deleteErrors.page_expired && (
              <p className="text-destructive text-sm">
                {t("auth.pageExpired")}
              </p>
            )}
          </div>

          <form
            className="space-y-7"
            onSubmit={submitDelete}
            aria-busy={deleteForm.processing}
            noValidate
          >
            <Field
              id="delete-current_password"
              label={t("account.security.currentPassword")}
              error={deleteForm.errors.current_password}
              required
            >
              {(control) => (
                <Input
                  {...control}
                  type="password"
                  value={deleteForm.data.current_password}
                  onChange={(event) =>
                    deleteForm.setData("current_password", event.target.value)
                  }
                  autoComplete="current-password"
                />
              )}
            </Field>

            <div className="space-y-2">
              <div className="flex items-start gap-3">
                <input
                  id="delete-confirm"
                  type="checkbox"
                  checked={deleteForm.data.confirm}
                  onChange={(event) =>
                    deleteForm.setData("confirm", event.target.checked)
                  }
                  aria-invalid={deleteForm.errors.confirm ? true : undefined}
                  aria-describedby={
                    deleteForm.errors.confirm
                      ? "delete-confirm-error"
                      : undefined
                  }
                  className="accent-ink size-5 shrink-0"
                />
                <label htmlFor="delete-confirm" className="text-ink text-sm">
                  {t("account.delete.confirm")}
                </label>
              </div>
              {deleteForm.errors.confirm && (
                <p
                  id="delete-confirm-error"
                  className="text-destructive text-sm"
                >
                  {deleteForm.errors.confirm}
                </p>
              )}
            </div>

            <Button
              type="submit"
              variant="outline"
              className="border-destructive text-destructive hover:bg-destructive hover:border-destructive hover:text-white"
              disabled={deleteForm.processing}
            >
              {deleteForm.processing
                ? t("account.delete.deleting")
                : t("account.delete.submit")}
            </Button>
          </form>
        </section>
      </div>
    </AccountPage>
  );
}
