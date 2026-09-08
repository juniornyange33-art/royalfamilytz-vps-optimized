import React from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

const tiers = [
  { key: 'royalfamilymember', name: 'Royal Family', monthly: 2000, yearly: 12000, color: 'springgreen', note: 'Membership ID in spring green' },
  { key: 'supporter', name: 'Supporter 🥈', monthly: 5000, yearly: 50000, color: 'silver', note: 'Silver membership ID' },
  { key: 'patron', name: 'Patron 🥇', monthly: 10000, yearly: 50000, color: 'gold', note: 'Patron membership ID in gold' },
]

export default function Members() {
  const { user } = useAuth()
  const navigate = useNavigate()

  function goToSubscribe(tier, period) {
    if (!user) return navigate('/signup')
    navigate(`/subscribe?tier=${tier}&period=${period}`)
  }

  return (
    <div className="max-w-5xl mx-auto px-5 py-20">
      <h1 className="font-display text-4xl mb-3">Become a member</h1>
      <p className="text-ink/70 mb-10 max-w-xl">
        Choose a membership level. Each supports our community programs and gives you a
        Royal Family membership ID and access to member perks.
      </p>

      <div className="grid md:grid-cols-3 gap-6">
        {tiers.map((t) => (
          <div key={t.key} className="border border-ink/15 rounded-2xl p-6 flex flex-col items-start">
            <div className="flex items-center justify-between w-full">
              <h3 className="font-display text-2xl text-royal">{t.name}</h3>
              <div className="text-sm text-ink/60">{t.note}</div>
            </div>
            <div className="mt-4 w-full">
              <div className="text-royal font-semibold">Monthly: TZS {t.monthly.toLocaleString()}</div>
              <div className="text-ink/60">Yearly: TZS {t.yearly.toLocaleString()}</div>
            </div>
            <div className="mt-6 w-full flex gap-3">
              <button onClick={() => goToSubscribe(t.key, 'monthly')} className="flex-1 bg-royal text-parchment py-2 rounded-full font-semibold">Become member (Monthly)</button>
              <button onClick={() => goToSubscribe(t.key, 'yearly')} className="flex-1 border border-ink/20 py-2 rounded-full font-semibold">Pay yearly</button>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
