import { describe, expect, it } from 'vitest'
import { bornesDePeriode } from './historique'

/**
 * Les filtres de date de l'historique portent sur `date_mouvement`, un
 * timestamp. « Du 16 septembre » doit donc désigner la journée **locale** de
 * l'utilisateur, pas celle de Greenwich : sinon un mouvement du soir se
 * rangerait la veille. Les tests comparent donc à ce que produit `new Date`
 * en heure locale, ce qui reste juste quel que soit le fuseau de la machine.
 */
describe('bornesDePeriode', () => {
  it('sans borne, ne filtre rien', () => {
    expect(bornesDePeriode({})).toEqual({})
    expect(bornesDePeriode({ du: '', au: '' })).toEqual({})
  })

  it('convertit un jour local en instant UTC', () => {
    const bornes = bornesDePeriode({ du: '2026-09-16' })

    expect(bornes.after).toBe(new Date(2026, 8, 16, 0, 0, 0, 0).toISOString())
  })

  it('exclut le lendemain au lieu d’inclure la fin du jour', () => {
    const bornes = bornesDePeriode({ du: '2026-09-16', au: '2026-09-16' })

    // Intervalle semi-ouvert : [16/09 00:00 locale, 17/09 00:00 locale[.
    // Écrire `before = fin du 16` marcherait aussi, mais une borne inclusive
    // laisse passer l'instant 00:00:00 du jour suivant.
    expect(bornes.after).toBe(new Date(2026, 8, 16).toISOString())
    expect(bornes.avant).toBe(new Date(2026, 8, 17).toISOString())
  })

  it('accepte les deux bornes séparément', () => {
    expect(bornesDePeriode({ au: '2026-06-01' }).avant).toBe(new Date(2026, 5, 2).toISOString())
    expect(bornesDePeriode({ au: '2026-06-01' }).after).toBeUndefined()
  })

  it('franchit correctement une fin de mois et d’année', () => {
    expect(bornesDePeriode({ au: '2026-12-31' }).avant).toBe(new Date(2027, 0, 1).toISOString())
    expect(bornesDePeriode({ au: '2026-02-28' }).avant).toBe(new Date(2026, 2, 1).toISOString())
  })
})
