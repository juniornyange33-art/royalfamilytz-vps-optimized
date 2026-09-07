import React from 'react'

export default function AdminOverview() {
  return (
    <div className="grid md:grid-cols-3 gap-6">
      <div className="border border-ink/15 rounded-2xl p-6">
        <p className="text-xs uppercase tracking-widest text-ink/40">Active members</p>
        <p className="font-display text-3xl mt-2">—</p>
      </div>
      <div className="border border-ink/15 rounded-2xl p-6">
        <p className="text-xs uppercase tracking-widest text-ink/40">This month's revenue</p>
        <p className="font-display text-3xl mt-2">—</p>
      </div>
      <div className="border border-ink/15 rounded-2xl p-6">
        <p className="text-xs uppercase tracking-widest text-ink/40">Blog posts</p>
        <p className="font-display text-3xl mt-2">—</p>
      </div>
      <p className="md:col-span-3 text-xs text-ink/40">
        Wire these to GET /api/admin/stats once the backend is connected to real data.
      </p>
    </div>
  )
}
