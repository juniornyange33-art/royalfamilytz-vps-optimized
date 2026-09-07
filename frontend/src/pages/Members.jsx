import React from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

const tiers = [
  { name: 'Standard', price: '10,000 TZS / month', perks: ['Membership ID', 'Event notifications', 'Community dashboard'] },
  { name: 'Supporter', price: '25,000 TZS / month', perks: ['Everything in Standard', 'Priority event invites', 'Recognition on site'] },
]

export default function Members() {
  const { user } = useAuth()
  return (
    <div className="max-w-4xl mx-auto px-5 py-20">
      <h1 className="font-display text-4xl mb-3">Membership</h1>
      <p className="text-ink/70 mb-10 max-w-xl">
        Members get a Royal Family membership ID, a personal dashboard, and first notice of
        charity events — funded through a simple monthly subscription.
      </p>
      <div className="grid md:grid-cols-2 gap-6">
        {tiers.map((t) => (
          <div key={t.name} className="border border-ink/15 rounded-2xl p-6">
            <h3 className="font-display text-2xl text-royal">{t.name}</h3>
            <p className="text-gold font-semibold mt-1 mb-4">{t.price}</p>
            <ul className="space-y-1 text-sm text-ink/70 mb-6">
              {t.perks.map((p) => <li key={p}>• {p}</li>)}
            </ul>
            <Link
              to={user ? '/subscribe' : '/signup'}
              className="block text-center bg-royal text-parchment py-2.5 rounded-full font-semibold"
            >
              {user ? 'Subscribe' : 'Create account to join'}
            </Link>
          </div>
        ))}
      </div>
    </div>
  )
}
