// Handles Visa/Mastercard payments via Stripe Checkout.
// Sign up at https://dashboard.stripe.com, grab the secret key, and set
// STRIPE_SECRET_KEY + STRIPE_WEBHOOK_SECRET in .env.
import Stripe from 'stripe'
import 'dotenv/config'

const stripe = new Stripe(process.env.STRIPE_SECRET_KEY || 'sk_test_placeholder')

export async function createStripeCheckoutSession({ amount, currency, successUrl, cancelUrl, metadata }) {
  const session = await stripe.checkout.sessions.create({
    mode: 'payment',
    payment_method_types: ['card'],
    line_items: [{
      price_data: {
        currency: currency.toLowerCase(),
        product_data: { name: metadata.description || 'Royal Family TZ payment' },
        unit_amount: Math.round(amount * 100), // Stripe uses the smallest currency unit
      },
      quantity: 1,
    }],
    success_url: successUrl,
    cancel_url: cancelUrl,
    metadata,
  })
  return session.url
}

export function constructStripeEvent(rawBody, signature) {
  return stripe.webhooks.constructEvent(rawBody, signature, process.env.STRIPE_WEBHOOK_SECRET)
}
