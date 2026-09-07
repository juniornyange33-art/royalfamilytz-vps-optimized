import { authAdmin } from '../config/firebaseAdmin.js'
import { sendEmail } from '../services/emailService.js'

// Admin-triggered "reset this member's password" — generates a Firebase
// reset link server-side and emails it via our own template, instead of
// Firebase's default email (keeps branding consistent).
export async function adminResetPassword(req, res) {
  const { uid } = req.params
  try {
    const user = await authAdmin.getUser(uid)
    const link = await authAdmin.generatePasswordResetLink(user.email)
    await sendEmail({
      to: user.email,
      subject: 'Reset your Royal Family TZ password',
      html: `<p>An admin triggered a password reset for your account.</p>
             <p><a href="${link}">Click here to reset your password</a></p>`,
    })
    res.json({ ok: true })
  } catch (err) {
    res.status(500).json({ error: 'Could not reset password' })
  }
}
