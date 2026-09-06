// PayPal Checkout via the REST Orders API.
// Create an app at https://developer.paypal.com/dashboard to get client
// ID/secret. Use PAYPAL_MODE=sandbox until you're ready to go live.
import axios from 'axios'
import 'dotenv/config'

const BASE = process.env.PAYPAL_MODE === 'live'
  ? 'https://api-m.paypal.com'
  : 'https://api-m.sandbox.paypal.com'

async function getAccessToken() {
  const auth = Buffer.from(`${process.env.PAYPAL_CLIENT_ID}:${process.env.PAYPAL_CLIENT_SECRET}`).toString('base64')
  const res = await axios.post(`${BASE}/v1/oauth2/token`, 'grant_type=client_credentials', {
    headers: { Authorization: `Basic ${auth}`, 'Content-Type': 'application/x-www-form-urlencoded' },
  })
  return res.data.access_token
}

export async function createPayPalOrder({ amount, currency, returnUrl, cancelUrl }) {
  const token = await getAccessToken()
  const res = await axios.post(`${BASE}/v2/checkout/orders`, {
    intent: 'CAPTURE',
    purchase_units: [{ amount: { currency_code: currency, value: amount.toFixed(2) } }],
    application_context: { return_url: returnUrl, cancel_url: cancelUrl },
  }, { headers: { Authorization: `Bearer ${token}` } })

  const approveLink = res.data.links.find((l) => l.rel === 'approve')
  return { orderId: res.data.id, redirectUrl: approveLink.href }
}

export async function capturePayPalOrder(orderId) {
  const token = await getAccessToken()
  const res = await axios.post(`${BASE}/v2/checkout/orders/${orderId}/capture`, {}, {
    headers: { Authorization: `Bearer ${token}` },
  })
  return res.data
}
