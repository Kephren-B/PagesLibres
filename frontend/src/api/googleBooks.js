const API_KEY = import.meta.env.VITE_GOOGLE_BOOKS_API_KEY

/**
 * F2 : préremplissage du formulaire de création de livre via l'API
 * publique Google Books, appelée directement depuis le navigateur
 * (décision d'architecture actée au Jalon 4 : pas de proxy backend).
 *
 * La clé API, si fournie, est lue depuis une variable d'environnement
 * Vite pour ne pas être committée en dur — mais elle finit de toute
 * façon dans le bundle JS livré au navigateur (côté client, ce n'est
 * jamais un vrai secret). La restreindre par référent HTTP dans la
 * console Google Cloud est la protection réelle, pas son opacité.
 */
export class IsbnNonTrouveError extends Error {}

export async function rechercherParIsbn(isbn) {
  const isbnNettoye = isbn.replace(/[^0-9Xx]/g, '')
  if (!isbnNettoye) {
    throw new IsbnNonTrouveError("ISBN vide ou invalide.")
  }

  const url = new URL('https://www.googleapis.com/books/v1/volumes')
  url.searchParams.set('q', `isbn:${isbnNettoye}`)
  if (API_KEY) url.searchParams.set('key', API_KEY)

  let response
  try {
    response = await fetch(url.toString())
  } catch {
    throw new Error('Impossible de contacter Google Books (réseau indisponible). Saisissez les champs manuellement.')
  }

  if (response.status === 429 || response.status === 403) {
    throw new Error('Quota Google Books dépassé. Réessayez plus tard ou saisissez les champs manuellement.')
  }
  if (!response.ok) {
    throw new Error(`Google Books a répondu une erreur (${response.status}). Saisissez les champs manuellement.`)
  }

  const data = await response.json()
  const item = data.items?.[0]
  if (!item) {
    throw new IsbnNonTrouveError(`Aucun livre trouvé pour l'ISBN ${isbnNettoye}. Saisissez les champs manuellement.`)
  }

  const info = item.volumeInfo ?? {}
  const annee = info.publishedDate ? parseInt(info.publishedDate.slice(0, 4), 10) : null

  return {
    titre: info.title ?? '',
    auteur: info.authors?.join(', ') ?? '',
    anneePublication: Number.isFinite(annee) ? annee : null,
    resume: info.description ?? '',
    couvertureUrl: info.imageLinks?.thumbnail ?? null,
  }
}
