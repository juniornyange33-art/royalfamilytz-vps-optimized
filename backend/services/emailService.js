// Thin wrapper around Resend for transactional email. Swap the `resend.emails.send`
// call for SendGrid/Mailgun/etc if preferred — everything else calling
// sendEmail() stays the same.
import { Resend } from 'resend'
import 'dotenv/config'

const resend = new Resend(process.env.RESEND_API_KEY)
const FROM = process.env.EMAIL_FROM || 'notifications@royalfamilytz.org'

export async function sendEmail({ to, subject, html }) {
  if (!process.env.RESEND_API_KEY) {
    console.warn('[emailService] RESEND_API_KEY not set — skipping send:', subject)
    return
  }
  return resend.emails.send({ from: FROM, to, subject, html })
}

export function membershipReceiptEmail({ membershipId, amount, currency, tier }) {
  const colors = {
    royalfamilymember: 'springgreen',
    supporter: 'silver',
    patron: 'gold',
  }
  const emojis = { royalfamilymember: '', supporter: '🥈', patron: '🥇' }
  const color = colors[tier] || 'black'
  const emoji = emojis[tier] || ''
  return {
    subject: 'Welcome to Royal Family TZ — your membership is active',
    html: `<p>Your membership is now active. ${emoji}</p>
           <p><b>Membership ID:</b> <span style="color:${color}; font-weight:700">${membershipId}</span></p>
           <p><b>Amount paid:</b> ${currency} ${amount}</p>`,
  }
}

export function charityEventEmail({ title, date, description }) {
  return {
    subject: `New charity event: ${title}`,
    html: `<p>${description}</p><p><b>Date:</b> ${date}</p>
           <p>See full details in your member dashboard.</p>`,
  }
}
