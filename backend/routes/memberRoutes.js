import express from 'express'
import { requireAuth } from '../middleware/authMiddleware.js'
import { getNotifications } from '../controllers/memberController.js'

const router = express.Router()
router.get('/notifications', requireAuth, getNotifications)
export default router
