import React, { createContext, useContext, useEffect, useState } from 'react'
import {
  onAuthStateChanged,
  signInWithEmailAndPassword,
  createUserWithEmailAndPassword,
  signInWithPopup,
  signOut,
  sendPasswordResetEmail,
} from 'firebase/auth'
import { doc, getDoc, setDoc, serverTimestamp } from 'firebase/firestore'
import { auth, db, googleProvider } from '../firebase'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [profile, setProfile] = useState(null) // Firestore user doc (role, membershipId, etc.)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const unsub = onAuthStateChanged(auth, async (firebaseUser) => {
      setUser(firebaseUser)
      if (firebaseUser) {
        const snap = await getDoc(doc(db, 'users', firebaseUser.uid))
        setProfile(snap.exists() ? snap.data() : null)
      } else {
        setProfile(null)
      }
      setLoading(false)
    })
    return unsub
  }, [])

  async function ensureUserDoc(firebaseUser, extra = {}) {
    const ref = doc(db, 'users', firebaseUser.uid)
    const snap = await getDoc(ref)
    if (!snap.exists()) {
      await setDoc(ref, {
        uid: firebaseUser.uid,
        email: firebaseUser.email,
        displayName: firebaseUser.displayName || extra.displayName || '',
        role: 'member',
        membershipActive: false,
        membershipId: null,
        createdAt: serverTimestamp(),
        ...extra,
      })
    }
  }

  async function signup(email, password, displayName) {
    const cred = await createUserWithEmailAndPassword(auth, email, password)
    await ensureUserDoc(cred.user, { displayName })
    return cred.user
  }

  async function login(email, password) {
    const cred = await signInWithEmailAndPassword(auth, email, password)
    return cred.user
  }

  async function loginWithGoogle() {
    const cred = await signInWithPopup(auth, googleProvider)
    await ensureUserDoc(cred.user)
    return cred.user
  }

  async function logout() {
    await signOut(auth)
  }

  async function resetPassword(email) {
    // Self-service reset: sends Firebase's built-in reset email.
    // Admin-triggered resets (from the admin dashboard) instead call
    // POST /api/admin/users/:uid/reset-password on the backend, which
    // uses the Firebase Admin SDK to generate the link and send it via
    // the custom email service (see backend/services/emailService.js).
    await sendPasswordResetEmail(auth, email)
  }

  const value = { user, profile, loading, signup, login, loginWithGoogle, logout, resetPassword }
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  return useContext(AuthContext)
}
