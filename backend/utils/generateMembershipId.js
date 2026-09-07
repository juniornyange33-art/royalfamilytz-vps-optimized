// Produces IDs like "RFTZ-2026-0001". Sequence is tracked in a Firestore
// counter doc so IDs stay unique and gap-free even under concurrent signups.
import { db } from '../config/firebaseAdmin.js'

export async function generateMembershipId() {
  const year = new Date().getFullYear()
  const counterRef = db.collection('counters').doc(`members-${year}`)

  const newId = await db.runTransaction(async (tx) => {
    const doc = await tx.get(counterRef)
    const next = (doc.exists ? doc.data().count : 0) + 1
    tx.set(counterRef, { count: next }, { merge: true })
    return next
  })

  return `RFTZ-${year}-${String(newId).padStart(4, '0')}`
}
