# Références et imports

Quand une propriété référence une autre classe (`?Role $role`, `Collection<int, Tag>`), le bundle choisit entre trois rendus : **importer** le type généré, le **décrire inline**, ou le remplacer par un type opaque.

---

## Sommaire
- [Import des classes générées](#import-des-classes-générées)
- [Génération automatique des dépendances](#génération-automatique-des-dépendances)
- [Description inline](#description-inline)
- [Références circulaires](#références-circulaires)
- [Noms personnalisés et collisions](#noms-personnalisés-et-collisions)

---

## Import des classes générées

Si la classe référencée est générée dans le **même projet** (déclarée dans `types`, ou découverte par `auto_generate_deps`), elle est importée sous son nom TypeScript :

```typescript
import { Role } from './Role';

export interface User {
  role?: Role | null;
}
```

Si ce type a un `name` personnalisé, c'est ce nom qui est utilisé, y compris dans les unions et les génériques :

```yaml
types:
    - class: 'App\Entity\Article'
      groups: ['article:read']
    - class: 'App\Entity\Tag'
      groups: ['article:read']
      name: 'TagDTO'
```

```typescript
import { TagDTO } from './TagDTO';

export interface Article {
  mainTag?: TagDTO | null;
  tags: Array<TagDTO>;
}
```

Une classe qui se référence elle-même (arbre, parent/enfant) utilise son propre nom, sans import :

```typescript
export interface Category {
  name: string;
  parent?: Category | null;
}
```

## Génération automatique des dépendances

Avec `auto_generate_deps: true`, chaque classe référencée qui n'est pas déjà déclarée devient un type généré, et ainsi de suite récursivement. Les types découverts héritent du type qui les a fait découvrir :

- des mêmes `groups` ;
- du même `kind` ;
- du même `mode` ;
- de `auto_generate_deps: true`.

Pour donner des options différentes à une dépendance (autres groupes, nom personnalisé), déclarez-la explicitement dans `types` : la déclaration explicite est prioritaire.

Les classes `DateTimeInterface`, les enums et les collections ne sont jamais générées en fichier : elles sont converties directement (voir [Correspondance des types](type-mapping.md)).

## Description inline

Sans `auto_generate_deps`, une classe référencée qui n'est pas générée est **décrite inline**, avec les mêmes groupes et le même mode que le type parent. La description est récursive :

```typescript
export interface Comment {
  id: number;
  author?: { id: number; username: string; role?: { id: number; name: string } | null } | null;
}
```

Si une classe inline référence un type généré, l'import correspondant est ajouté au fichier.

Une classe inline sans aucune propriété dans les groupes demandés est rendue `Record<string, any>`.

## Références circulaires

Pour éviter une description infinie, une classe déjà en cours de description inline est remplacée par `Record<string, any>` lorsqu'elle réapparaît plus bas. Pour obtenir un vrai type récursif, générez la classe (déclaration explicite ou `auto_generate_deps`) : elle sera alors référencée par son nom.

## Noms personnalisés et collisions

Le nom TypeScript d'un type est son option `name` ou, à défaut, le nom court de la classe. Deux classes ne peuvent pas produire le même nom dans un projet, car elles écriraient le même fichier. La comparaison ignore la casse, pour les systèmes de fichiers qui ne la distinguent pas.

```
Classes "App\Entity\Tag" and "App\Blog\Tag" both generate the TypeScript type "Tag".
Declare one of them explicitly with a distinct "name" option.
```

Solution : déclarer l'une des deux classes avec un nom distinct.

```yaml
types:
    - class: 'App\Entity\Tag'
    - class: 'App\Blog\Tag'
      name: 'BlogTag'
```

Le nom doit être un identifiant TypeScript valide. Un nom comme `my-type` ou `../types/User` est refusé avant toute écriture de fichier.
