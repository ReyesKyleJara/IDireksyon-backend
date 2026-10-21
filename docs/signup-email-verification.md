# Email verification during signup

Email signup requires a six-digit code delivered to the address entered. The
account is created only after the code is verified. Phone signup is unchanged.
This confirms access to the mailbox; formatting checks alone cannot establish
that an address belongs to the person signing up. Existing accounts are not
retroactively blocked, and changing an account email still clears its existing
verification timestamp.

## Gmail delivery

1. Enable 2-Step Verification on the Google account that will send mail.
2. Create a Google app password at https://myaccount.google.com/apppasswords.
   Some account policies do not allow app passwords; see
   [Google's app password help](https://support.google.com/accounts/answer/185833).
3. Copy the settings from `.env.gmail.example` into your private backend `.env`.
   Set `MAIL_USERNAME` and `MAIL_FROM_ADDRESS` to your Gmail address and
   `MAIL_PASSWORD` to the app password without display spaces. Do not use your
   regular Gmail login password. If `.env` already has `MAIL_URL`, remove it or
   set it to `null` so it does not override these settings.
4. Run `php artisan config:clear` and restart the backend. Restart Flutter to
   load the updated signup flow.

The template uses `smtp.gmail.com`, port 465, and implicit TLS (`smtps`), as
documented in [Google's SMTP guide](https://developers.google.com/workspace/gmail/imap/imap-smtp).
Live inbox delivery requires valid credentials and is not covered by mocked
tests. Codes may arrive in spam. Provider acceptance is not a guarantee of
delivery. Never put SMTP credentials in Flutter or commit your private `.env`.

For development without credentials, keep `APP_ENV=local` and `MAIL_MAILER=log`.
The full message and code appear in `storage/logs/laravel.log`; no email is sent.
Logging/array mailers are refused outside local/testing environments. Avoid
production mail transports with a silent fallback to logging.

## Database and API

Apply the additive migration without resetting the database:

```sh
php artisan migrate --path=database/migrations/2026_10_21_010000_create_signup_email_verifications_table.php
```

1. `POST /api/auth/email-verification/send` with `email` returns `challenge_id`,
   `expires_in: 600`, and `resend_after: 60`.
2. `POST /api/auth/email-verification/verify` with `challenge_id` and six-digit
   string `code` returns an opaque `email_verification_token`.
3. Include that token with the existing email registration fields in
   `POST /api/auth/register`. Proof is bound to the normalized email, consumed
   atomically with account creation, and sets `email_verified_at`.

Codes are stored as hashes, expire after ten minutes, and lock after five
incorrect guesses. Proof expires at the original deadline and is single-use.
Resends have a sixty-second destination cooldown, plus an IP send limit of five
per minute. Verification attempts have an IP limit of twenty per minute.
Resends create independent challenges; older challenges retain their original
deadline. Expired records are cleaned up on subsequent sends. No code is
included in API responses or Flutter logs.
