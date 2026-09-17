import { describe, it, expect } from 'vitest'
import {
  distanceKm,
  etoiles,
  formatJours,
  formatKm,
  joursDepuis,
  mouvementDeReference,
  nombreTrouvailles,
  noteMoyenne,
} from './voyage'

const PARIS = { lat: 48.8566, lon: 2.3522 }
const LYON = { lat: 45.764, lon: 4.8357 }

describe('distanceKm', () => {
  it('vaut zéro sur un point unique ou des points confondus', () => {
    expect(distanceKm([])).toBe(0)
    expect(distanceKm([PARIS])).toBe(0)
    // Cas réel : un livre relibéré au même endroit (Luxembourg, dans le jeu).
    expect(distanceKm([PARIS, PARIS])).toBe(0)
  })

  it('donne un degré de latitude pour 111,19 km', () => {
    // Repère vérifiable sans dépendre d'un service : 1° de latitude ≈ 111,19 km.
    const mesure = distanceKm([{ lat: 48, lon: 2 }, { lat: 49, lon: 2 }])
    expect(mesure).toBeGreaterThan(111)
    expect(mesure).toBeLessThan(111.3)
  })

  it('donne environ 392 km entre Paris et Lyon', () => {
    const mesure = distanceKm([PARIS, LYON])
    expect(mesure).toBeGreaterThan(385)
    expect(mesure).toBeLessThan(400)
  })

  it('additionne les segments dans l’ordre reçu', () => {
    const parisLyon = distanceKm([PARIS, LYON])
    const parisLyonParis = distanceKm([PARIS, LYON, PARIS])
    expect(parisLyonParis).toBeCloseTo(parisLyon * 2, 1)
  })
})

describe('noteMoyenne et etoiles', () => {
  it('renvoie null sans avis', () => {
    expect(noteMoyenne([])).toBeNull()
    expect(etoiles(noteMoyenne([])).pleines).toBe(0)
  })

  it('fait la moyenne des notes', () => {
    expect(noteMoyenne([{ note: 5 }, { note: 4 }, { note: 4 }])).toBeCloseTo(4.333, 2)
  })

  it('arrondit à l’étoile, comme la maquette', () => {
    expect(etoiles(4.3)).toEqual({ pleines: 4, vides: 1 })
    expect(etoiles(4.6)).toEqual({ pleines: 5, vides: 0 })
    expect(etoiles(5)).toEqual({ pleines: 5, vides: 0 })
  })
})

describe('joursDepuis et formatJours', () => {
  it('compte les jours entiers', () => {
    const maintenant = new Date('2026-03-22T12:00:00+02:00')
    expect(joursDepuis('2026-03-12T12:00:00+02:00', maintenant)).toBe(10)
    expect(joursDepuis('2026-03-22T06:00:00+02:00', maintenant)).toBe(0)
    expect(joursDepuis(null, maintenant)).toBeNull()
  })

  it('met les jours au singulier et gère le jour même', () => {
    expect(formatJours(10)).toBe('10 jours')
    expect(formatJours(1)).toBe('1 jour')
    expect(formatJours(0)).toBe("aujourd'hui")
    expect(formatJours(null)).toBe('—')
  })
})

describe('mouvementDeReference', () => {
  const journal = [
    { typeMouvement: 'liberation', dateMouvement: '2026-03-12T10:00:00+01:00' },
    { typeMouvement: 'trouvaille', dateMouvement: '2026-03-15T10:00:00+01:00' },
    { typeMouvement: 'liberation', dateMouvement: '2026-03-22T10:00:00+01:00' },
  ]

  it('retient la dernière libération pour un exemplaire en circulation', () => {
    expect(mouvementDeReference(journal, 'en_circulation')?.dateMouvement).toBe('2026-03-22T10:00:00+01:00')
  })

  it('retient la dernière trouvaille pour un exemplaire en lecture', () => {
    expect(mouvementDeReference(journal, 'trouve')?.dateMouvement).toBe('2026-03-15T10:00:00+01:00')
  })

  it('ne casse pas sur un journal vide', () => {
    expect(mouvementDeReference([], 'en_circulation')).toBeNull()
  })

  it('compte les trouvailles', () => {
    expect(nombreTrouvailles(journal)).toBe(1)
    expect(nombreTrouvailles([])).toBe(0)
  })
})

describe('formatKm', () => {
  it('écrit la distance à la française, avec une décimale', () => {
    expect(formatKm(7.42)).toBe('7,4 km')
    expect(formatKm(0)).toBe('0,0 km')
    expect(formatKm(null)).toBe('0,0 km')
  })
})
