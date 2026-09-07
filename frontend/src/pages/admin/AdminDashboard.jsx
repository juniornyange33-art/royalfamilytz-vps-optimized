import React from 'react'
import { Link, Outlet, NavLink } from 'react-router-dom'

const tabs = [
  { to: '/admin', label: 'Overview', end: true },
  { to: '/admin/users', label: 'Members' },
  { to: '/admin/payments', label: 'Payments' },
  { to: '/admin/blog', label: 'Blog & Videos' },
  { to: '/admin/images', label: 'Site Images' },
]

export default function AdminDashboard() {
  return (
    <div className="max-w-6xl mx-auto px-5 py-10">
      <h1 className="font-display text-3xl mb-6">Admin</h1>
      <div className="flex gap-2 mb-8 border-b border-ink/10 overflow-x-auto">
        {tabs.map((t) => (
          <NavLink key={t.to} to={t.to} end={t.end}
            className={({ isActive }) => `px-4 py-2 text-sm font-semibold whitespace-nowrap ${isActive ? 'border-b-2 border-royal text-royal' : 'text-ink/50'}`}>
            {t.label}
          </NavLink>
        ))}
      </div>
      <Outlet />
    </div>
  )
}
