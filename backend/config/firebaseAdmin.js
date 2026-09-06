// Firebase Admin SDK init — used for verifying ID tokens, managing users
// (password resets, role claims), and server-side Firestore access that
// bypasses client security rules (needed for RBAC checks, writing
// membership status after a payment webhook, etc).
import admin from 'firebase-admin'
import 'dotenv/config'

if (!admin.apps.length) {
  admin.initializeApp({
    credential: admin.credential.cert({
      projectId: process.env.FIREBASE_PROJECT_ID,
      clientEmail: process.env.FIREBASE_CLIENT_EMAIL,
      // Render literal newlines from the .env value.
      privateKey: (process.env.FIREBASE_PRIVATE_KEY || '').replace(/\\n/g, '\n'),
    }),
  })
}

export const db = admin.firestore()
export const authAdmin = admin.auth()
export const storageAdmin = admin.storage()
export default admin
