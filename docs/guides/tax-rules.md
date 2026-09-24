# Tax Rules

If you charge VAT, GST or sales tax, set the rates under **Setup → Tax
Rules**.

## Add rates

Rates are grouped by **country** and, optionally, **state or province**. For
each group you add one or more named rates (for example "VAT 20%") and mark
one as the group's default.

1. **Setup → Tax Rules**
2. Choose the country (and state, if the rate is regional).
3. Add the rate: a name and a percentage.
4. Save.

A group with an **empty country** is the global default: it applies to every
customer no other rule matches.

## Which rate a customer pays

For each invoice PNLCS looks at the customer's country and state, and uses the
first of these that exists:

1. the rate for that **country and state**;
2. the rate for the **country** (no state);
3. the **global default**.

No match, no tax. The tax is added on top of the price and shown on the
invoice as its own line.

## Customers who pay no tax

Mark a customer **tax exempt** on their profile (**Clients → the client**) and
their invoices carry no tax, whatever the rules say.

## Good to know

- Prices are entered **before tax**: the rate is added to them. There is no
  setting to enter prices with tax already included.
- Every product is taxable; there is no per-product switch on the product
  screen yet.

!!! warning "Tax is your responsibility"
    PNLCS applies the rates you configure; it is not tax advice. EU VAT, B2B
    reverse charge and US sales-tax nexus depend on the jurisdiction. Confirm
    your obligations with an accountant.
