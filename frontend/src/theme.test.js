import { describe, it, expect } from 'vitest'
import { preferenceValide, resoudreTheme, autreTheme } from './theme'

describe('preferenceValide', () => {
  it('ne retient que les deux thèmes connus', () => {
    expect(preferenceValide('clair')).toBe('clair')
    expect(preferenceValide('sombre')).toBe('sombre')
  })

  it('écarte tout le reste (aucune préférence, stockage altéré)', () => {
    expect(preferenceValide(null)).toBeNull()
    expect(preferenceValide('')).toBeNull()
    expect(preferenceValide('auto')).toBeNull()
    expect(preferenceValide('Sombre')).toBeNull()
  })
})

describe('resoudreTheme', () => {
  it('suit le système tant que rien n\'a été choisi', () => {
    expect(resoudreTheme(null, 'sombre')).toBe('sombre')
    expect(resoudreTheme(null, 'clair')).toBe('clair')
  })

  it('donne la priorité au choix explicite, même contraire au système', () => {
    expect(resoudreTheme('clair', 'sombre')).toBe('clair')
    expect(resoudreTheme('sombre', 'clair')).toBe('sombre')
  })
})

describe('autreTheme', () => {
  it('inverse le thème courant', () => {
    expect(autreTheme('clair')).toBe('sombre')
    expect(autreTheme('sombre')).toBe('clair')
  })
})
