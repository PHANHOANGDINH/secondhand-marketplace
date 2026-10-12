import { read, mutate } from './domain.api'
export const listConversations = (params) => read('conversations', params)
export const startConversation = async (productId) =>
  (await mutate('post', 'conversations', { product_id: productId })).data
export const listMessages = (id, params) => read(`conversations/${id}/messages`, params)
export const sendMessage = async (id, body) =>
  (await mutate('post', `conversations/${id}/messages`, { body })).data
export const markConversationRead = async (id, lastMessageId) =>
  (await mutate('post', `conversations/${id}/read`, { last_message_id: lastMessageId })).data
