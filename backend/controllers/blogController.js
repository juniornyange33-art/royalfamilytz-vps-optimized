import { db } from '../config/firebaseAdmin.js'

export async function listPosts(req, res) {
  const snap = await db.collection('blogPosts').orderBy('createdAt', 'desc').get()
  res.json(snap.docs.map((d) => ({ id: d.id, ...d.data() })))
}

export async function createPost(req, res) {
  const { title, excerpt, youtubeId } = req.body
  const ref = await db.collection('blogPosts').add({
    title, excerpt, youtubeId: youtubeId || null, createdAt: new Date().toISOString(),
  })
  res.json({ id: ref.id })
}

export async function deletePost(req, res) {
  await db.collection('blogPosts').doc(req.params.id).delete()
  res.json({ ok: true })
}
