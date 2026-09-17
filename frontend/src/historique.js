/**
 * Filtres de l'historique personnel (F9) : vocabulaire partagé entre l'écran et
 * les requêtes, et conversion des bornes de date.
 *
 * `date_mouvement` est un timestamp : « du 16 septembre » doit donc désigner la
 * journée **locale** de l'utilisateur, pas celle de Greenwich. Sinon un
 * mouvement du soir se rangerait la veille, et le filtre contredirait la date
 * affichée juste à côté.
 */

/** Valeurs de l'énumération `TypeMouvement` côté API. */
export const FILTRES_TYPE = [
  { valeur: '', libelle: 'Tout' },
  { valeur: 'liberation', libelle: 'Libérations' },
  { valeur: 'trouvaille', libelle: 'Trouvailles' },
]

/** Minuit local du jour demandé, exprimé en instant UTC. */
function debutDeJourLocal(jour) {
  const [annee, mois, jourDuMois] = jour.split('-').map(Number)
  return new Date(annee, mois - 1, jourDuMois, 0, 0, 0, 0)
}

/**
 * Bornes à envoyer à l'API pour une période de jours locaux.
 *
 * L'intervalle est **semi-ouvert** : `[du 00:00, lendemain du dernier jour[`.
 * Une borne de fin inclusive (`before`) laisserait passer l'instant 00:00:00 du
 * jour suivant ; `strictly_before` l'exclut, ce qui correspond exactement à
 * « jusqu'au 16 septembre inclus ».
 *
 * @returns {{ after?: string, avant?: string }} les instants UTC à transmettre
 */
export function bornesDePeriode({ du = '', au = '' } = {}) {
  const bornes = {}

  if (du) bornes.after = debutDeJourLocal(du).toISOString()
  if (au) {
    const lendemain = debutDeJourLocal(au)
    lendemain.setDate(lendemain.getDate() + 1)
    bornes.avant = lendemain.toISOString()
  }

  return bornes
}
