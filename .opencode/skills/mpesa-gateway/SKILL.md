---
name: mpesa-gateway
description: Use when working on the M-Pesa WooCommerce payment gateway plugin in this repo (STK Push / Daraja API, WC_Payment_Gateway class, checkout, callbacks).
---

# M-Pesa Gateway

Context for building and editing the WooCommerce M-Pesa payment plugin in this repo.

## Ground rules

- Keep code minimal: no excessive Boilerplate, no comments unless asked.
- Gateway must extend `WC_Payment_Gateway` and be registered via the `woocommerce_payment_gateways` filter.
- Order flow: initiate STK push in `process_payment()`, confirm via the `wc-api` callback, then `$order->payment_complete()`.
- Follow the verified official docs:
  - WooCommerce: https://developer.woocommerce.com/docs/features/payments/payment-gateway-api
  - Daraja (Safaricom): https://developer.safaricom.co.ke (M-Pesa Express / STK Push)

## Verify before finishing

1. `php -l` on every changed PHP file.
2. Confirm Daraja endpoints/base URLs and STK request params against the official docs.
3. Ask before committing to git.

## Sandbox vs production

- Sandbox base URL `https://sandbox.safaricom.co.ke`, production `https://api.safaricom.co.ke`.
- Test phone for sandbox simulator: 254708374149.
- `ResponseCode "0"` means the request was accepted, not that payment completed.