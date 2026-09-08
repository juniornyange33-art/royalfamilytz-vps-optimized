import React from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

const TIERS = [
  { key: 'royalfamilymember', name: 'Royal Family', monthly: 2000, yearly: 12000, tag: 'Spring Green ID' },
  { key: 'supporter', name: 'Supporter', monthly: 5000, yearly: 50000, tag: 'Silver ID' },
  { key: 'patron', name: 'Patron', monthly: 10000, yearly: 50000, tag: 'Gold Patron ID' },
]

export default function Members() {
  const { user } = useAuth()
  const navigate = useNavigate()

  function goToSubscribe(tier, period = 'monthly') {
    if (!user) return navigate('/signup')
    navigate(`/subscribe?tier=${tier}&period=${period}`)
  }

  return (
    <div className="max-w-5xl mx-auto px-5 py-20">
      <div className="mb-8 text-center">
        <h1 className="font-display text-4xl mb-2">Become a member</h1>
        <p className="text-ink/70 max-w-2xl mx-auto">Support our work with a small recurring contribution. Choose a tier that fits your capacity — all members receive a digital membership ID and access to member-only events and benefits.</p>
      </div>

      <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {TIERS.map((t) => (
          <div key={t.key} className="bg-white rounded-2xl shadow-sm border p-6 flex flex-col justify-between">
            <div>
              <div className="flex items-center justify-between">
                <h3 className="font-display text-2xl text-royal">{t.name}</h3>
                <span className="text-xs text-ink/60">{t.tag}</span>
              </div>
              <p className="text-ink/60 mt-3">Support community programs, trips, and youth talent development. Your membership helps fund events and scholarships.</p>

              <div className="mt-6 grid grid-cols-2 gap-3">
                <div className="p-4 rounded-lg border text-center">
                  <div className="text-sm text-ink/60">Monthly</div>
                  <div className="text-lg font-display mt-1">TZS {t.monthly.toLocaleString()}</div>
                </div>
                <div className="p-4 rounded-lg border text-center">
                  <div className="text-sm text-ink/60">Yearly</div>
                  <div className="text-lg font-display mt-1">TZS {t.yearly.toLocaleString()}</div>
                </div>
              </div>

              <ul className="mt-4 text-sm text-ink/70 space-y-2">
                <li>• Digital membership ID</li>
                <li>• Priority access to events</li>
                <li>• Exclusive updates and perks</li>
              </ul>
            </div>

            <div className="mt-6 flex gap-3">
              <button onClick={() => goToSubscribe(t.key, 'monthly')} className="flex-1 bg-royal text-parchment py-3 rounded-full font-semibold">Join — Monthly</button>
              <button onClick={() => goToSubscribe(t.key, 'yearly')} className="flex-1 border border-ink/20 py-3 rounded-full font-semibold">Join — Yearly</button>
            </div>
          </div>
        ))}
      </div>

      <div className="mt-12 max-w-2xl mx-auto text-sm text-ink/60">
        <h4 className="font-semibold mb-2">How memberships work</h4>
        <p className="mb-2">Payments are handled securely by our payment providers. After a successful payment, your membership will be activated and you'll receive a digital membership ID by email.</p>
        <p>If you need help, contact us via the Contact page.</p>
      </div>
    </div>
  )
}
