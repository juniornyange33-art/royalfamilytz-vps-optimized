import { db } from '../config/firebaseAdmin.js'

// GET /api/members/notifications — charity events a member hasn't seen yet.
export async function getNotifications(req, res) {
  const snap = await db.collection('notifications')
    .where('audience', '==', 'members')
    .orderBy('createdAt', 'desc')
    .limit(20)
    .get()
  res.json(snap.docs.map((d) => ({ id: d.id, ...d.data() })))
}
