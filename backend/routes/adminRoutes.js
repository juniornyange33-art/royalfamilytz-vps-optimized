import express from 'express'
import multer from 'multer'
import { requireAuth } from '../middleware/authMiddleware.js'
import { requireAdmin } from '../middleware/adminMiddleware.js'
import { listUsers, listTransactions, publishEvent, uploadImage } from '../controllers/adminController.js'
import { adminResetPassword } from '../controllers/authController.js'
import { createPost, deletePost } from '../controllers/blogController.js'

const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 5 * 1024 * 1024 } })
const router = express.Router()

// Every route below is admin-only: requireAuth verifies identity,
// requireAdmin checks the Firestore role server-side.
router.use(requireAuth, requireAdmin)

router.get('/users', listUsers)
router.post('/users/:uid/reset-password', adminResetPassword)

router.get('/transactions', listTransactions)

router.post('/blog', createPost)
router.delete('/blog/:id', deletePost)

router.post('/images', upload.single('image'), uploadImage)

router.post('/events', publishEvent)

export default router
