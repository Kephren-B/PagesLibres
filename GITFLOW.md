# Stratégie de branches (GitFlow)

Modèle de gestion de branches appliqué au dépôt **PagesLibres**, conforme à la
stratégie `main`/`develop`/`feature/*` décrite au CDC (Jalon 2) et au cahier des
charges technique (Jalon 5).

## Branches permanentes

| Branche | Rôle |
|---|---|
| `main` | **Uniquement les versions stables** (livrables de jalons), taguées `vX.0`. L'historique direct (first-parent) ne contient que des commits de *release*. |
| `develop` | **Branche d'intégration**. Historique complet du développement ; les fonctionnalités terminées et testées y convergent avant chaque livraison de jalon. |

## Branches temporaires

- `feature/<nom-fonctionnalite>` — **une branche par fonctionnalité**, créée
  depuis `develop`, fusionnée dans `develop` (via PR, `--no-ff`) une fois la
  fonctionnalité terminée et **testée localement**, puis supprimée.

## Cycle de vie d'une fonctionnalité

```bash
# 1. Partir de develop à jour
git checkout develop && git pull
# 2. Créer sa branche feature
git checkout -b feature/mon-fonctionnalite
# 3. Travailler, committer fréquemment et explicitement
git add . && git commit -m "feat: ..."
# 4. Terminer et tester localement (backend + frontend)
APP_ENV=test bin/phpunit   # backend
pnpm test                  # frontend
# 5. Fusionner dans develop via PR (--no-ff) puis supprimer la branche
```

## Publication d'une release (jalon)

```bash
# Depuis develop stabilisé, créer le commit de release sur main
git checkout main && git pull
# (état stable intégré depuis develop)
git tag -a vX.0 -m "Jalon X — ..."
git push origin main --tags
```

## État actuel (jalons livrés)

- `main` : historique court de releases
  - `72e52b6` bootstrap
  - `09d48a3` release **v0.5-jalon5** (tag `v0.5-jalon5`)
  - `5797c65` release **v1.0** (tag `v1.0`, tip de `main`)
- `develop` : historique complet du développement (24 commits)

> Les arborescences des releases de `main` sont **strictement identiques** aux
> états réels des jalons (aucun contenu modifié) : voir les tags
> `backup/pre-rewrite` et `backup/develop-old` pour l'historique d'origine.
