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

async function request(path, { method = 'GET', body, auth = false, contentType } = {}) {
  const headers = { Accept: 'application/json' }
  // PATCH exige `application/merge-patch+json` : avec `application/json`,
  // API Platform remplace la ressource au lieu de la fusionner.
  if (body !== undefined) headers['Content-Type'] = contentType ?? 'application/json'
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
    // JWT expiré/invalide sur une requête authentifiée : on déconnecte
    // proprement (au lieu d'afficher l'erreur brute "Expired JWT Token").
    if (response.status === 401 && auth) {
      setToken(null)
      window.dispatchEvent(new CustomEvent('auth-expired'))
    }
    const violations = data?.violations?.map((v) => v.message).join(' ')
    const message = violations || data?.detail || data?.message || `Erreur ${response.status}`
    throw new Error(message)
  }

  return data
}

/**
 * Requête du catalogue : recherche par titre, filtre par catégorie (F8) et
 * pagination. Fonction pure, couverte par client.test.js.
 *
 * La pagination avance par numéro de page, et non en demandant à chaque fois
 * davantage d'éléments : l'API plafonne `itemsPerPage` (30), si bien qu'au-delà
 * elle renvoie moins que demandé — le catalogue s'arrêtait alors en silence, le
 * bouton « Voir plus » disparu et les livres suivants inaccessibles.
 */
export function requeteLivres({ titre = '', categorie = '', limite = null, page = null } = {}) {
  const parametres = new URLSearchParams()
  if (titre) parametres.set('titre', titre)
  if (categorie) parametres.set('categorie', categorie)
  if (limite) parametres.set('itemsPerPage', String(limite))
  if (page && page > 1) parametres.set('page', String(page))
  const requete = parametres.toString()
  return requete ? `?${requete}` : ''
}

export const api = {
  register: (payload) => request('/api/utilisateurs', { method: 'POST', body: payload }),
  login: (payload) => request('/api/login_check', { method: 'POST', body: payload }),

  listLivres: (query = '') => request(`/api/livres${query}`),
  getLivre: (id) => request(`/api/livres/${id}`),
  createLivre: (payload) => request('/api/livres', { method: 'POST', body: payload, auth: true }),

  /**
   * Vocabulaire des catégories, déduit du catalogue (F8).
   *
   * L'API ne propose pas de route « valeurs distinctes » et la réponse JSON des
   * collections ne porte aucune métadonnée de pagination : on parcourt donc les
   * pages jusqu'à en recevoir une incomplète — sinon les catégories présentes
   * au-delà de la première page resteraient invisibles. La taille de page est
   * déduite de la première réponse, l'API pouvant plafonner `itemsPerPage`.
   */
  listCategories: async () => {
    const categories = new Set()
    let taillePage = null

    for (let page = 1; page <= 20; page += 1) {
      const donnees = await request(`/api/livres?itemsPerPage=30&page=${page}`)
      const lot = Array.isArray(donnees) ? donnees : []
      if (taillePage === null) taillePage = lot.length || 1

      lot.forEach((livre) => {
        if (livre.categorie) categories.add(livre.categorie)
      })

      if (lot.length < taillePage) break
    }

    return [...categories].sort((a, b) => a.localeCompare(b, 'fr'))
  },

  listExemplaires: (query = '') => request(`/api/exemplaires${query}`),
  getExemplaire: (id) => request(`/api/exemplaires/${id}`, { auth: true }),
  createExemplaire: (payload) => request('/api/exemplaires', { method: 'POST', body: payload, auth: true }),

  createMouvement: (payload) => request('/api/mouvements', { method: 'POST', body: payload, auth: true }),

  listAvis: (livreId) => request(`/api/avis?livre=${livreId}`),
  createAvis: (payload) => request('/api/avis', { method: 'POST', body: payload, auth: true }),
  listCommentaires: (query = '') => request(`/api/commentaires${query}`),
  createCommentaire: (payload) => request('/api/commentaires', { method: 'POST', body: payload, auth: true }),

  // F4 : proximité. F8 : filtres facultatifs par catégorie et par statut
  // (« en_circulation » = disponible, « trouve » = en lecture).
  proximite: (lat, lon, rayon = 5000, filtres = {}) => {
    const parametres = new URLSearchParams({ lat: String(lat), lon: String(lon), rayon: String(rayon) })
    if (filtres.categorie) parametres.set('categorie', filtres.categorie)
    if (filtres.statut) parametres.set('statut', filtres.statut)

    return request(`/api/exemplaires/proximite?${parametres}`, { auth: true })
  },

  getMoi: () => request('/api/moi', { auth: true }),

  /**
   * Catalogue des badges (F9) : lecture publique, 5 lignes de référence.
   *
   * Le profil n'obtient que les badges déjà mérités : sans ce catalogue, un
   * membre ne peut pas savoir ce qu'il reste à décrocher.
   */
  listBadges: () => request('/api/badges'),

  /** F9 : modification de son propre pseudo (seul champ accepté par l'API). */
  updateProfil: (idUtilisateur, payload) =>
    request(`/api/utilisateurs/${idUtilisateur}`, {
      method: 'PATCH',
      body: payload,
      auth: true,
      contentType: 'application/merge-patch+json',
    }),

  /**
   * F9 : historique personnel.
   *
   * Le tri est demandé à l'API, jamais refait côté client : une page est déjà
   * découpée, donc trier la liste affichée ne trierait que cette page. Le
   * `OrderFilter` remplaçant l'ordre par défaut au lieu de s'y ajouter, les
   * deux critères partent ensemble — sans le second, deux mouvements de même
   * date s'ordonneraient au hasard.
   */
  listMesMouvements: (utilisateurIri, { page = 1, sens = 'desc', taille = 20 } = {}) => {
    const parametres = new URLSearchParams({
      utilisateur: utilisateurIri,
      itemsPerPage: String(taille),
      'order[dateMouvement]': sens,
      'order[idMouvement]': sens,
    })
    if (page > 1) parametres.set('page', String(page))

    return request(`/api/mouvements?${parametres}`, { auth: true })
  },

  listMesBadges: (utilisateurIri) => request(`/api/obtention_badges?utilisateur=${encodeURIComponent(utilisateurIri)}`, { auth: true }),

  // F10 — modération (back-office, admin)
  getAvis: (id) => request(`/api/avis/${id}`),
  getCommentaire: (id) => request(`/api/commentaires/${id}`),
  listSignalements: (query = '') => request(`/api/signalements${query}`, { auth: true }),
  traiterSignalement: (id) => request(`/api/signalements/${id}/traiter`, { method: 'POST', auth: true }),
  rejeterSignalement: (id) => request(`/api/signalements/${id}/rejeter`, { method: 'POST', auth: true }),
}
