# First Login

After installation, PNLCS creates a single administrator account so you can get
in. Your very first job is to sign in and secure it.

## 1. Open the admin login

Go to:

```
https://your-domain/admin/login
```

Replace `your-domain` with wherever you installed PNLCS (for a Docker install
this is usually `http://localhost:8090/admin/login`).

## 2. Sign in with the default credentials

If you installed with the **in-app install wizard**, you chose your own
username and password during setup — use those.

If you installed **manually** (`php artisan db:seed`), the default account is:

| Field | Value |
|-------|-------|
| Username | `admin` |
| Password | `admin123` |

!!! danger "Change this immediately"
    The default `admin` / `admin123` password is public knowledge. Anyone who
    finds your panel can try it. Change it before you do anything else.

## 3. Change your password

1. Click your name in the top-right corner → **My Account**.
2. Open **Change Password**.
3. Set a long, unique password and save.

## 4. Turn on two-factor authentication (recommended)

Still under **My Account**, enable **Two-Factor Authentication (2FA)**:

1. Scan the QR code with an authenticator app (Google Authenticator, Authy, 1Password, etc.).
2. Enter the 6-digit code to confirm.
3. Save your recovery codes somewhere safe.

From now on you'll enter a code from your phone at each login. This protects
your billing system even if your password leaks.

## What's next

Your account is secure. Now configure the essentials so the panel can send
email, take payments and provision hosting:

➡️ [Setup Checklist](setup-checklist.md)
