# Modes d'extraction

Le mode détermine **comment** le bundle découvre les propriétés d'une classe. Il se règle par projet (`mode`) et peut être surchargé par type.

---

## Sommaire
- [Choisir un mode](#choisir-un-mode)
- [`auto`](#auto)
- [`runtime`](#runtime)
- [Normaliser un objet réel](#normaliser-un-objet-réel)
- [`static`](#static)
- [`jms`](#jms)
- [Résolution du type des propriétés](#résolution-du-type-des-propriétés)

---

## Choisir un mode

| Mode | Liste des propriétés | Exécute du code applicatif | À utiliser quand |
|---|---|---|---|
| `auto` *(défaut)* | `runtime`, puis `static`, puis `jms` | oui (via `runtime`) | Cas général. |
| `runtime` | sortie réelle de `$serializer->normalize()` | oui | Vous utilisez des custom normalizers, un `NameConverter` ou des getters calculés. |
| `static` | métadonnées du serializer (`#[Groups]`, `#[SerializedName]`, `#[Ignore]`) | non | Vos normalizers ont des effets de bord, ou vous voulez une génération sans surprise. |
| `jms` | métadonnées JMS Serializer | non | Votre API est sérialisée par JMS. |

## `auto`

Les extracteurs `runtime`, `static` puis `jms` sont essayés dans cet ordre. Le premier qui renvoie au moins une propriété l'emporte. Si aucun ne trouve de propriété, le type est généré vide.

## `runtime`

Le bundle passe une instance de la classe au serializer de l'application, avec les groupes demandés. Les clés obtenues sont exactement celles de votre API : custom normalizers, `NameConverter` (ex. `snake_case`) et getters calculés compris.

Par défaut, l'instance est créée **sans appeler son constructeur** (`ReflectionClass::newInstanceWithoutConstructor()`). Ses propriétés sont donc vides. Pour normaliser un vrai objet, rempli, configurez un [`sample_id`](#normaliser-un-objet-réel).

Les attributs qui portent `#[Groups]` mais que la normalisation n'a pas produits (getter qui échoue sur un objet vide, par exemple) sont ajoutés depuis les métadonnées du serializer.

> [!WARNING]
> **Effets de bord**
>
> Vos getters et normalizers sont **réellement exécutés**, sur un objet dont les propriétés ne sont pas initialisées. Un normalizer qui interroge la base ou appelle une API le fera aussi pendant la génération. Pour ces classes, utilisez `mode: static`.

Si la normalisation lève une exception, elle est journalisée en `warning` (service `logger`) et le bundle continue avec les seules métadonnées. Avec la configuration Monolog par défaut en `dev`, ces messages s'affichent dans la console. Sinon, consultez `var/log/dev.log`.

## Normaliser un objet réel

Un objet vide décrit mal ce qu'il ne type pas lui-même : le champ ajouté par un custom normalizer, un getter sans type de retour, un `array` sans PHPDoc. Normalisé, il donne `null` ou `[]`, et le bundle ne peut en déduire que `any` ou `any[]`.

L'option `sample_id` indique l'identifiant d'un **objet réel** à normaliser à la place :

```yaml
codifyo_ts_generator:
    projects:
        frontend:
            dir: 'assets/ts/types'
            types:
                - class: 'App\Entity\Report'
                  groups: ['report:read']
                  sample_id: 42

                # Clé composite
                - class: 'App\Entity\Translation'
                  groups: ['translation:read']
                  sample_id: { locale: 'fr', key: 'home.title' }
```

Les valeurs de l'objet décrivent alors les propriétés qu'aucune autre source ne type :

```php
// Ajouté par un custom normalizer : aucun type PHP
$data['stats'] = ['count' => 3, 'labels' => ['urgent', 'client'], 'ratio' => 0.5];
$data['history'] = [
    ['date' => '2026-01-01', 'value' => 1],
    ['date' => '2026-01-02', 'value' => 2, 'note' => 'relance'],
];
```

```typescript
// Sans sample_id (objet vide : stats vaut null, history vaut [])
stats: any;
history: any[];

// Avec sample_id: 42
stats: { count: number; labels: Array<string>; ratio: number };
history: Array<{ date: string; value: number; note?: string }>;
```

Règles de déduction :

- Les objets d'une liste sont **fusionnés** : une clé absente de certains éléments devient optionnelle (`note?`).
- Une valeur `null` n'apprend rien. Elle ne rend pas le champ nullable, sauf dans une liste qui mélange `null` et d'autres valeurs (`Array<string | null>`).
- Les types déclarés (type PHP, PHPDoc, PropertyInfo, Doctrine) restent prioritaires. Les valeurs ne servent que lorsqu'ils sont absents ou trop vagues (`any`, `any[]`).
- Seuls les types sont générés : aucune valeur de l'objet n'est écrite dans les fichiers `.ts`.

### Chargement de l'objet

Par défaut, l'objet est chargé avec Doctrine (`$entityManager->find($class, $sampleId)`) : `sample_id` accepte un entier, une chaîne (UUID…) ou une map pour une clé composite.

| Situation | Comportement |
|---|---|
| L'objet n'existe pas (base vide en CI, ligne supprimée) | `warning` journalisé, génération avec une instance vide. |
| La base est inaccessible | `warning` journalisé, génération avec une instance vide. |
| DoctrineBundle absent, ou classe non gérée par Doctrine | Erreur : la configuration est incohérente. |
| `sample_id` avec le mode `static` ou `jms` | Erreur : seuls les modes `runtime` et `auto` normalisent un objet. |

`sample_id` ne s'applique qu'au type qui le déclare. Les dépendances découvertes par `auto_generate_deps` utilisent une instance vide : pour leur donner un objet réel, déclarez-les explicitement avec leur propre `sample_id`.

> [!WARNING]
> **Objet réel, code réel**
>
> L'objet est normalisé comme dans votre API : ses relations sont chargées (requêtes SQL) et vos normalizers s'exécutent. Utilisez un environnement de développement, avec des données que vous pouvez lire sans risque.

### Charger les objets autrement

Pour charger les objets depuis une autre source (fixtures, factory, client d'API, objet non Doctrine), implémentez `SampleObjectProviderInterface` et faites-en le service utilisé :

```php
namespace App\TypeScript;

use Codifyo\TsGeneratorBundle\Sample\SampleObjectProviderInterface;

final class FixtureSampleProvider implements SampleObjectProviderInterface
{
    public function find(string $className, int|string|array $id): ?object
    {
        return match ($className) {
            \App\Dto\Dashboard::class => new \App\Dto\Dashboard(widgets: [/* ... */]),
            default => null, // null : objet introuvable, instance vide
        };
    }
}
```

```yaml
# config/services.yaml
services:
    Codifyo\TsGeneratorBundle\Sample\SampleObjectProviderInterface:
        alias: App\TypeScript\FixtureSampleProvider
```

Levez une `\LogicException` pour signaler une erreur de configuration (classe non prise en charge) : elle interrompt la génération au lieu d'être journalisée.

## `static`

Lit les métadonnées du serializer (`ClassMetadataFactory`) sans instancier la classe :

- les attributs `#[Ignore]` sont exclus ;
- les groupes sont appliqués strictement ;
- `#[SerializedName]` donne le nom de la propriété TypeScript.

Si aucune métadonnée de serializer n'est disponible (serializer désactivé), toutes les propriétés de la classe sont listées, puisqu'il n'existe alors aucune information de groupe.

## `jms`

Lit les métadonnées de `jms/serializer` (service `jms_serializer.metadata_factory`, injecté automatiquement si JMSSerializerBundle est installé) :

- les propriétés exclues sont ignorées ;
- sans groupe déclaré, une propriété appartient au groupe `Default`, comme dans JMS ;
- `serializedName` est respecté ;
- les types JMS sont convertis (`array<App\Tag>`, `ArrayCollection<App\Tag>` → `Array<Tag>`).

JMS n'envoie pas les valeurs `null` par défaut (`serialize_null: false`) : toutes les propriétés sont donc générées optionnelles et nullables (`prop?: T | null`).

Sans JMS installé, ce mode renvoie un type vide.

## Résolution du type des propriétés

Une fois la liste des propriétés connue (modes `runtime` et `static`), le type de chacune est cherché dans les sources suivantes, dans l'ordre. La recherche s'arrête à la première source qui donne un type précis (autre que `any`, `any[]` ou une collection non typée) :

1. **Attribut Doctrine** sur la propriété (`OneToMany`, `ManyToMany`, `ManyToOne`, `OneToOne`).
2. **PropertyInfo** (`property_info`), qui exploite les extracteurs configurés dans l'application.
3. **Propriété** : type natif PHP, puis PHPDoc `@var`.
4. **Getter** (`getX()`, `isX()`, `hasX()`, `canX()` ou méthode du même nom) : type de retour natif, puis PHPDoc `@return`.
5. **Valeur normalisée** (mode `runtime` uniquement) : type déduit de la valeur obtenue (`number`, `string`, `boolean`, tableaux et objets décrits récursivement). Avec un [`sample_id`](#normaliser-un-objet-réel), cette valeur vient d'un objet réel.

Sans aucune information, la propriété est typée `any`.

> [!TIP]
> **Génériques PHPDoc**
>
> Les PHPDoc sont toujours lus par le bundle, mais installer `phpstan/phpdoc-parser` permet aussi à PropertyInfo de résoudre les génériques (`Collection<int, Tag>`), ce qui fiabilise l'étape 2.
