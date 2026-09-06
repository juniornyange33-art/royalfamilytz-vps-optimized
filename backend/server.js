import express from 'express'
import cors from 'cors'
import 'dotenv/config'

import paymentRoutes from './routes/paymentRoutes.js'
import adminRoutes from './routes/adminRoutes.js'
import blogRoutes from './routes/blogRoutes.js'
import memberRoutes from './routes/memberRoutes.js'
import contactRoutes from './routes/contactRoutes.js'
import { stripeWebhook, clickpesaWebhook } from './controllers/paymentController.js'
import { constructStripeEvent } from './services/payments/stripeService.js'

const app = express()

app.use(cors({ origin: process.env.CLIENT_URL || 'http://localhost:5173' }))

// Stripe webhook needs the RAW body for signature verification, so it's
// registered before the global express.json() body parser.
app.post('/api/payments/webhooks/stripe', express.raw({ type: 'application/json' }), (req, res, next) => {
  try {
    req.stripeEvent = constructStripeEvent(req.body, req.headers['stripe-signature'])
    next()
  } catch (err) {
    return res.status(400).send(`Webhook signature verification failed`)
  }
}, stripeWebhook)

app.use(express.json())

app.get('/health', (req, res) => res.json({ ok: true }))

app.use('/api/payments', paymentRoutes)
// ClickPesa POSTs event notifications here for both USSD-PUSH (mobile
// money) and card payments — set this exact URL as the Application
// Webhook in the ClickPesa dashboard under Settings > Developers.
app.post('/api/payments/webhooks/clickpesa', clickpesaWebhook)
app.use('/api/admin', adminRoutes)
app.use('/api/blog', blogRoutes)
app.use('/api/members', memberRoutes)
app.use('/api/contact', contactRoutes)

app.use((err, req, res, next) => {
  console.error(err)
  res.status(500).json({ error: 'Server error' })
})

const PORT = process.env.PORT || 5000
app.listen(PORT, () => console.log(`Royal Family TZ API running on port ${PORT}`))
