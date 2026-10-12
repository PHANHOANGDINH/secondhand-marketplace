export { orderStatusLabels as orderStatuses, paymentStatusLabels as paymentStatuses } from '../utils/labels'
export const nextOrderStatus = {
  PENDING: 'CONFIRMED',
  CONFIRMED: 'PROCESSING',
  PROCESSING: 'SHIPPING',
  SHIPPING: 'COMPLETED',
}
