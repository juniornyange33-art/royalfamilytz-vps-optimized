import { db } from '../config/firebaseAdmin.js'

// Runs after requireAuth. Confirms the caller's Firestore user doc has
// role === 'admin' — checked server-side, so a client can never grant
// itself admin by editing local state.
export async function requireAdmin(req, res, next) {
  const snap = await db.collection('users').doc(req.user.uid).get()
  if (!snap.exists || snap.data().role !== 'admin') {
    return res.status(403).json({ error: 'Admin access required' })
  }
  next()
}
