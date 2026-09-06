import React, { useState } from 'react'

export default function Contact() {
  const [form, setForm] = useState({ name: '', email: '', message: '' })
  const [status, setStatus] = useState(null)

  async function handleSubmit(e) {
    e.preventDefault()
    setStatus('sending')
    try {
      const res = await fetch(`${import.meta.env.VITE_API_URL}/api/contact`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form),
      })
      if (!res.ok) throw new Error('Failed')
      setStatus('sent')
      setForm({ name: '', email: '', message: '' })
    } catch {
      setStatus('error')
    }
  }

  return (
    <div className="max-w-xl mx-auto px-5 py-20">
      <h1 className="font-display text-4xl mb-6">Contact us</h1>
      <form onSubmit={handleSubmit} className="space-y-4">
        <input required placeholder="Your name" value={form.name}
          onChange={(e) => setForm({ ...form, name: e.target.value })}
          className="w-full border border-ink/20 rounded-lg px-4 py-3" />
        <input required type="email" placeholder="Your email" value={form.email}
          onChange={(e) => setForm({ ...form, email: e.target.value })}
          className="w-full border border-ink/20 rounded-lg px-4 py-3" />
        <textarea required placeholder="Message" rows={5} value={form.message}
          onChange={(e) => setForm({ ...form, message: e.target.value })}
          className="w-full border border-ink/20 rounded-lg px-4 py-3" />
        <button className="bg-royal text-parchment px-6 py-3 rounded-full font-semibold">
          {status === 'sending' ? 'Sending…' : 'Send message'}
        </button>
        {status === 'sent' && <p className="text-growth text-sm">Thanks — we'll get back to you soon.</p>}
        {status === 'error' && <p className="text-red-600 text-sm">Something went wrong. Try again.</p>}
      </form>
    </div>
  )
}
