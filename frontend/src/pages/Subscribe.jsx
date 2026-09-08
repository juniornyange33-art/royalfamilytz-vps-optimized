import React, { useState, useEffect } from 'react'
import { useAuth } from '../context/AuthContext'
import { useLocation, useNavigate } from 'react-router-dom'

const TIERS = {
  royalfamilymember: { name: 'Royal Family', monthly: 2000, yearly: 12000 },
  supporter: { name: 'Supporter', monthly: 5000, yearly: 50000 },
  patron: { name: 'Patron', monthly: 10000, yearly: 50000 },
}

export default function Subscribe() {
  const { user } = useAuth()
  const [method, setMethod] = useState('mobile')
  const [phone, setPhone] = useState('')
  const [name, setName] = useState(user?.displayName || '')
  const [email, setEmail] = useState(user?.email || '')
  const [tier, setTier] = useState('royalfamilymember')
  const [period, setPeriod] = useState('monthly')
  const [loading, setLoading] = useState(false)
  const [choosePlan, setChoosePlan] = useState(false)

  const location = useLocation()
  const navigate = useNavigate()
  useEffect(() => {
    const qp = new URLSearchParams(location.search)
    const qTier = qp.get('tier')
    const qPeriod = qp.get('period')
    // If no tier was passed explicitly, send the user to the members page
    if (!qTier) { navigate('/members'); return }
    if (qTier && TIERS[qTier]) setTier(qTier)
    if (qPeriod && (qPeriod === 'monthly' || qPeriod === 'yearly')) setPeriod(qPeriod)
  }, [location.search])

  const prices = TIERS[tier] || TIERS.royalfamilymember
  const amount = period === 'monthly' ? prices.monthly : prices.yearly

  async function handleSubscribe() {
    setLoading(true)
    try {
      if (method === 'mobile' && !phone) { alert('Please enter your mobile number for mobile payments'); setLoading(false); return }
      const token = user ? await user.getIdToken() : null
      const payload = { method, phone, tier, period, name: name || undefined, email: email || undefined }
      const res = await fetch(`${import.meta.env.VITE_API_URL}/api/payments/subscribe`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
        body: JSON.stringify(payload),
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
    <div className="max-w-2xl mx-auto px-5 py-16">
      <div className="mb-6 text-center">
        <h1 className="font-display text-4xl mb-2">Activate membership</h1>
        <p className="text-ink/70">Simple confirmation and payment for <strong className="text-royal">{TIERS[tier].name}</strong>.</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
        <div className="md:col-span-2">
          <div className="bg-white rounded-lg p-6 shadow-sm border">
            <div className="flex items-center justify-between">
              <div>
                <div className="text-sm text-ink/60">Plan</div>
                <div className="text-2xl font-display">{TIERS[tier].name}</div>
                <div className="text-ink/70 mt-1">{period === 'monthly' ? 'Monthly' : 'Yearly'} • TZS {amount.toLocaleString()}</div>
              </div>
              <div>
                <button onClick={() => setChoosePlan(!choosePlan)} className="text-sm underline text-royal">Change plan</button>
              </div>
            </div>

            {choosePlan && (
              <div className="mt-4 grid sm:grid-cols-2 gap-3">
                {Object.keys(TIERS).map((k) => (
                  <div key={k} className={`p-3 rounded-lg border ${k === tier ? 'border-royal bg-royal/5' : 'border-ink/10 bg-white'}`}>
                    <div className="flex items-center justify-between">
                      <div>
                        <div className="font-semibold">{TIERS[k].name}</div>
                        <div className="text-sm text-ink/60">Monthly: TZS {TIERS[k].monthly.toLocaleString()}</div>
                      </div>
                      <div>
                        <button onClick={() => { setTier(k); setChoosePlan(false); }} className="btn-select text-sm px-3 py-1 border rounded">Select</button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}

            <div className="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm text-ink/60 mb-2">Your name</label>
                <input value={name} onChange={(e) => setName(e.target.value)} placeholder="Full name" className="w-full border border-ink/20 rounded-lg px-4 py-3" />
              </div>
              <div>
                <label className="block text-sm text-ink/60 mb-2">Contact email</label>
                <input value={email} onChange={(e) => setEmail(e.target.value)} placeholder="you@example.com" className="w-full border border-ink/20 rounded-lg px-4 py-3" />
              </div>
            </div>

            <div className="mt-4">
              <label className="block text-sm text-ink/60 mb-2">Payment method</label>
              <select value={method} onChange={(e) => setMethod(e.target.value)} className="w-full border border-ink/20 rounded-lg px-4 py-3">
                <option value="mobile">Mobile Money (ClickPesa / M-Pesa / Tigo Pesa)</option>
                <option value="paypal">PayPal</option>
                <option value="card">Card (Stripe)</option>
              </select>
            </div>

            {method === 'mobile' && (
              <div className="mt-4">
                <label className="block text-sm text-ink/60 mb-2">Mobile number</label>
                <input value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="e.g. 07XXXXXXXX" className="w-full border border-ink/20 rounded-lg px-4 py-3" />
              </div>
            )}

            <div className="mt-6">
              <button onClick={handleSubscribe} disabled={loading} className="w-full bg-royal text-parchment px-6 py-3 rounded-full font-semibold shadow-md">
                {loading ? 'Processing…' : `Pay TZS ${amount.toLocaleString()} — Subscribe`}
              </button>
            </div>
          </div>
        </div>

        <aside className="hidden md:block">
          <div className="bg-white rounded-lg p-4 border shadow-sm">
            <h4 className="font-semibold mb-2">Need help?</h4>
            <p className="text-sm text-ink/60">Contact us at <a href="mailto:info@royalfamilytz.org" className="text-royal underline">info@royalfamilytz.org</a> or via the Contact page.</p>
            <div className="mt-4">
              <div className="text-sm text-ink/60">Amount</div>
              <div className="text-lg font-display">TZS {amount.toLocaleString()}</div>
            </div>
          </div>
        </aside>
      </div>
    </div>
  )
}
