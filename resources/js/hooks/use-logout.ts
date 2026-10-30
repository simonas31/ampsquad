import { useForm, usePage } from "@inertiajs/react";
import { useRef } from "react";

/**
 * Signing the customer out, as a request the page makes itself rather than a
 * link that is followed: while it runs `processing` is true, so the button
 * can say it is working and cannot be pressed a second time. `onFinish` runs
 * when the request is over, whichever way it went.
 *
 * A page left open long enough carries a token the server no longer accepts.
 * The server answers that with "page expired" and a fresh token, which used
 * to mean pressing the button again. Signing out can safely be asked twice,
 * so the hook asks again once, by itself, when the first try ends that way.
 */
export function useLogout(onFinish?: () => void) {
  const { account } = usePage().props;
  const form = useForm({});
  const expired = useRef(false);
  const retried = useRef(false);

  function logout() {
    if (form.processing) {
      return;
    }

    retried.current = false;
    send();
  }

  function send() {
    expired.current = false;

    form.post(account.logoutUrl, {
      onError: (errors) => {
        expired.current = Boolean(errors.page_expired);
      },
      onFinish: () => {
        if (expired.current && !retried.current) {
          retried.current = true;
          send();

          return;
        }

        onFinish?.();
      },
    });
  }

  return { logout, processing: form.processing };
}
