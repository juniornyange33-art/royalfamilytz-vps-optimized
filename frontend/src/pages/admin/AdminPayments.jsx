import React, { useEffect, useState } from 'react'
import { useAuth } from '../../context/AuthContext'

// Read-only ledger of transactions recorded by the backend payment webhooks
// (Azampay/Selcom, PayPal, Stripe) — see backend/controllers/paymentController.js.
export default function AdminPayments() {
  const { user } = useAuth()
  const [txns, setTxns] = useState([])

  useEffect(() => {
    async function load() {
      const token = await user.getIdToken()
      const res = await fetch(`${import.meta.env.VITE_API_URL}/api/admin/transactions`, {
        headers: { Authorization: `Bearer ${token}` },
      })
      setTxns(await res.json())
    }
    load().catch(() => setTxns([]))
  }, [user])

  return (
    <table className="w-full text-sm">
      <thead className="text-left text-ink/40 uppercase text-xs">
        <tr><th className="py-2">Date</th><th>Member</th><th>Method</th><th>Amount</th><th>Status</th></tr>
      </thead>
      <tbody>
        {txns.map((t) => (
          <tr key={t.id} className="border-t border-ink/10">
            <td className="py-3">{new Date(t.createdAt).toLocaleDateString()}</td>
            <td>{t.userEmail}</td>
            <td className="capitalize">{t.method}</td>
            <td>{t.currency} {t.amount.toLocaleString()}</td>
            <td className={t.status === 'succeeded' ? 'text-growth' : 'text-ink/40'}>{t.status}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}
