import { describe, it, expect } from 'vitest'
import { requeteLivres } from './client'

describe('requeteLivres', () => {
  it('ne produit aucune requête quand aucun filtre n\'est posé', () => {
    expect(requeteLivres()).toBe('')
    expect(requeteLivres({})).toBe('')
    expect(requeteLivres({ titre: '', categorie: '', limite: null })).toBe('')
  })

  it('encode la recherche par titre', () => {
    expect(requeteLivres({ titre: 'Le Petit Prince' })).toBe('?titre=Le+Petit+Prince')
    expect(requeteLivres({ titre: 'Étranger & Cie' })).toBe('?titre=%C3%89tranger+%26+Cie')
  })

  it('ajoute le filtre de catégorie (F8)', () => {
    expect(requeteLivres({ categorie: 'Bande dessinée' })).toBe('?categorie=Bande+dessin%C3%A9e')
  })

  it('ajoute la pagination du catalogue', () => {
    expect(requeteLivres({ limite: 6 })).toBe('?itemsPerPage=6')
  })

  it('avance par numéro de page plutôt qu\'en grossissant la taille de page', () => {
    // Page 1 = valeur par défaut de l'API, on ne l'écrit pas.
    expect(requeteLivres({ limite: 6, page: 1 })).toBe('?itemsPerPage=6')
    expect(requeteLivres({ limite: 6, page: 3 })).toBe('?itemsPerPage=6&page=3')
  })

  it('combine recherche, catégorie et pagination', () => {
    expect(requeteLivres({ titre: 'Dune', categorie: 'Science-fiction', limite: 12 })).toBe(
      '?titre=Dune&categorie=Science-fiction&itemsPerPage=12',
    )
  })
})
