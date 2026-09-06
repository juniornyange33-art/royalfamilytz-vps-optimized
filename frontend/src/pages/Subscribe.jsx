import React, { useState } from 'react'
import { useAuth } from '../context/AuthContext'

// Kicks off a subscription payment. The backend creates a pending
// transaction, talks to the chosen provider, and — on confirmed webhook —
// activates membership, generates a membership ID, and emails the receipt.
export default function Subscribe() {
  const { user } = useAuth()
  const [method, setMethod] = useState('mobile')
  const [phone, setPhone] = useState('')
  const [loading, setLoading] = useState(false)

  async function handleSubscribe() {
    setLoading(true)
    try {
      const res = await fetch(`${import.meta.env.VITE_API_URL}/api/payments/subscribe`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${await user.getIdToken()}` },
        body: JSON.stringify({ method, phone, tier: 'standard' }),
      })
      const data = await res.json()
      if (data.redirectUrl) window.location.href = data.redirectUrl
      else if (data.ussdInstructions) alert(data.ussdInstructions)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-md mx-auto px-5 py-16">
      <h1 className="font-display text-3xl mb-6">Subscribe</h1>
      <label className="block text-sm text-ink/60 mb-2">Payment method</label>
      <select value={method} onChange={(e) => setMethod(e.target.value)} className="w-full border border-ink/20 rounded-lg px-4 py-3 mb-4">
        <option value="mobile">Mobile Money (M-Pesa / Tigo Pesa / Airtel Money)</option>
        <option value="paypal">PayPal</option>
        <option value="card">Card (Visa / Mastercard)</option>
      </select>

      {method === 'mobile' && (
        <input value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="e.g. 07XXXXXXXX"
          className="w-full border border-ink/20 rounded-lg px-4 py-3 mb-4" />
      )}

      <button onClick={handleSubscribe} disabled={loading}
        className="w-full bg-royal text-parchment py-3 rounded-full font-semibold disabled:opacity-50">
        {loading ? 'Processing…' : 'Pay & activate membership'}
      </button>
      <p className="text-xs text-ink/40 mt-4">
        Payments are processed securely and never stored on our servers — card details go
        directly to the payment provider (Stripe / PayPal / mobile money gateway).
      </p>
    </div>
  )
}
