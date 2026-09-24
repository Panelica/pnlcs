# Live Servers

**Live Servers** lists your Panelica servers and signs you into any of their
control panels with one click, without typing a password.

## Where

On the admin dashboard, under the quick actions: **Live Servers**. It is shown
to staff whose role has the *manage servers* permission.

## What it shows

Every server of type **Panelica** from **Setup → Servers**: its name,
hostname, IP address and whether it is active, with a **log in** button. Other panel types are not listed: the one-click
sign-in uses Panelica's own single-use login link, which the other panels do
not offer.

## How the sign-in works

1. PNLCS asks the server, with the API key stored for it, for a single-use
   sign-in link for the panel account the key belongs to.
2. Your browser opens that link, and you are in the panel.

Every sign-in, and every failed attempt, is written to the **Activity Log**
with your name and the server.

!!! note
    The panel account you land in is the one the server's API key belongs to.
    If the button fails, check the key with **Test** on **Setup → Servers**.
