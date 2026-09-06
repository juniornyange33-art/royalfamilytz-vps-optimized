import React, { useState } from 'react'
import { doc, updateDoc } from 'firebase/firestore'
import { db } from '../firebase'
import { useAuth } from '../context/AuthContext'

export default function Profile() {
  const { user, profile } = useAuth()
  const [displayName, setDisplayName] = useState(profile?.displayName || '')
  const [phone, setPhone] = useState(profile?.phone || '')
  const [bio, setBio] = useState(profile?.bio || '')
  const [saved, setSaved] = useState(false)

  async function handleSave(e) {
    e.preventDefault()
    await updateDoc(doc(db, 'users', user.uid), { displayName, phone, bio })
    setSaved(true)
    setTimeout(() => setSaved(false), 2000)
  }

  return (
    <div className="max-w-lg mx-auto px-5 py-16">
      <h1 className="font-display text-3xl mb-6">Your profile</h1>
      <form onSubmit={handleSave} className="space-y-4">
        <input value={displayName} onChange={(e) => setDisplayName(e.target.value)}
          placeholder="Full name" className="w-full border border-ink/20 rounded-lg px-4 py-3" />
        <input value={phone} onChange={(e) => setPhone(e.target.value)}
          placeholder="Phone number" className="w-full border border-ink/20 rounded-lg px-4 py-3" />
        <textarea value={bio} onChange={(e) => setBio(e.target.value)} rows={4}
          placeholder="A little about you" className="w-full border border-ink/20 rounded-lg px-4 py-3" />
        <button className="bg-royal text-parchment px-6 py-3 rounded-full font-semibold">Save changes</button>
        {saved && <p className="text-growth text-sm">Saved.</p>}
      </form>
    </div>
  )
}
