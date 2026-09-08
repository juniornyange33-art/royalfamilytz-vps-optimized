import React, { useState } from 'react'

const PRESET_AMOUNTS = [5000, 10000, 25000, 50000]

export default function Donate() {
  const [amount, setAmount] = useState(10000)
  const [method, setMethod] = useState('mobile')

  async function handleDonate() {
    const res = await fetch(`${import.meta.env.VITE_API_URL}/api/payments/donate`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ amount, method, currency: 'TZS' }),
    })
    const data = await res.json()
    if (data.redirectUrl) window.location.href = data.redirectUrl
    else if (data.ussdInstructions) alert(data.ussdInstructions)
  }

  return (
    <div className="max-w-2xl mx-auto px-5 py-20">
      <div className="mb-6 text-center">
        <h1 className="font-display text-4xl mb-2">Support the mission</h1>
        <p className="text-ink/70 max-w-xl mx-auto">Donations directly fund charity events, trips, and youth talent programs. Choose an amount and preferred payment method.</p>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        {PRESET_AMOUNTS.map((a) => (
          <button key={a} onClick={() => setAmount(a)}
            className={`flex items-center justify-center h-20 rounded-xl border font-semibold text-sm shadow-sm transform transition hover:-translate-y-0.5 ${amount === a ? 'border-royal bg-royal/10 text-royal' : 'border-ink/20 bg-white'}`}>
            <div className="text-center">
              <div className="text-sm">TZS</div>
              <div className="text-lg font-display">{a.toLocaleString()}</div>
            </div>
          </button>
        ))}
      </div>

      <div className="mb-6 flex items-center gap-3">
        <label className="block text-sm text-ink/60">Custom amount</label>
        <input type="number" value={amount} onChange={(e) => setAmount(Number(e.target.value || 0))} className="ml-auto w-40 border border-ink/20 rounded-lg px-3 py-2" />
      </div>

      <label className="block text-sm mb-2 text-ink/60">Payment method</label>
      <select value={method} onChange={(e) => setMethod(e.target.value)} className="w-full border border-ink/20 rounded-lg px-4 py-3 mb-6">
        <option value="mobile">Mobile Money (Tanzania)</option>
        <option value="paypal">PayPal</option>
        <option value="card">Card (Visa / Mastercard)</option>
      </select>

      <div className="mb-4 text-sm text-ink/60">You will be redirected to the selected payment provider after clicking Donate.</div>

      <button onClick={handleDonate} className="w-full bg-royal text-parchment px-6 py-3 rounded-full font-semibold shadow-md hover:brightness-105">
        Donate TZS {amount.toLocaleString()}
      </button>
    </div>
  )
}
