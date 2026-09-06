import React from 'react'
import Logo from './Logo'

export default function Footer() {
  return (
    <footer className="bg-navy text-parchment/80 mt-24">
      <div className="max-w-6xl mx-auto px-5 py-12 grid gap-8 md:grid-cols-3">
        <div>
          <Logo dark size={34} />
          <p className="mt-3 text-sm text-parchment/60 max-w-xs">
            A community built on service and the growth of young talent, in Arusha and beyond.
          </p>
        </div>
        <div>
          <h4 className="font-display text-parchment mb-3">Explore</h4>
          <ul className="space-y-2 text-sm">
            <li><a href="/about" className="hover:text-gold">About</a></li>
            <li><a href="/members" className="hover:text-gold">Membership</a></li>
            <li><a href="/blog" className="hover:text-gold">Blog & Videos</a></li>
            <li><a href="/donate" className="hover:text-gold">Donate</a></li>
          </ul>
        </div>
        <div>
          <h4 className="font-display text-parchment mb-3">Connect</h4>
          <ul className="space-y-2 text-sm">
            <li><a href="/contact" className="hover:text-gold">Contact us</a></li>
            <li><a href="https://youtube.com" target="_blank" rel="noreferrer" className="hover:text-gold">YouTube Channel</a></li>
          </ul>
        </div>
      </div>
      <div className="text-center text-xs text-parchment/40 pb-6">
        © {new Date().getFullYear()} Royal Family TZ. All rights reserved.
      </div>
    </footer>
  )
}
