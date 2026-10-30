# Configuration

Toute la configuration se trouve sous la clé `codifyo_ts_generator`. Un fichier peut déclarer plusieurs **projets**, chacun avec son dossier de sortie et sa liste de types.

---

## Sommaire
- [Exemple complet](#exemple-complet)
- [Options du projet](#options-du-projet)
- [Options des types](#options-des-types)
- [Syntaxe alternative](#syntaxe-alternative)
- [Commande](#commande)
- [Fichiers générés](#fichiers-générés)

---

## Exemple complet

```yaml
# config/packages/dev/codifyo_ts_generator.yaml
codifyo_ts_generator:
    projects:
        frontend:
            dir: 'assets/ts/types'
            mode: 'auto'
            types:
                - class: 'App\Entity\User'
                  groups: ['user:read']
                  auto_generate_deps: true

                - class: 'App\Entity\Product'
                  groups: ['product:read', 'product:detail']
                  name: 'ProductDTO'
                  kind: 'type'
                  mode: 'static'

        admin:
            dir: '/var/www/admin-front/src/types'
            types:
                - class: 'App\Entity\User'
                  groups: ['user:read', 'admin']
                  kind: 'class'
```

## Options du projet

| Option | Type | Défaut | Description |
|---|---|---|---|
| `dir` | `string` | *requis* | Dossier de sortie. Un chemin relatif part de la racine du projet Symfony (`%kernel.project_dir%`), un chemin absolu (`/…`, `C:\…`) est utilisé tel quel. Le dossier est créé s'il n'existe pas. |
| `mode` | `auto` \| `runtime` \| `static` \| `jms` | `auto` | Mode d'extraction par défaut des types du projet. Voir [Modes d'extraction](extraction-modes.md). |
| `types` | liste | *requis* (au moins un) | Types à générer. |

## Options des types

| Option | Type | Défaut | Description |
|---|---|---|---|
| `class` | `string` | *requis* | FQCN de la classe PHP. |
| `groups` | `string[]` | `[]` | Groupes de sérialisation. Vide : toutes les propriétés connues du serializer. Si aucune propriété ne correspond, le type généré est **vide**. |
| `name` | `string` | nom court de la classe | Nom du type TypeScript, qui sert aussi de nom de fichier (`<name>.ts`). Doit être un identifiant TypeScript valide (`[A-Za-z_$][A-Za-z0-9_$]*`). |
| `kind` | `interface` \| `class` \| `type` | `interface` | Forme générée : `export interface X {…}`, `export class X {…}` ou `export type X = {…};`. |
| `mode` | `auto` \| `runtime` \| `static` \| `jms` | mode du projet | Surcharge le mode du projet pour ce type. |
| `auto_generate_deps` | `bool` | `false` | Génère aussi un fichier pour chaque classe référencée, récursivement. Sinon, les classes référencées sont décrites inline. Voir [Références et imports](references-and-imports.md). |

> [!TIP]
> **`kind: class`**
>
> Les classes générées déclarent leurs propriétés sans initialiseur. Avec `strictPropertyInitialization` activé dans `tsconfig.json`, préférez `interface` ou `type`.

## Syntaxe alternative

Les projets peuvent aussi être déclarés directement sous la clé racine, et un projet peut être écrit comme une liste de fragments fusionnés :

```yaml
codifyo_ts_generator:
    frontend:
        - dir: 'assets/ts/types'
        - types:
            - class: 'App\Entity\User'
              groups: ['user:read']
```

Cette forme est normalisée vers la syntaxe `projects:` décrite plus haut.

## Commande

```bash
# Tous les projets
php bin/console codifyo:ts-generator:generate

# Un seul projet
php bin/console codifyo:ts-generator:generate --project=frontend
php bin/console codifyo:ts-generator:generate -p frontend

# Afficher la trace complète en cas d'erreur
php bin/console codifyo:ts-generator:generate -v
```

La commande liste les fichiers écrits par projet et renvoie un code de sortie non nul en cas d'erreur (classe introuvable, collision de noms, projet inconnu…), ce qui permet de l'utiliser en CI.

> [!TIP]
> **Vérifier en CI que les types sont à jour**
>
> Lancez la commande puis `git diff --exit-code assets/ts/types` : la CI échoue si quelqu'un a modifié une entité sans régénérer les types.

## Fichiers générés

- Un fichier `<name>.ts` par type, dans le dossier `dir` du projet.
- Chaque fichier commence par un en-tête `/* tslint:disable */`, `/* eslint-disable */` et un commentaire « Auto-generated ». Ne les modifiez pas à la main : ils sont réécrits à chaque génération.
- Les imports sont relatifs (`import { Role } from './Role';`) et triés par ordre alphabétique.
- Les fichiers d'un type retiré de la configuration **ne sont pas supprimés** : supprimez-les vous-même, ou videz le dossier avant de générer.
