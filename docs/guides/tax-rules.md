# Tax Rules

If you need to charge VAT, GST or sales tax, configure it under
**Configuration → Tax**.

## Add a tax rule

1. **Configuration → Tax → Add Rule**
2. Set the **name** (e.g. "VAT", "GST"), the **rate** (e.g. `20` for 20%), and
   the **region** it applies to (country and, optionally, state/province).
3. Save.

You can add several rules — for example a national rate plus regional rates.

## Inclusive vs exclusive

Under **Settings → General**, choose how tax is displayed:

- **Exclusive** — prices are shown pre-tax and tax is added at checkout
  (common in the US and B2B).
- **Inclusive** — prices already include tax (common in the EU B2C).

## Which products are taxed

Tax only applies to products marked **taxable**. Set this per product when you
create or edit it (**Products**). Domains and some fees are often left
non-taxable — check your local rules.

## How tax is applied

At checkout and on each renewal invoice, PNLCS matches the customer's country
(and state, if set) against your tax rules and adds the matching rate to
taxable line items. The invoice shows the tax as a separate line.

!!! warning "Tax is your responsibility"
    PNLCS applies the rates you configure — it is not tax advice. Rules like EU
    VAT MOSS, reverse-charge for B2B, and US nexus vary by jurisdiction.
    Confirm your obligations with an accountant.
