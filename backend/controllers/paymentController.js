import { db } from '../config/firebaseAdmin.js'
import { generateMembershipId } from '../utils/generateMembershipId.js'
import { sendEmail, membershipReceiptEmail } from '../services/emailService.js'
import { createStripeCheckoutSession } from '../services/payments/stripeService.js'
import { createPayPalOrder, capturePayPalOrder } from '../services/payments/paypalService.js'
import { createMobileMoneyCheckout, verifyWebhookChecksum } from '../services/payments/clickpesaService.js'

// Prices for tiers (TZS). Values are per-period.
const TIER_PRICES = {
  royalfamilymember: { monthly: 2000, yearly: 12000 },
  supporter: { monthly: 5000, yearly: 50000 },
  patron: { monthly: 10000, yearly: 50000 },
}

// Normalizes local Tanzanian phone formats (07XXXXXXXX, +255..., 255...)
// into the "255XXXXXXXXX" shape ClickPesa expects (country code, no plus).
function normalizeTzPhone(phone) {
  const digits = String(phone).replace(/\D/g, '')
  if (digits.startsWith('255')) return digits
  if (digits.startsWith('0')) return `255${digits.slice(1)}`
  return `255${digits}`
}

// POST /api/payments/subscribe — starts a membership payment.
export async function startSubscription(req, res) {
  const { method, phone, tier = 'royalfamilymember', period = 'monthly' } = req.body
  const amount = (TIER_PRICES[tier] && TIER_PRICES[tier][period]) || (TIER_PRICES.royalfamilymember.monthly)
  const uid = req.user.uid

  const txnRef = await db.collection('transactions').add({
    uid, userEmail: req.user.email, type: 'subscription', tier, period,
    amount, currency: 'TZS', method, status: 'pending',
    createdAt: new Date().toISOString(),
  })

  try {
    if (method === 'mobile') {
      const result = await createMobileMoneyCheckout({
        amount, phoneNumber: normalizeTzPhone(phone), orderReference: txnRef.id,
      })
      await txnRef.update({ providerRef: result.id, channel: result.channel })
      return res.json({ ussdInstructions: 'Check your phone to approve the mobile money payment.', raw: result })
    }
    if (method === 'paypal') {
      const { orderId, redirectUrl } = await createPayPalOrder({
        amount: amount / 2600, // rough TZS->USD; replace with a real FX rate source
        currency: 'USD',
        returnUrl: `${process.env.CLIENT_URL}/subscribe?paypalOrder=${txnRef.id}`,
        cancelUrl: `${process.env.CLIENT_URL}/subscribe`,
      })
      await txnRef.update({ providerRef: orderId })
      return res.json({ redirectUrl })
    }
    if (method === 'card') {
      const url = await createStripeCheckoutSession({
        amount: amount / 2600,
        currency: 'usd',
        successUrl: `${process.env.CLIENT_URL}/dashboard?paid=1`,
        cancelUrl: `${process.env.CLIENT_URL}/subscribe`,
        metadata: { txnId: txnRef.id, uid, description: 'Royal Family TZ membership' },
      })
      return res.json({ redirectUrl: url })
    }
    return res.status(400).json({ error: 'Unknown payment method' })
  } catch (err) {
    console.error(err)
    await txnRef.update({ status: 'failed' })
    res.status(500).json({ error: 'Could not start payment' })
  }
}

// Shared logic once any provider confirms a subscription payment succeeded.
export async function activateMembership(txnId) {
  const txnRef = db.collection('transactions').doc(txnId)
  const txn = (await txnRef.get()).data()
  if (!txn || txn.status === 'succeeded') return

  const membershipId = await generateMembershipId()
  await db.collection('users').doc(txn.uid).update({ membershipActive: true, membershipId })
  await txnRef.update({ status: 'succeeded' })

  const { subject, html } = membershipReceiptEmail({ membershipId, amount: txn.amount, currency: txn.currency, tier: txn.tier })
  await sendEmail({ to: txn.userEmail, subject, html })
}

// Called by the frontend after PayPal redirects back with an approved order.
export async function capturePaypal(req, res) {
  const { txnId, orderId } = req.body
  await capturePayPalOrder(orderId)
  await activateMembership(txnId)
  res.json({ ok: true })
}

// POST /api/payments/webhooks/stripe — Stripe calls this directly.
export async function stripeWebhook(req, res) {
  // Signature verification happens in the route handler (needs the raw body).
  const session = req.stripeEvent.data.object
  if (req.stripeEvent.type === 'checkout.session.completed') {
    await activateMembership(session.metadata.txnId)
  }
  res.json({ received: true })
}

// POST /api/payments/webhooks/clickpesa — fires for both USSD-PUSH mobile
// money and card payments. See https://docs.clickpesa.com/home/webhooks
export async function clickpesaWebhook(req, res) {
  if (!verifyWebhookChecksum(req.body)) {
    return res.status(401).json({ error: 'Invalid checksum' })
  }

  const { event, data } = req.body
  const txnId = data.orderReference
  const txnRef = db.collection('transactions').doc(txnId)
  const txn = (await txnRef.get()).data()
  if (!txn) return res.json({ received: true }) // unknown reference, nothing to do

  if (event === 'PAYMENT RECEIVED') {
    if (txn.type === 'subscription') await activateMembership(txnId)
    else await txnRef.update({ status: 'succeeded' })
  } else if (event === 'PAYMENT FAILED') {
    await txnRef.update({ status: 'failed', failureMessage: data.message })
  }

  res.json({ received: true })
}

// POST /api/payments/donate — one-off donation, no membership tie-in.
export async function donate(req, res) {
  const { amount, method, currency = 'TZS' } = req.body
  const txnRef = await db.collection('transactions').add({
    type: 'donation', amount, currency, method, status: 'pending',
    createdAt: new Date().toISOString(),
  })
  if (method === 'card') {
    const url = await createStripeCheckoutSession({
      amount: currency === 'TZS' ? amount / 2600 : amount,
      currency: 'usd',
      successUrl: `${process.env.CLIENT_URL}/donate?thanks=1`,
      cancelUrl: `${process.env.CLIENT_URL}/donate`,
      metadata: { txnId: txnRef.id, description: 'Royal Family TZ donation' },
    })
    return res.json({ redirectUrl: url })
  }
  if (method === 'paypal') {
    const { redirectUrl } = await createPayPalOrder({
      amount: currency === 'TZS' ? amount / 2600 : amount, currency: 'USD',
      returnUrl: `${process.env.CLIENT_URL}/donate?thanks=1`, cancelUrl: `${process.env.CLIENT_URL}/donate`,
    })
    return res.json({ redirectUrl })
  }
  const result = await createMobileMoneyCheckout({
    amount, phoneNumber: normalizeTzPhone(req.body.phone), orderReference: txnRef.id,
  })
  res.json({ ussdInstructions: 'Approve the payment on your phone.', raw: result })
}
