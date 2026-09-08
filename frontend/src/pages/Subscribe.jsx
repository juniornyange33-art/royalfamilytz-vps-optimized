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
      console.error(err)
      alert('Payment failed — please try again')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-xl mx-auto px-5 py-20">
      <h1 className="font-display text-4xl mb-3">Activate membership — {TIERS[tier].name}</h1>
      <p className="text-ink/70 mb-6">Choose a billing period and payment method to activate your membership and receive your digital membership ID.</p>

      <div className="grid grid-cols-2 gap-3 mb-6">
        <button onClick={() => setPeriod('monthly')}
          className={`flex items-center justify-center h-20 rounded-xl border font-semibold text-sm shadow-sm transition-colors ${period === 'monthly' ? 'border-royal bg-royal/10 text-royal' : 'border-ink/20 bg-white'}`}>
          <div>
            <div className="text-sm">Monthly</div>
            <div className="text-lg font-display">TZS {prices.monthly.toLocaleString()}</div>
          </div>
        </button>
        <button onClick={() => setPeriod('yearly')}
          className={`flex items-center justify-center h-20 rounded-xl border font-semibold text-sm shadow-sm transition-colors ${period === 'yearly' ? 'border-royal bg-royal/10 text-royal' : 'border-ink/20 bg-white'}`}>
          <div>
            <div className="text-sm">Yearly</div>
            <div className="text-lg font-display">TZS {prices.yearly.toLocaleString()}</div>
          </div>
        </button>
      </div>

      <label className="block text-sm mb-2 text-ink/60">Payment method</label>
      <select value={method} onChange={(e) => setMethod(e.target.value)} className="w-full border border-ink/20 rounded-lg px-4 py-3 mb-4">
        <option value="mobile">Mobile Money (Tanzania)</option>
        <option value="paypal">PayPal</option>
        <option value="card">Card (Visa / Mastercard)</option>
      </select>

      {method === 'mobile' && (
        <input value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="e.g. 07XXXXXXXX"
          className="w-full border border-ink/20 rounded-lg px-4 py-3 mb-4" />
      )}

      <button onClick={handleSubscribe} disabled={loading}
        className="w-full bg-gold text-ink px-6 py-3 rounded-full font-semibold">
        {loading ? 'Processing…' : `Pay TZS ${amount.toLocaleString()} — Activate`}
      </button>

      <p className="text-xs text-ink/40 mt-4">
        Payments are processed securely by our payment providers. We do not store card details.
      </p>
    </div>
  )
}
