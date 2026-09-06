import React, { useEffect, useState } from 'react'
import { useAuth } from '../../context/AuthContext'

// Simple CRUD panel for blog posts / YouTube video embeds.
export default function AdminBlog() {
  const { user } = useAuth()
  const [posts, setPosts] = useState([])
  const [form, setForm] = useState({ title: '', excerpt: '', youtubeId: '' })

  async function authHeaders() {
    return { 'Content-Type': 'application/json', Authorization: `Bearer ${await user.getIdToken()}` }
  }

  async function load() {
    const res = await fetch(`${import.meta.env.VITE_API_URL}/api/blog`)
    setPosts(await res.json())
  }
  useEffect(() => { load().catch(() => {}) }, [])

  async function handleCreate(e) {
    e.preventDefault()
    await fetch(`${import.meta.env.VITE_API_URL}/api/admin/blog`, {
      method: 'POST', headers: await authHeaders(), body: JSON.stringify(form),
    })
    setForm({ title: '', excerpt: '', youtubeId: '' })
    load()
  }

  async function handleDelete(id) {
    await fetch(`${import.meta.env.VITE_API_URL}/api/admin/blog/${id}`, {
      method: 'DELETE', headers: await authHeaders(),
    })
    load()
  }

  return (
    <div>
      <form onSubmit={handleCreate} className="grid md:grid-cols-3 gap-3 mb-8">
        <input required placeholder="Title" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })}
          className="border border-ink/20 rounded-lg px-3 py-2 text-sm" />
        <input placeholder="YouTube video ID" value={form.youtubeId} onChange={(e) => setForm({ ...form, youtubeId: e.target.value })}
          className="border border-ink/20 rounded-lg px-3 py-2 text-sm" />
        <button className="bg-royal text-parchment rounded-lg text-sm font-semibold">Publish post</button>
        <textarea placeholder="Excerpt" value={form.excerpt} onChange={(e) => setForm({ ...form, excerpt: e.target.value })}
          className="border border-ink/20 rounded-lg px-3 py-2 text-sm md:col-span-3" />
      </form>

      <ul className="space-y-2">
        {posts.map((p) => (
          <li key={p.id} className="flex justify-between border-b border-ink/10 py-2 text-sm">
            <span>{p.title}</span>
            <button onClick={() => handleDelete(p.id)} className="text-red-600 text-xs">Delete</button>
          </li>
        ))}
      </ul>
    </div>
  )
}
