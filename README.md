# Provably Fair Validator

Independently verify that your skin.club rolls were provably fair. Paste the roll
data from the site and the app recalculates the result for you — no trust required.

[![Run on Replit](https://replit.com/badge/github/skinclub-tech/provably-fair-validator)](https://replit.com/github/skinclub-tech/provably-fair-validator)

## How to use

1. Open the **"Check Roll"** (Fairness) page on skin.club for the drop you want to verify.
2. Copy the JSON block shown there — including the `{ }`.
3. Paste it into the input box and click **Check**.
4. Green means the roll and public hash match — it's provably fair.

## Run it yourself

Click the **Run on Replit** button above, or run locally with PHP:

```bash
php -S 0.0.0.0:8080 -t .
```

Then open the page in your browser.

## Sample data

**Regular roll**

Mock values in the same format as the site's data.

```json
{
  "client_seed": "my_seed",
  "server_seed": "0d2ade1251ce04077d6020a5fb081c36",
  "secret_salt": "b07bbd68cf0ba687",
  "public_hash": "924ef42387a290388c5258d00660a7885cb27e3a7bd15bacee2f0242d9615b77",
  "nonce": 7,
  "roll": 10477,
  "created_at": "2026-01-01 12:00:00"
}
```

**Battle roll** (`client_seed` lists the client seeds of all participants)

```json
{
  "type": "battle",
  "beacon": "11TD1na4qZZonViCx9NbqbQ1cyiVtQ51",
  "client_seed": "my_seed,player_2,player_3,player_4",
  "nonce": 3,
  "roll": 81575
}
```

Data copied from the roll details without commas between fields (one
`"key": "value"` pair per line) is accepted too.

## Source

[github.com/skinclub-tech/provably-fair-validator](https://github.com/skinclub-tech/provably-fair-validator)
