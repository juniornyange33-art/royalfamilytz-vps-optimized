import React from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export default function Dashboard() {
  const { profile } = useAuth()

  return (
    <div className="max-w-3xl mx-auto px-5 py-16">
      <h1 className="font-display text-3xl mb-8">Welcome, {profile?.displayName || 'Member'}</h1>

      <div className="grid md:grid-cols-2 gap-6">
        <div className="border border-ink/15 rounded-2xl p-6">
          <p className="text-xs uppercase tracking-widest text-ink/40 mb-2">Membership status</p>
          {profile?.membershipActive ? (
            <>
              <p className="text-growth font-semibold text-lg mb-1">Active</p>
              <p className="font-mono text-royal text-xl">{profile.membershipId}</p>
            </>
          ) : (
            <>
              <p className="text-ink/60 font-semibold text-lg mb-3">Not a paying member yet</p>
              <div className="flex gap-3">
                <Link to="/members" className="inline-block border border-ink/10 px-4 py-2 rounded-full text-sm font-semibold">
                  Choose a plan
                </Link>
                <Link to="/subscribe" className="inline-block bg-gold text-ink px-4 py-2 rounded-full text-sm font-semibold">
                  Subscribe now
                </Link>
              </div>
            </>
          )}
        </div>

        <div className="border border-ink/15 rounded-2xl p-6">
          <p className="text-xs uppercase tracking-widest text-ink/40 mb-2">Your profile</p>
          <p className="text-ink/80">{profile?.email}</p>
          <Link to="/profile" className="inline-block mt-3 text-royal text-sm font-semibold underline">
            Edit profile
          </Link>
        </div>

        <div className="border border-ink/15 rounded-2xl p-6 md:col-span-2">
          <p className="text-xs uppercase tracking-widest text-ink/40 mb-2">Upcoming charity events</p>
          {/* Populated from /api/members/notifications — see backend notification service,
              which emails members and writes an in-app notification when an admin publishes an event. */}
          <p className="text-ink/50 text-sm">No upcoming events yet. You'll be notified here and by email.</p>
        </div>
      </div>
    </div>
  )
}
