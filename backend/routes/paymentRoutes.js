import express from 'express'
import { requireAuth } from '../middleware/authMiddleware.js'
import {
  startSubscription, capturePaypal, donate,
} from '../controllers/paymentController.js'

const router = express.Router()

router.post('/subscribe', requireAuth, startSubscription)
router.post('/paypal/capture', requireAuth, capturePaypal)
router.post('/donate', donate) // donations don't require an account

export default router
