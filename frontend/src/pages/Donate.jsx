import React, { useState } from 'react'

const amounts = [5000, 10000, 25000, 50000]

export default function Donate() {
  const [amount, setAmount] = useState(10000)
  const [method, setMethod] = useState('mobile')

  async function handleDonate() {
    // Calls the backend, which creates a pending transaction and returns
    // a checkout URL / USSD push depending on `method`.
    const res = await fetch(`${import.meta.env.VITE_API_URL}/api/payments/donate`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ amount, method, currency: 'TZS' }),
    })
    const data = await res.json()
    if (data.redirectUrl) window.location.href = data.redirectUrl
  }

  return (
    <div className="max-w-xl mx-auto px-5 py-20">
      <h1 className="font-display text-4xl mb-3">Support the mission</h1>
      <p className="text-ink/70 mb-8">Donations fund charity events and youth talent programs directly.</p>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        {amounts.map((a) => (
          <button key={a} onClick={() => setAmount(a)}
            className={`flex items-center justify-center h-20 rounded-xl border font-semibold text-sm shadow-sm transition-colors ${amount === a ? 'border-royal bg-royal/10 text-royal' : 'border-ink/20 bg-white'}`}>
            <div>
              <div className="text-sm">TZS</div>
              <div className="text-lg font-display">{a.toLocaleString()}</div>
            </div>
          </button>
        ))}
      </div>

      <label className="block text-sm mb-2 text-ink/60">Payment method</label>
      <select value={method} onChange={(e) => setMethod(e.target.value)} className="w-full border border-ink/20 rounded-lg px-4 py-3 mb-6">
        <option value="mobile">Mobile Money (Tanzania)</option>
        <option value="paypal">PayPal</option>
        <option value="card">Card (Visa / Mastercard)</option>
      </select>

      <button onClick={handleDonate} className="w-full bg-gold text-ink px-6 py-3 rounded-full font-semibold">
        Donate TZS {amount.toLocaleString()}
      </button>
    </div>
  )
}
