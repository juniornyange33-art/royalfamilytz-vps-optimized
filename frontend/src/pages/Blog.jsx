import React, { useEffect, useState } from 'react'

// Fetches blog posts from the backend, which in turn stores posts (title,
// body, optional YouTube video ID) added via the Admin > Blog CRUD panel.
export default function Blog() {
  const [posts, setPosts] = useState([])

  useEffect(() => {
    fetch(`${import.meta.env.VITE_API_URL}/api/blog`)
      .then((r) => r.json())
      .then(setPosts)
      .catch(() => setPosts([]))
  }, [])

  return (
    <div className="max-w-4xl mx-auto px-5 py-20">
      <h1 className="font-display text-4xl mb-2">Blog & Videos</h1>
      <p className="text-ink/70 mb-10">
        Stories from the community, plus the latest from the{' '}
        <a href="https://youtube.com" target="_blank" rel="noreferrer" className="text-royal underline">
          Royal Family YouTube channel
        </a>.
      </p>

      {posts.length === 0 && (
        <p className="text-ink/40 text-sm">No posts yet — check back soon.</p>
      )}

      <div className="space-y-10">
        {posts.map((post) => (
          <article key={post.id} className="border-b border-ink/10 pb-10">
            <h2 className="font-display text-2xl mb-2">{post.title}</h2>
            <p className="text-ink/70 mb-4">{post.excerpt}</p>
            {post.youtubeId && (
              <div className="aspect-video rounded-xl overflow-hidden">
                <iframe
                  className="w-full h-full"
                  src={`https://www.youtube.com/embed/${post.youtubeId}`}
                  title={post.title}
                  allowFullScreen
                />
              </div>
            )}
          </article>
        ))}
      </div>
    </div>
  )
}
