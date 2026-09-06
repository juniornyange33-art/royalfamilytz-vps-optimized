import React, { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export default function Login() {
  const { login, loginWithGoogle, resetPassword } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const navigate = useNavigate()

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    try {
      await login(email, password)
      navigate('/dashboard')
    } catch (err) {
      setError('Incorrect email or password.')
    }
  }

  async function handleForgot() {
    if (!email) return setError('Enter your email above first, then tap "Forgot password".')
    await resetPassword(email)
    setError('')
    alert('Password reset email sent.')
  }

  return (
    <div className="max-w-sm mx-auto px-5 py-20">
      <h1 className="font-display text-3xl mb-6">Log in</h1>
      <form onSubmit={handleSubmit} className="space-y-4">
        <input required type="email" placeholder="Email" value={email}
          onChange={(e) => setEmail(e.target.value)} className="w-full border border-ink/20 rounded-lg px-4 py-3" />
        <input required type="password" placeholder="Password" value={password}
          onChange={(e) => setPassword(e.target.value)} className="w-full border border-ink/20 rounded-lg px-4 py-3" />
        {error && <p className="text-red-600 text-sm">{error}</p>}
        <button className="w-full bg-royal text-parchment py-3 rounded-full font-semibold">Log in</button>
        <button type="button" onClick={handleForgot} className="text-xs text-ink/50 underline">Forgot password?</button>
      </form>
      <div className="my-6 flex items-center gap-3 text-ink/30 text-xs">
        <div className="flex-1 h-px bg-ink/10" /> OR <div className="flex-1 h-px bg-ink/10" />
      </div>
      <button onClick={() => loginWithGoogle().then(() => navigate('/dashboard'))}
        className="w-full border border-ink/20 py-3 rounded-full font-semibold">
        Continue with Google
      </button>
      <p className="text-sm text-ink/60 mt-6">
        No account yet? <Link to="/signup" className="text-royal font-semibold">Sign up</Link>
      </p>
    </div>
  )
}
