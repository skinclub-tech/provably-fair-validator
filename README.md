# Provably Fair Validator

Independently verify that your skin.club rolls were provably fair. Paste the roll
data from the site and the app recalculates the result for you — no trust required.

[![Run on Replit](https://replit.com/badge/github/skinclub-tech/provably-fair-validator)](https://replit.com/github/skinclub-tech/provably-fair-validator)

## How to use

1. Open the **"Check Roll"** (Fairness) page on skin.club for the drop you want to verify.
2. Copy the JSON block shown there — including the `{ }`.
3. Paste it into the input box and click **Check!**
4. Green means the roll and public hash match — it's provably fair.

## Run it yourself

Click the **Run on Replit** button above, or run locally with PHP:

```bash
php -S 0.0.0.0:8080 -t .
```

Then open the page in your browser.

## Sample data

**Regular roll**

```json
{
  "server_seed": "c4ca4238a0b92382",
  "secret_salt": "0dcc509a6f75849b",
  "public_hash": "dc883b29588c1204fcad00984aaa2404c2251f9a0e5300106eb39aaebcc0f493",
  "client_seed": "my_seed",
  "nonce": "4",
  "roll": "21752"
}
```

**Battle roll**

```json
{
  "type": "battle",
  "beacon": "Tt5qAdTwoTeygDdghVlfEWtNJQkGYg5q",
  "client_seed": "12354,abgd",
  "nonce": "9",
  "roll": "5415"
}
```

## Source

[github.com/skinclub-tech/provably-fair-validator](https://github.com/skinclub-tech/provably-fair-validator)
