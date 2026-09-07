import React from 'react'
import { Link } from 'react-router-dom'

export default function Home() {
  return (
    <div>
      <section className="max-w-6xl mx-auto px-5 pt-20 pb-24 grid md:grid-cols-2 gap-10 items-center">
        <div>
          <p className="uppercase tracking-widest text-xs font-semibold text-gold mb-4">Arusha, Tanzania</p>
          <h1 className="font-display text-5xl md:text-6xl leading-[1.05] text-ink">
            A family raising up <span className="text-royal">community</span> and <span className="text-growth">young talent</span>.
          </h1>
          <p className="mt-6 text-ink/70 text-lg max-w-md">
            Royal Family TZ brings people together around service, mentorship, and the growth
            of youth talent — in sport, arts, and skills that build a future.
          </p>
          <div className="mt-8 flex gap-4">
            <Link to="/signup" className="bg-royal text-parchment px-6 py-3 rounded-full font-semibold hover:bg-ink transition-colors">
              Become a member
            </Link>
            <Link to="/about" className="px-6 py-3 rounded-full font-semibold border border-ink/20 hover:border-royal transition-colors">
              Our story
            </Link>
          </div>
        </div>
        <div className="aspect-square rounded-3xl bg-gold/5 border border-gold/30 flex items-center justify-center">
          <span className="text-ink/40 text-sm">Hero image / community photo goes here</span>
        </div>
      </section>

      <section className="bg-navy text-parchment py-20">
        <div className="max-w-6xl mx-auto px-5 grid md:grid-cols-3 gap-10">
          {[
            { title: 'Community service', body: 'Outreach and charity events that respond to real needs in Arusha and beyond.' },
            { title: 'Youth talent growth', body: 'Mentorship and platforms for young people to develop and showcase their talent.' },
            { title: 'A family, not a crowd', body: 'Membership means belonging — a membership ID, a dashboard, and a voice.' },
          ].map((f) => (
            <div key={f.title}>
              <h3 className="font-display text-2xl mb-2 text-gold">{f.title}</h3>
              <p className="text-parchment/70">{f.body}</p>
            </div>
          ))}
        </div>
      </section>
    </div>
  )
}
