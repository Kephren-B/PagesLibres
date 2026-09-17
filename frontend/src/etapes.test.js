import { describe, it, expect } from 'vitest'
import { cadenceEtapes, etiqueterEtapes, mouvementReduit } from './etapes'

describe('etiqueterEtapes', () => {
  const journal = [
    { typeMouvement: 'liberation' },
    { typeMouvement: 'trouvaille' },
    { typeMouvement: 'liberation' },
    { typeMouvement: 'trouvaille' },
    { typeMouvement: 'liberation' },
  ]

  it('numérote les étapes dans l\'ordre et chaque type séparément', () => {
    expect(etiqueterEtapes(journal).map((e) => e.etiquette)).toEqual([
      '1 · L1',
      '2 · T1',
      '3 · L2',
      '4 · T2',
      '5 · L3',
    ])
  })

  it('conserve les champs du mouvement', () => {
    const [premier] = etiqueterEtapes([{ typeMouvement: 'liberation', message: 'Banc du hall' }])
    expect(premier.message).toBe('Banc du hall')
    expect(premier.numeroEtape).toBe(1)
    expect(premier.numeroType).toBe(1)
  })

  it('formule un libellé parlé, sans le code du sigle', () => {
    const [, deuxieme] = etiqueterEtapes(journal)
    expect(deuxieme.libelleParle).toBe('Étape 2, trouvaille n° 1')
  })

  it('ne se casse pas sur un type inconnu', () => {
    const [etape] = etiqueterEtapes([{ typeMouvement: 'transfert' }])
    expect(etape.etiquette).toBe('1')
    expect(etape.libelleParle).toBe('Étape 1')
    expect(etape.numeroType).toBeNull()
  })

  it('accepte un journal vide', () => {
    expect(etiqueterEtapes()).toEqual([])
    expect(etiqueterEtapes([])).toEqual([])
  })
})

describe('cadenceEtapes', () => {
  it('reste sous les 3 secondes pour un long journal', () => {
    const { delai, finTrait } = cadenceEtapes(19)
    expect(delai).toBe(137)
    expect(finTrait).toBe(3406)
  })

  it('plafonne le délai pour les journaux courts', () => {
    // Un journal de trois étapes ne doit pas s'étaler : le délai est borné.
    expect(cadenceEtapes(3).delai).toBe(260)
    expect(cadenceEtapes(1).delai).toBe(260)
  })

  it('ne descend pas sous le plancher sur un journal très long', () => {
    expect(cadenceEtapes(200).delai).toBe(70)
  })

  it('prévoit un effacement postérieur à la dernière apparition', () => {
    const { delai, finTrait } = cadenceEtapes(5)
    expect(finTrait).toBeGreaterThan(4 * delai)
  })
})

describe('mouvementReduit', () => {
  it('renvoie faux quand matchMedia est absent', () => {
    // L'environnement de test n'est pas un navigateur : le garde-fou doit tenir.
    expect(mouvementReduit()).toBe(false)
  })
})
