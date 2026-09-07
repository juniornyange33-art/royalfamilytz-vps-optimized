// ClickPesa integration — Tanzanian mobile money (M-Pesa, Tigo Pesa, Airtel
// Money, HaloPesa) via USSD-PUSH, plus card payments as a hosted link.
//
// Setup: https://merchant.clickpesa.com -> Settings -> Developers ->
// Create Application (Integration Type: API) -> Manage API Keys.
// That gives you CLICKPESA_CLIENT_ID and CLICKPESA_API_KEY.
//
// Docs: https://docs.clickpesa.com
import axios from 'axios'
import crypto from 'crypto'
import 'dotenv/config'

const BASE = process.env.CLICKPESA_BASE_URL || 'https://api.clickpesa.com/third-parties'

// --- Auth -------------------------------------------------------------
// JWT tokens are valid for 1 hour; cache in memory and refresh a little early.
let cachedToken = null
let tokenExpiresAt = 0

async function getToken() {
  if (cachedToken && Date.now() < tokenExpiresAt) return cachedToken

  const res = await axios.post(`${BASE}/generate-token`, {}, {
    headers: {
      'client-id': process.env.CLICKPESA_CLIENT_ID,
      'api-key': process.env.CLICKPESA_API_KEY,
    },
  })

  // Response token field already includes the "Bearer " prefix.
  cachedToken = res.data.token
  tokenExpiresAt = Date.now() + 55 * 60 * 1000 // refresh 5 min early
  return cachedToken
}

async function authHeaders() {
  return { Authorization: await getToken(), 'Content-Type': 'application/json' }
}

// --- Checksum (optional — only needed if checksum is enabled on your
// ClickPesa application). Signs/validates payloads with HMAC-SHA256 over
// the alphabetically-sorted, compact-JSON payload. ---------------------
function canonicalize(obj) {
  if (obj === null || typeof obj !== 'object') return obj
  if (Array.isArray(obj)) return obj.map(canonicalize)
  return Object.keys(obj).sort().reduce((acc, key) => {
    acc[key] = canonicalize(obj[key])
    return acc
  }, {})
}

export function createChecksum(payload) {
  const json = JSON.stringify(canonicalize(payload))
  return crypto.createHmac('sha256', process.env.CLICKPESA_CHECKSUM_KEY).update(json).digest('hex')
}

export function verifyWebhookChecksum(payload) {
  if (!process.env.CLICKPESA_CHECKSUM_KEY) return true // checksum not enabled
  const { checksum, checksumMethod, ...rest } = payload
  if (!checksum) return false
  return createChecksum(rest) === checksum
}

// --- Mobile money (USSD-PUSH) ------------------------------------------
// Step 1: preview — confirms the phone number's network and that a fee
// quote is available before we prompt the customer.
export async function previewMobileMoneyPush({ amount, orderReference, phoneNumber }) {
  const headers = await authHeaders()
  const res = await axios.post(`${BASE}/payments/preview-ussd-push-request`, {
    amount: String(amount), currency: 'TZS', orderReference, phoneNumber,
  }, { headers })
  return res.data
}

// Step 2: initiate — sends the actual USSD prompt to the customer's phone.
export async function initiateMobileMoneyPush({ amount, orderReference, phoneNumber }) {
  const headers = await authHeaders()
  const res = await axios.post(`${BASE}/payments/initiate-ussd-push-request`, {
    amount: String(amount), currency: 'TZS', orderReference, phoneNumber,
  }, { headers })
  return res.data // { id, status: 'PROCESSING', channel, orderReference, ... }
}

// Convenience wrapper used by the payment controller: preview then initiate.
export async function createMobileMoneyCheckout({ amount, phoneNumber, orderReference }) {
  await previewMobileMoneyPush({ amount, orderReference, phoneNumber })
  return initiateMobileMoneyPush({ amount, orderReference, phoneNumber })
}

// --- Card payments (Visa/Mastercard/Amex/UnionPay via hosted widget) ---
// Note: ClickPesa's card API settles in USD, not TZS.
export async function initiateCardPayment({ amount, orderReference, fullName, email, phoneNumber }) {
  const headers = await authHeaders()
  const res = await axios.post(`${BASE}/payments/initiate-card-payment`, {
    amount: String(amount), currency: 'USD', orderReference,
    customer: { fullName, email, phoneNumber },
  }, { headers })
  return res.data // { cardPaymentLink }
}

// --- Status ---------------------------------------------------------------
export async function queryPaymentStatus(orderReference) {
  const headers = await authHeaders()
  const res = await axios.get(`${BASE}/payments/${orderReference}`, { headers })
  return res.data // array of matching payment records
}
