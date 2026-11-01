# Official Exchange Rates

When you bill in a second currency (**Setup → General Settings → Invoicing →
Billing currency**), every invoice freezes the rate it was raised at. With an
**official rate source** chosen, that rate comes from a named publisher instead
of the commercial rate feed, and the invoice prints where it came from: the
publisher, the kind of rate, the publisher's own date and, where there is one,
the bulletin number. An auditor can then check the number against the source.

PNLCS ships one official source, **TCMB** (Central Bank of the Republic of
Turkey), for lira invoices from a US dollar shop. You can add others.

## Adding a provider

Fill in **Official rate providers (JSON)** with a list of entries, save, then
choose the one you want under **Official rate source**. An entry that is in the
list but not chosen is never contacted. Only one official source is active at a
time, and it sets the rate of one currency.

```json
[
  {
    "key": "cbuae",
    "name": "Central Bank of the UAE",
    "url": "https://example.org/rates/usd-aed.json",
    "base": "USD",
    "currency": "AED",
    "rate_path": "rate",
    "date_path": "rate_date",
    "stale_path": "stale",
    "quote": "base",
    "kind": "reference",
    "on_stale": "refuse"
  }
]
```

| Field | Required | Meaning |
|---|---|---|
| `key` | yes | Your name for the entry: lowercase letters, digits, `-` or `_`, up to 32. Not `tcmb`. |
| `name` | yes | The source printed on the invoice. Name the publisher, not a website. |
| `url` | yes | An `https` URL that answers with JSON. |
| `base` | yes | The currency the rate is against. Must be your shop's default currency, or the entry is skipped. |
| `currency` | yes | The currency whose rate it sets (normally your billing currency). |
| `rate_path` | yes | Dot path to the number in the answer: `rate`, `data.rates.AED`, `0.value`. |
| `date_path` | yes | Dot path to the publisher's date for that number. Printed as given. |
| `quote` | yes | `base`: 1 base = *rate* currency. `currency`: 1 currency = *rate* base (PNLCS stores the inverse). |
| `kind` | no | `reference` (default), `ForexSelling` or `ForexBuying`; printed as the kind of rate. |
| `stale_path` | no | Dot path to a `true`/`false` flag the publisher sets when its table is behind. |
| `max_age_days` | no | Treat the answer as stale when its date is more than this many days old (0-60). |
| `on_stale` | no | `refuse` (default): keep the commercial rate and print no source. `use`: store it anyway. |
| `ref_path` | no | Dot path to a bulletin or table number, printed after the date. |
| `auth` | no | `true` sends **Official rate API key** as a bearer token. Default `false`. |

The list is checked when you save; an entry with a missing or malformed field
is rejected with a message saying which. Quote direction is never guessed: a
table quoted the other way round needs `"quote": "currency"`.

## What happens on each run

`pnlcs:currency-update` first fetches the commercial rates for every currency,
then asks the chosen provider for its currency:

- **Fresh answer**: the rate replaces the commercial one, and invoices in that
  currency print the publisher, kind, date and reference.
- **Stale and `on_stale` is `refuse`**, **unreachable**, **no rate or no date
  in the answer**, **base is not the shop currency**, or **the address is not a
  public one**: the commercial rate stays, and invoices print no official
  source until the next good run.

A publisher has to be reachable on the public internet. The address is checked
against DNS before it is requested and the connection goes to the address that
was checked, so an entry naming this server, its own network or a cloud
metadata service is refused rather than fetched. The key under **Official rate
API key**, if the publisher needs one, is stored encrypted like the panel's
other credentials.

!!! note "Add Funds invoices are priced when they are paid"
    An ordinary invoice keeps the rate it was raised at — it is a debt for
    something already sold. An *Add Funds* invoice sells the shop currency
    itself and stays payable until somebody pays it, so an unpaid one takes
    today's rate when it is opened or paid, and its line says which rate was
    applied. Once any money has arrived on it, the rate stands.

## Example: AllRatesToday central-bank tables

[AllRatesToday](https://allratestoday.com) publishes the latest table of about
120 central banks and tax authorities, fetched from each publisher on its own
schedule. Each answer carries the publisher's `rate_date`, a `stale` flag,
`age_days` and the `rate_type`, and quotes the table the publisher's way round.
The list of institutions and their codes is at
`https://allratestoday.com/api/open/central-banks`.

The latest table is keyless for evaluation; commercial use, which includes
issuing invoices, needs an API key (Official Rates plan). Put the key in
**Official rate API key** and set `"auth": true`:

```json
[
  {
    "key": "cbuae-art",
    "name": "Central Bank of the UAE (via AllRatesToday)",
    "url": "https://allratestoday.com/api/v1/central-bank/cbuae/latest?source=USD&target=AED",
    "base": "USD",
    "currency": "AED",
    "rate_path": "rate",
    "date_path": "rate_date",
    "stale_path": "stale",
    "quote": "base",
    "kind": "reference",
    "on_stale": "refuse",
    "auth": true
  }
]
```

For evaluation without a key, use
`https://allratestoday.com/api/open/central-bank/cbuae?source=USD&target=AED`
with `"auth": false`. Check the field names against one live answer before
relying on an entry.
