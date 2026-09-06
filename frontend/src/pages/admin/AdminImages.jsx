import React, { useState } from 'react'
import { useAuth } from '../../context/AuthContext'

// Uploads replace site images (hero photo, about photo, etc.) stored in
// Firebase Storage; backend writes the resulting URL to a `siteConfig`
// Firestore doc that the public pages read from.
export default function AdminImages() {
  const { user } = useAuth()
  const [slot, setSlot] = useState('hero')
  const [file, setFile] = useState(null)
  const [status, setStatus] = useState('')

  async function handleUpload(e) {
    e.preventDefault()
    if (!file) return
    setStatus('Uploading…')
    const fd = new FormData()
    fd.append('image', file)
    fd.append('slot', slot)
    const token = await user.getIdToken()
    const res = await fetch(`${import.meta.env.VITE_API_URL}/api/admin/images`, {
      method: 'POST', headers: { Authorization: `Bearer ${token}` }, body: fd,
    })
    setStatus(res.ok ? 'Uploaded.' : 'Upload failed.')
  }

  return (
    <form onSubmit={handleUpload} className="max-w-sm space-y-4">
      <select value={slot} onChange={(e) => setSlot(e.target.value)} className="w-full border border-ink/20 rounded-lg px-3 py-2 text-sm">
        <option value="hero">Home hero image</option>
        <option value="about">About page image</option>
        <option value="logo">Logo</option>
      </select>
      <input type="file" accept="image/*" onChange={(e) => setFile(e.target.files[0])} className="text-sm" />
      <button className="bg-royal text-parchment px-5 py-2.5 rounded-full text-sm font-semibold">Upload</button>
      {status && <p className="text-xs text-ink/50">{status}</p>}
    </form>
  )
}
