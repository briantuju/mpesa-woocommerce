# M-Pesa Payment Gateway for WooCommerce

Accept M-Pesa payments via Safaricom Daraja STK Push (Lipa Na M-Pesa Online).

## Requirements

- WordPress 6.x, WooCommerce 8.x+, PHP 8.x
- HTTPS on the checkout **and** callback URLs (Safaricom requires it)
- A Daraja account, and — for live payments — an approved **Go Live** application

## Installation

1. Copy the plugin folder into `wp-content/plugins/` and activate it.
2. WooCommerce → Settings → Payments → M-Pesa.
3. Choose **Environment** (Sandbox or Live), then enter the Consumer Key,
   Consumer Secret, PayBill/Till number, and Lipa Na M-Pesa Online Pass Key.
4. The **Callback URL** is prefilled. It must be publicly reachable over HTTPS.
5. Save, enable the gateway, and confirm the store currency is **KES** (the
   gateway hides itself for any other currency).

### Classic checkout (required)

WooCommerce's block-based checkout ignores all classic gateways, this one
included. Until block support lands, create a page with the `[woocommerce_checkout]`
shortcode and set it as the checkout page (Settings → Pages → Checkout).

## Go-Live Checklist

### 1. Sign up for Daraja and Go Live

1. Register at the [Daraja Portal](https://developer.safaricom.co.ke/)
   (requires Safaricom business credentials / M-Pesa number of the business).
2. Optionally build and test against **Sandbox** credentials with a test till
   (`174379` etc.) until the happy path works.
3. Apply for **Go Live**: this requires the business contract, KRA PIN, and
   business registration documents. Approval takes several working days —
   **start early**; it's the long pole of any launch.

### 2. Have the production "Lipa Na M-Pesa Online" product enabled

The PayBill number you use must have the **Lipa Na M-Pesa Online** product
activated (usually configured when Go Live is approved). STK push will not
work on a plain PayBill.

### 3. Deploy to a real host

- Public domain + TLS (Let's Encrypt is fine). Confirm no mixed-content warnings.
- WordPress **Site Address** (`wp_options`) must match the real URL.
- Run a verify request to your callback URL and confirm a
  `200 OK` against `/wc-api/wc_gateway_mpesa`.
- Keep the WP database and backups private; credentials live in
  `woocommerce_mpesa_settings`.

### 4. Configure the gateway

- Environment = **Live**, production Consumer Key/Secret, PayBill, Pass Key.
- Callback URL = your HTTPS endpoint above.
- **Debug log = off** (leave it on only while troubleshooting; logs to
  WooCommerce → Status → Logs as `wc-mpesa`).

### 5. Make a real payment

- Place an order, approve the push on a phone, confirm:
  - order moves to **Processing** automatically (callback received),
  - an order note records the M-Pesa receipt number,
  - the customer is redirected to the order-received page.
- Then test a **cancelled push** (decline the prompt) → order goes to **Failed**.
- Trigger the cleanup: an abandoned on-hold order should auto-fail after 30
  minutes (filter `mpesa_pending_order_timeout`).

### 6. Monitoring

- Failure path: enable debug logging briefly while reproducing.
- The order **status page**: orders left in *on-hold* past the timeout are
  failed by the hourly cleanup cron.

## Extensibility

The gateway emits two domain events extensions can hook:

- `do_action( 'mpesa_payment_completed', $order, $receipt )`
- `do_action( 'mpesa_payment_failed', $order, $reason )`