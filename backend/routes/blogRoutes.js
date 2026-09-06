import express from 'express'
import { listPosts } from '../controllers/blogController.js'

const router = express.Router()
router.get('/', listPosts) // public — admin CRUD lives under /api/admin/blog
export default router
