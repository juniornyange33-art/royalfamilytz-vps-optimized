import React from 'react'
import { Navigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export default function AdminRoute({ children }) {
  const { user, profile, loading } = useAuth()
  if (loading) return <div className="p-10 text-center">Loading…</div>
  if (!user || profile?.role !== 'admin') return <Navigate to="/" replace />
  return children
}
