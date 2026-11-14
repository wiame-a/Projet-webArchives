import { api } from './client'

export const authApi = {
  login: (data) => api.post('/login', data),
  register: (data) => api.post('/register', data),
  logout: () => api.post('/logout'),
  me: () => api.get('/me'),
}

export const dashboardApi = {
  get: () => api.get('/dashboard'),
}

export const categoriesApi = {
  list: () => api.get('/categories'),
  create: (data) => api.post('/categories', data),
  update: (id, data) => api.put(`/categories/${id}`, data),
  remove: (id) => api.delete(`/categories/${id}`),
}

export const documentsApi = {
  list: (params, signal) => api.get('/documents', { params, signal }),
  get: (id) => api.get(`/documents/${id}`),
  create: (formData, onUploadProgress) =>
    api.post('/documents', formData, { onUploadProgress }),
  update: (id, data) => api.put(`/documents/${id}`, data),
  remove: (id) => api.delete(`/documents/${id}`),
  archive: (id) => api.patch(`/documents/${id}/archive`),
  restore: (id) => api.patch(`/documents/${id}/restore`),
  validate: (id) => api.patch(`/documents/${id}/validate`),
  reject: (id, motif) => api.patch(`/documents/${id}/reject`, { motif }),
  reopen: (id) => api.patch(`/documents/${id}/reopen`),
  addVersion: (id, formData, onUploadProgress) =>
    api.post(`/documents/${id}/versions`, formData, { onUploadProgress }),
  downloadVersion: (documentId, versionId) =>
    api.get(`/documents/${documentId}/versions/${versionId}/download`, { responseType: 'blob' }),
  share: (id, data) => api.post(`/documents/${id}/permissions`, data),
  unshare: (id, userId) => api.delete(`/documents/${id}/permissions/${userId}`),
}

export const profileApi = {
  update: (data) => api.put('/profile', data),
  changePassword: (data) => api.put('/profile/password', data),
}

export const activityApi = {
  list: (params, signal) => api.get('/activity', { params, signal }),
  forDocument: (id) => api.get(`/documents/${id}/activity`),
}

export const usersApi = {
  list: (params) => api.get('/users', { params }),
  lookup: (q, signal) => api.get('/users/lookup', { params: { q }, signal }),
  update: (id, data) => api.put(`/users/${id}`, data),
  remove: (id) => api.delete(`/users/${id}`),
}
