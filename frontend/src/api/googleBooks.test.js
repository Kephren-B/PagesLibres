import { describe, it, expect, vi, afterEach } from 'vitest'
import { rechercherParIsbn, IsbnNonTrouveError, urlCouverture } from './googleBooks'

describe('urlCouverture', () => {
  it('ne renvoie rien quand il n\'y a pas de couverture', () => {
    expect(urlCouverture(null)).toBeNull()
    expect(urlCouverture(undefined)).toBeNull()
    expect(urlCouverture('')).toBeNull()
    expect(urlCouverture('   ')).toBeNull()
  })

  it('passe en HTTPS (contenu mixte et CSP de production)', () => {
    expect(urlCouverture('http://books.google.com/books/content?id=X&printsec=frontcover')).toBe(
      'https://books.google.com/books/content?id=X&printsec=frontcover',
    )
  })

  it('demande une vignette deux fois plus grande que zoom=1', () => {
    expect(urlCouverture('https://books.google.com/books/content?id=X&zoom=1')).toBe(
      'https://books.google.com/books/content?id=X&zoom=2',
    )
    expect(urlCouverture('https://books.google.com/books/content?zoom=1')).toBe(
      'https://books.google.com/books/content?zoom=2',
    )
  })

  it('laisse intactes les autres sources et les autres valeurs de zoom', () => {
    const openLibrary = 'https://covers.openlibrary.org/b/isbn/9782070612758-M.jpg'
    expect(urlCouverture(openLibrary)).toBe(openLibrary)
    expect(urlCouverture('https://books.google.com/books/content?id=X&zoom=2')).toBe(
      'https://books.google.com/books/content?id=X&zoom=2',
    )
    expect(urlCouverture('https://books.google.com/books/content?id=X&zoom=10')).toBe(
      'https://books.google.com/books/content?id=X&zoom=10',
    )
  })
})

const REPONSE_GATSBY = {
  items: [
    {
      volumeInfo: {
        title: 'The Great Gatsby',
        authors: ['F. Scott Fitzgerald'],
        publishedDate: '2004-09-30',
        description: "Un classique de la littérature américaine.",
        imageLinks: { thumbnail: 'http://books.google.com/gatsby-thumb.jpg' },
      },
    },
  ],
}

describe('rechercherParIsbn', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('préremplit les champs à partir d\'un ISBN valide', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => REPONSE_GATSBY,
    }))

    const resultat = await rechercherParIsbn('9780743273565')

    expect(resultat).toEqual({
      titre: 'The Great Gatsby',
      auteur: 'F. Scott Fitzgerald',
      anneePublication: 2004,
      resume: 'Un classique de la littérature américaine.',
      couvertureUrl: 'http://books.google.com/gatsby-thumb.jpg',
    })

    const urlAppelee = fetch.mock.calls[0][0]
    expect(urlAppelee).toContain('isbn%3A9780743273565')
  })

  it('lève IsbnNonTrouveError quand Google Books ne renvoie aucun résultat', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ totalItems: 0 }),
    }))

    await expect(rechercherParIsbn('0000000000000')).rejects.toBeInstanceOf(IsbnNonTrouveError)
  })

  it('renvoie un message clair sur quota dépassé (429)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: false,
      status: 429,
      json: async () => ({}),
    }))

    await expect(rechercherParIsbn('9780743273565')).rejects.toThrow(/quota/i)
  })

  it('renvoie un message clair sur erreur réseau', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('network error')))

    await expect(rechercherParIsbn('9780743273565')).rejects.toThrow(/réseau/i)
  })
})
