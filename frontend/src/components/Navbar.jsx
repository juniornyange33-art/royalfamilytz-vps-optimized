import React, { useState } from 'react'
import { Link, NavLink } from 'react-router-dom'
import Logo from './Logo'
import { useAuth } from '../context/AuthContext'

const links = [
  { to: '/', label: 'Home' },
  { to: '/about', label: 'About' },
  { to: '/members', label: 'Membership' },
  { to: '/blog', label: 'Blog' },
  { to: '/donate', label: 'Donate' },
  { to: '/contact', label: 'Contact' },
]

export default function Navbar() {
  const [open, setOpen] = useState(false)
  const { user, profile, logout } = useAuth()

  return (
    <header className="sticky top-0 z-50 bg-parchment/90 backdrop-blur border-b border-ink/10">
      <div className="max-w-6xl mx-auto flex items-center justify-between px-5 py-3">
        <Link to="/"><Logo /></Link>

        <nav className="hidden md:flex items-center gap-6">
          {links.map((l) => (
            <NavLink
              key={l.to}
              to={l.to}
              className={({ isActive }) =>
                `text-sm font-medium transition-colors ${isActive ? 'text-royal' : 'text-ink/70 hover:text-royal'}`
              }
            >
              {l.label}
            </NavLink>
          ))}
        </nav>

        <div className="hidden md:flex items-center gap-3">
          {user ? (
            <>
              <Link to="/dashboard" className="text-sm font-medium text-royal">Dashboard</Link>
              {profile?.role === 'admin' && (
                <Link to="/admin" className="text-sm font-medium text-gold">Admin</Link>
              )}
              <button onClick={logout} className="text-sm font-medium text-ink/60 hover:text-ink">Log out</button>
            </>
          ) : (
            <>
              <Link to="/login" className="text-sm font-medium text-ink/70 hover:text-royal">Log in</Link>
              <Link to="/signup" className="text-sm font-semibold bg-royal text-parchment px-4 py-2 rounded-full hover:bg-ink transition-colors">
                Join Royal Family
              </Link>
            </>
          )}
        </div>

        <button className="md:hidden" onClick={() => setOpen(!open)} aria-label="Toggle menu">
          <div className="w-6 h-0.5 bg-ink mb-1.5" />
          <div className="w-6 h-0.5 bg-ink mb-1.5" />
          <div className="w-6 h-0.5 bg-ink" />
        </button>
      </div>

      {open && (
        <div className="md:hidden px-5 pb-4 flex flex-col gap-3 bg-parchment border-t border-ink/10">
          {links.map((l) => (
            <Link key={l.to} to={l.to} onClick={() => setOpen(false)} className="text-sm font-medium text-ink/80">
              {l.label}
            </Link>
          ))}
          {user ? (
            <>
              <Link to="/dashboard" onClick={() => setOpen(false)} className="text-sm font-semibold text-royal">Dashboard</Link>
              <button onClick={() => { logout(); setOpen(false) }} className="text-sm text-left text-ink/60">Log out</button>
            </>
          ) : (
            <>
              <Link to="/login" onClick={() => setOpen(false)} className="text-sm font-medium text-ink/80">Log in</Link>
              <Link to="/signup" onClick={() => setOpen(false)} className="text-sm font-semibold text-royal">Join Royal Family</Link>
            </>
          )}
        </div>
      )}
    </header>
  )
}
