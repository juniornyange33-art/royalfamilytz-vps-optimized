import React, { useEffect, useState } from 'react'
import { useAuth } from '../../context/AuthContext'

// Full member list, with reset-password and role controls.
// Backed by GET/PATCH /api/admin/users (server-side RBAC enforced —
// see backend/middleware/adminMiddleware.js).
export default function AdminUsers() {
  const { user } = useAuth()
  const [users, setUsers] = useState([])

  useEffect(() => {
    async function load() {
      const token = await user.getIdToken()
      const res = await fetch(`${import.meta.env.VITE_API_URL}/api/admin/users`, {
        headers: { Authorization: `Bearer ${token}` },
      })
      setUsers(await res.json())
    }
    load().catch(() => setUsers([]))
  }, [user])

  async function resetPassword(uid) {
    const token = await user.getIdToken()
    await fetch(`${import.meta.env.VITE_API_URL}/api/admin/users/${uid}/reset-password`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` },
    })
    alert('Password reset email sent to this member.')
  }

  return (
    <table className="w-full text-sm">
      <thead className="text-left text-ink/40 uppercase text-xs">
        <tr><th className="py-2">Name</th><th>Email</th><th>Membership ID</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        {users.map((u) => (
          <tr key={u.uid} className="border-t border-ink/10">
            <td className="py-3">{u.displayName}</td>
            <td>{u.email}</td>
            <td className="font-mono">{u.membershipId || '—'}</td>
            <td>{u.membershipActive ? <span className="text-growth">Active</span> : <span className="text-ink/40">Inactive</span>}</td>
            <td><button onClick={() => resetPassword(u.uid)} className="text-royal underline text-xs">Reset password</button></td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}
