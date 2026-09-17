import { useCallback, useEffect, useState } from 'react'

/**
 * Thème clair / sombre (palette de la charte, valeurs inversées).
 *
 * Aucun état global React n'est nécessaire : le thème est porté par l'attribut
 * `data-theme` sur <html>, que lit index.css. Le réglage du système fournit la
 * valeur par défaut, le choix de l'utilisateur la surcharge et se mémorise.
 */

const CLE_STOCKAGE = 'pageslibres.theme'
const REQUETE_SOMBRE = '(prefers-color-scheme: dark)'

/* ---------- Logique pure (couverte par theme.test.js) ---------- */

/** Ne retient qu'une préférence exploitable : 'clair', 'sombre', sinon null. */
export function preferenceValide(valeur) {
  return valeur === 'clair' || valeur === 'sombre' ? valeur : null
}

/** Le choix explicite prime sur le réglage du système. */
export function resoudreTheme(preference, themeSysteme) {
  return preference ?? themeSysteme
}

export function autreTheme(theme) {
  return theme === 'sombre' ? 'clair' : 'sombre'
}

/* ---------- Accès au navigateur ---------- */

function lirePreference() {
  try {
    return preferenceValide(window.localStorage.getItem(CLE_STOCKAGE))
  } catch {
    return null // stockage refusé (navigation privée) : on suivra le système
  }
}

function ecrirePreference(theme) {
  try {
    window.localStorage.setItem(CLE_STOCKAGE, theme)
  } catch {
    /* sans stockage, le choix ne vaudra que pour cette visite */
  }
}

function themeSysteme() {
  return window.matchMedia?.(REQUETE_SOMBRE).matches ? 'sombre' : 'clair'
}

/**
 * Pose le thème sur <html>. index.css s'appuie sur cet attribut pour trancher
 * entre les deux blocs de valeurs (celui du média et celui du choix explicite).
 */
function appliquer(theme) {
  document.documentElement.dataset.theme = theme
}

/**
 * Thème courant + bascule. Tant que l'utilisateur n'a rien choisi, le thème
 * suit le système — y compris ses changements à chaud. Avant le montage de
 * React, c'est le bloc @media de index.css qui donne le bon premier rendu.
 */
export function useTheme() {
  const [preference, setPreference] = useState(lirePreference)
  const [systeme, setSysteme] = useState(themeSysteme)

  const theme = resoudreTheme(preference, systeme)

  useEffect(() => {
    appliquer(theme)
  }, [theme])

  useEffect(() => {
    const requete = window.matchMedia?.(REQUETE_SOMBRE)
    if (!requete) return undefined

    const surChangement = (evenement) => setSysteme(evenement.matches ? 'sombre' : 'clair')
    requete.addEventListener('change', surChangement)
    return () => requete.removeEventListener('change', surChangement)
  }, [])

  const basculer = useCallback(() => {
    setPreference((courante) => {
      const suivante = autreTheme(resoudreTheme(courante, themeSysteme()))
      ecrirePreference(suivante)
      return suivante
    })
  }, [])

  return { theme, basculer }
}
