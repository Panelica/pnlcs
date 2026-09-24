# First Login

## 1. Open the admin login

```
https://example.com/admin/login
```

Use your own address in place of `example.com`. With the Docker quick start it
is `http://localhost:8090/admin/login`.

## 2. Sign in

Use the username and password **you chose in the install wizard**. There is
no default password to change.

!!! danger "Only if you seeded the database by hand"
    A headless install that ran `php artisan db:seed` instead of the wizard
    starts with the account `admin` / `admin123`. That password is public
    knowledge: change it before anything else (step 3).

## 3. Your account

Open **My Account** from the menu under your name. It has three parts:

- **Profile information**: your name, email and language.
- **Change password**.
- **Two-factor authentication**: turn it on now (step 4).

## 4. Turn on two-factor authentication

1. Under **My Account → Two-factor authentication**, choose to turn it on.
2. Scan the QR code with an authenticator app (Google Authenticator, Authy,
   1Password and the like), or type the secret shown under it.
3. Enter the 6-digit code the app shows.
4. **Write down the recovery codes** the next page shows. Each one signs you in
   once in place of the phone; keep them somewhere other than the phone.

From now on you enter a code from the app at every sign-in. It protects your
billing system even if your password leaks.

## What's next

➡️ [Setup Checklist](setup-checklist.md): email, a payment gateway, a server
and your first product.
