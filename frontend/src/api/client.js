const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8090'
const TOKEN_KEY = 'pageslibres_token'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token) {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
  } else {
    localStorage.removeItem(TOKEN_KEY)
  }
}

async function request(path, { method = 'GET', body, auth = false } = {}) {
  const headers = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (auth) {
    const token = getToken()
    if (token) headers.Authorization = `Bearer ${token}`
  }

  const response = await fetch(`${API_URL}${path}`, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })

  const text = await response.text()
  const data = text ? JSON.parse(text) : null

  if (!response.ok) {
    const violations = data?.violations?.map((v) => v.message).join(' ')
    const message = violations || data?.detail || data?.message || `Erreur ${response.status}`
    throw new Error(message)
  }

  return data
}

export const api = {
  register: (payload) => request('/api/utilisateurs', { method: 'POST', body: payload }),
  login: (payload) => request('/api/login_check', { method: 'POST', body: payload }),

  listLivres: (query = '') => request(`/api/livres${query}`),
  getLivre: (id) => request(`/api/livres/${id}`),
  createLivre: (payload) => request('/api/livres', { method: 'POST', body: payload, auth: true }),

  listExemplaires: (query = '') => request(`/api/exemplaires${query}`),
  getExemplaire: (id) => request(`/api/exemplaires/${id}`, { auth: true }),
  createExemplaire: (payload) => request('/api/exemplaires', { method: 'POST', body: payload, auth: true }),

  createMouvement: (payload) => request('/api/mouvements', { method: 'POST', body: payload, auth: true }),

  listAvis: (livreId) => request(`/api/avis?livre=${livreId}`),
  createAvis: (payload) => request('/api/avis', { method: 'POST', body: payload, auth: true }),
  listCommentaires: (query = '') => request(`/api/commentaires${query}`),
  createCommentaire: (payload) => request('/api/commentaires', { method: 'POST', body: payload, auth: true }),

  proximite: (lat, lon, rayon = 5000) =>
    request(`/api/exemplaires/proximite?lat=${lat}&lon=${lon}&rayon=${rayon}`, { auth: true }),

  getMoi: () => request('/api/moi', { auth: true }),
  listMesMouvements: (utilisateurIri) => request(`/api/mouvements?utilisateur=${utilisateurIri}`, { auth: true }),
  listMesBadges: (utilisateurIri) => request(`/api/obtention_badges?utilisateur=${utilisateurIri}`, { auth: true }),
}
