import React, { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export default function Signup() {
  const { signup, loginWithGoogle } = useAuth()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const navigate = useNavigate()

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    try {
      await signup(email, password, name)
      navigate('/dashboard')
    } catch (err) {
      setError(err.message.includes('email-already') ? 'That email is already registered.' : 'Could not create account.')
    }
  }

  return (
    <div className="max-w-sm mx-auto px-5 py-20">
      <h1 className="font-display text-3xl mb-6">Create your account</h1>
      <form onSubmit={handleSubmit} className="space-y-4">
        <input required placeholder="Full name" value={name}
          onChange={(e) => setName(e.target.value)} className="w-full border border-ink/20 rounded-lg px-4 py-3" />
        <input required type="email" placeholder="Email" value={email}
          onChange={(e) => setEmail(e.target.value)} className="w-full border border-ink/20 rounded-lg px-4 py-3" />
        <input required type="password" placeholder="Password (min 6 characters)" value={password}
          onChange={(e) => setPassword(e.target.value)} className="w-full border border-ink/20 rounded-lg px-4 py-3" />
        {error && <p className="text-red-600 text-sm">{error}</p>}
        <button className="w-full bg-royal text-parchment py-3 rounded-full font-semibold">Sign up</button>
      </form>
      <div className="my-6 flex items-center gap-3 text-ink/30 text-xs">
        <div className="flex-1 h-px bg-ink/10" /> OR <div className="flex-1 h-px bg-ink/10" />
      </div>
      <button onClick={() => loginWithGoogle().then(() => navigate('/dashboard'))}
        className="w-full border border-ink/20 py-3 rounded-full font-semibold">
        Continue with Google
      </button>
      <p className="text-sm text-ink/60 mt-6">
        Already a member? <Link to="/login" className="text-royal font-semibold">Log in</Link>
      </p>
    </div>
  )
}
