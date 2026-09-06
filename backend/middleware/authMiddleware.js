import { authAdmin } from '../config/firebaseAdmin.js'

// Verifies the Firebase ID token sent as "Authorization: Bearer <token>".
// This is the server-side half of RBAC — never trust a role claim sent
// from the client itself.
export async function requireAuth(req, res, next) {
  const header = req.headers.authorization || ''
  const token = header.startsWith('Bearer ') ? header.slice(7) : null
  if (!token) return res.status(401).json({ error: 'Missing auth token' })

  try {
    const decoded = await authAdmin.verifyIdToken(token)
    req.user = decoded // { uid, email, ... }
    next()
  } catch (err) {
    res.status(401).json({ error: 'Invalid or expired token' })
  }
}
