# Correspondance des types

Cette page décrit comment chaque type PHP est traduit en TypeScript, et comment la nullabilité est rendue.

---

## Sommaire
- [Types scalaires](#types-scalaires)
- [Objets, dates et enums](#objets-dates-et-enums)
- [Tableaux et collections](#tableaux-et-collections)
- [Array shapes et alias PHPStan](#array-shapes-et-alias-phpstan)
- [Unions et nullabilité](#unions-et-nullabilité)
- [Relations Doctrine](#relations-doctrine)
- [Noms de propriétés](#noms-de-propriétés)

---

## Types scalaires

| PHP / PHPDoc | TypeScript |
|---|---|
| `int`, `float`, `positive-int`, `non-negative-int`… | `number` |
| `string`, `non-empty-string`, `class-string` | `string` |
| `bool`, `true`, `false` | `boolean` |
| `mixed` | `any` |
| `object` | `Record<string, any>` |
| `void` / `never` | `void` / `never` |

## Objets, dates et enums

| PHP | TypeScript | Remarque |
|---|---|---|
| `DateTimeInterface`, `DateTime`, `DateTimeImmutable` | `string` | Le serializer les envoie en chaîne (RFC 3339 par défaut). |
| Enum « backed » `string` | `string` | Valeur de l'enum. |
| Enum « backed » `int` | `number` | Valeur de l'enum. |
| Enum pur (`UnitEnum`) | `string` | |
| Autre classe (`App\Entity\Role`) | `Role` | Importé, généré ou décrit inline : voir [Références et imports](references-and-imports.md). |

## Tableaux et collections

| PHP / PHPDoc | TypeScript |
|---|---|
| `array`, `iterable`, `list` (sans type de valeur) | `any[]` |
| `Tag[]`, `array<Tag>`, `list<Tag>`, `array<int, Tag>` | `Array<Tag>` |
| `Collection<int, Tag>`, `ArrayCollection<int, Tag>`, `iterable<Tag>` | `Array<Tag>` |
| `array<string, int>` | `Record<string, number>` |
| `array<int, array<string, Tag>>` | `Array<Record<string, Tag>>` |
| `Collection` sans générique | `any[]` |
| `class-string<Tag>` | `string` |
| Autre classe générique (`Paginator<Tag>`) | `Paginator` |

Une collection Doctrine typée uniquement `Collection` côté PHP n'a pas de type de valeur : ajoutez `@var Collection<int, Tag>` (ou un attribut de relation Doctrine) pour obtenir `Array<Tag>`.

## Array shapes et alias PHPStan

Les array shapes deviennent des types objet :

```php
/** @return array{id: int, label?: string} */
public function getSummary(): array
```

```typescript
summary: { id: number; label?: string };
```

Les alias déclarés sur la classe avec `@phpstan-type` ou `@psalm-type` sont développés :

```php
/**
 * @phpstan-type Item array{id: int, name: string, active?: bool}
 */
class Order
{
    /** @return array<Item> */
    #[Groups(['order:read'])]
    public function getItems(): array { /* … */ }
}
```

```typescript
items: Array<{ id: number; name: string; active?: boolean }>;
```

Les alias sont propres à chaque classe et ne sont jamais réutilisés pour une autre.

## Unions et nullabilité

- Les unions sont conservées : `string|int` → `string | number`.
- `null` n'apparaît jamais dans le type lui-même. Une propriété nullable (`?string`, `string|null`, `@var Tag|null`) est rendue **optionnelle et nullable** :

```typescript
email?: string | null;
```

- Une propriété non nullable est rendue obligatoire : `username: string;`.

## Relations Doctrine

Les attributs `Doctrine\ORM\Mapping` sont reconnus sur les propriétés :

| Attribut | TypeScript | Nullable |
|---|---|---|
| `#[ORM\OneToMany]`, `#[ORM\ManyToMany]` | `Array<Target>` | non |
| `#[ORM\ManyToOne]`, `#[ORM\OneToOne]` | `Target` | oui, sauf exceptions ci-dessous |

Une relation « to-one » n'est **pas** nullable si :

- le type PHP de la propriété est non nullable (`private Category $category;`) ;
- ou la colonne de jointure est déclarée `#[ORM\JoinColumn(nullable: false)]`.

Le côté inverse d'un `OneToOne` (`mappedBy`) est toujours nullable. La cible est lue dans `targetEntity` ou, à défaut, dans le type PHP de la propriété.

```php
#[ORM\ManyToOne(targetEntity: Category::class)]
#[ORM\JoinColumn(nullable: false)]
private ?Category $category = null;

#[ORM\ManyToOne(targetEntity: User::class)]
private ?User $reviewer = null;
```

```typescript
category: Category;
reviewer?: User | null;
```

## Noms de propriétés

Le nom TypeScript est le nom **sérialisé** : `#[SerializedName]`, `NameConverter` (mode `runtime`) ou `serializedName` JMS. Un nom qui n'est pas un identifiant TypeScript valide est mis entre guillemets :

```php
#[SerializedName('published-at')]
private ?\DateTimeImmutable $publishedAt = null;
```

```typescript
'published-at'?: string | null;
```
