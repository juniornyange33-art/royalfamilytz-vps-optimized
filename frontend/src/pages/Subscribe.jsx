import React, { useState, useEffect } from 'react'
import { useAuth } from '../context/AuthContext'
import { useLocation } from 'react-router-dom'

const TIERS = {
  royalfamilymember: { name: 'Royal Family', monthly: 2000, yearly: 12000 },
  supporter: { name: 'Supporter', monthly: 5000, yearly: 50000 },
  patron: { name: 'Patron', monthly: 10000, yearly: 50000 },
}

export default function Subscribe() {
  const { user } = useAuth()
  const [method, setMethod] = useState('mobile')
  const [phone, setPhone] = useState('')
  const [tier, setTier] = useState('royalfamilymember')
  const [period, setPeriod] = useState('monthly')
  const [loading, setLoading] = useState(false)

  const location = useLocation()
  useEffect(() => {
    const qp = new URLSearchParams(location.search)
    const qTier = qp.get('tier')
    const qPeriod = qp.get('period')
    if (qTier && TIERS[qTier]) setTier(qTier)
    if (qPeriod && (qPeriod === 'monthly' || qPeriod === 'yearly')) setPeriod(qPeriod)
  }, [location.search])

  const prices = TIERS[tier] || TIERS.royalfamilymember
  const amount = period === 'monthly' ? prices.monthly : prices.yearly

  async function handleSubscribe() {
    setLoading(true)
    try {
      const token = user ? await user.getIdToken() : null
      const res = await fetch(`${import.meta.env.VITE_API_URL}/api/payments/subscribe`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
        body: JSON.stringify({ method, phone, tier, period }),
      })
      const data = await res.json()
      if (data.redirectUrl) window.location.href = data.redirectUrl
      else if (data.ussdInstructions) alert(data.ussdInstructions)
    } catch (err) {
      console.error('Subscribe error', err)
      alert('Payment failed — please try again')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-2xl mx-auto px-5 py-20">
      <div className="mb-6 text-center">
        <h1 className="font-display text-4xl mb-2">Activate membership — {TIERS[tier].name}</h1>
        <p className="text-ink/70">Choose billing period, payment method, and confirm your details to activate membership.</p>
      </div>

      <div className="mb-6 grid grid-cols-2 gap-3">
        <button onClick={() => setPeriod('monthly')}
          className={`flex items-center justify-center h-20 rounded-xl border font-semibold text-sm shadow-sm transform transition hover:-translate-y-0.5 ${period === 'monthly' ? 'border-royal bg-royal/10 text-royal' : 'border-ink/20 bg-white'}`}>
          <div>
            <div className="text-sm">Monthly</div>
            <div className="text-lg font-display">TZS {prices.monthly.toLocaleString()}</div>
          </div>
        </button>
        <button onClick={() => setPeriod('yearly')}
          className={`flex items-center justify-center h-20 rounded-xl border font-semibold text-sm shadow-sm transform transition hover:-translate-y-0.5 ${period === 'yearly' ? 'border-royal bg-royal/10 text-royal' : 'border-ink/20 bg-white'}`}>
          <div>
            <div className="text-sm">Yearly</div>
            <div className="text-lg font-display">TZS {prices.yearly.toLocaleString()}</div>
          </div>
        </button>
      </div>

      <div className="grid sm:grid-cols-2 gap-4 mb-4">
        <div>
          <label className="block text-sm text-ink/60 mb-2">Payment method</label>
          <select value={method} onChange={(e) => setMethod(e.target.value)} className="w-full border border-ink/20 rounded-lg px-4 py-3">
            <option value="mobile">Mobile Money (M-Pesa / Tigo Pesa / Airtel Money)</option>
            <option value="paypal">PayPal</option>
            <option value="card">Card (Visa / Mastercard)</option>
          </select>
        </div>

        {method === 'mobile' && (
          <div>
            <label className="block text-sm text-ink/60 mb-2">Mobile number</label>
            <input value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="e.g. 07XXXXXXXX"
              className="w-full border border-ink/20 rounded-lg px-4 py-3" />
          </div>
        )}
      </div>

      <div className="mb-4">
        <div className="p-4 rounded-lg border bg-white shadow-sm">
          <div className="flex items-center justify-between">
            <div className="text-sm text-ink/60">Amount</div>
            <div className="text-lg font-display">TZS {amount.toLocaleString()}</div>
          </div>
          <div className="text-xs text-ink/50 mt-2">You will be redirected to complete payment. After successful payment your membership will be activated.</div>
        </div>
      </div>

      <button onClick={handleSubscribe} disabled={loading}
        className="w-full bg-royal text-parchment px-6 py-3 rounded-full font-semibold shadow-md">
        {loading ? 'Processing…' : `Pay TZS ${amount.toLocaleString()} — Activate`}
      </button>

      <p className="text-xs text-ink/40 mt-4">Payments are processed securely by our payment providers. We do not store card details.</p>
    </div>
  )
}
