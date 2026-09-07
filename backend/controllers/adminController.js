import { db } from '../config/firebaseAdmin.js'
import { storageAdmin } from '../config/firebaseAdmin.js'
import { sendEmail, charityEventEmail } from '../services/emailService.js'

export async function listUsers(req, res) {
  const snap = await db.collection('users').get()
  res.json(snap.docs.map((d) => d.data()))
}

export async function listTransactions(req, res) {
  const snap = await db.collection('transactions').orderBy('createdAt', 'desc').limit(200).get()
  res.json(snap.docs.map((d) => ({ id: d.id, ...d.data() })))
}

// POST /api/admin/events — publishes a charity event: writes an in-app
// notification for members' dashboards AND emails every active member.
export async function publishEvent(req, res) {
  const { title, date, description } = req.body

  await db.collection('notifications').add({
    audience: 'members', title, date, description, createdAt: new Date().toISOString(),
  })

  const members = await db.collection('users').where('membershipActive', '==', true).get()
  const { subject, html } = charityEventEmail({ title, date, description })
  await Promise.all(members.docs.map((m) => sendEmail({ to: m.data().email, subject, html })))

  res.json({ ok: true, notified: members.size })
}

// POST /api/admin/images (multipart) — uploads to Firebase Storage and
// records the resulting URL against a named "slot" (hero, about, logo)
// that public pages read from a `siteConfig/images` Firestore doc.
export async function uploadImage(req, res) {
  const { slot } = req.body
  const file = req.file
  if (!file) return res.status(400).json({ error: 'No image uploaded' })

  const bucket = storageAdmin.bucket()
  const dest = `site-images/${slot}-${Date.now()}${extname(file.originalname)}`
  const blob = bucket.file(dest)
  await blob.save(file.buffer, { contentType: file.mimetype, public: true })
  const url = `https://storage.googleapis.com/${bucket.name}/${dest}`

  await db.collection('siteConfig').doc('images').set({ [slot]: url }, { merge: true })
  res.json({ url })
}

function extname(name) {
  const i = name.lastIndexOf('.')
  return i === -1 ? '' : name.slice(i)
}
