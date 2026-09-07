import React from 'react'

// Signature mark: a crown resting on a growth leaf — royalty + youth growth.
// Swap for a raster/SVG file from the admin "Site Images" panel once branding is final.
export default function Logo({ dark = false, size = 40 }) {
  const stroke = dark ? '#F8F4E9' : '#3B1F63'
  const gold = '#D4A017'
  return (
    <div className="flex items-center gap-2">
      <svg width={size} height={size} viewBox="0 0 48 48" fill="none">
        <path d="M8 34 L12 16 L20 26 L24 12 L28 26 L36 16 L40 34 Z" stroke={gold} strokeWidth="2.5" strokeLinejoin="round" fill="none" />
        <path d="M14 40 C 20 34, 28 34, 34 40" stroke={stroke} strokeWidth="2.5" strokeLinecap="round" fill="none" />
      </svg>
      <span className={`font-display text-xl tracking-tight ${dark ? 'text-parchment' : 'text-ink'}`}>
        Royal<span style={{ color: gold }}>Family</span>TZ
      </span>
    </div>
  )
}
