import { read, mutate } from './domain.api'
export const getProfile = async () => (await read('profile')).data
export const updateProfile = async (data) => (await mutate('patch', 'profile', data)).data
