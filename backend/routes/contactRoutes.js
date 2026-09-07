import express from 'express'
import { sendEmail } from '../services/emailService.js'

const router = express.Router()

router.post('/', async (req, res) => {
  const { name, email, message } = req.body
  if (!name || !email || !message) return res.status(400).json({ error: 'Missing fields' })

  await sendEmail({
    to: process.env.EMAIL_FROM,
    subject: `Contact form: ${name}`,
    html: `<p><b>From:</b> ${name} (${email})</p><p>${message}</p>`,
  })
  res.json({ ok: true })
})

export default router
