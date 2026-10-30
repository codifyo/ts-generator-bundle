# Modes d'extraction

Le mode détermine **comment** le bundle découvre les propriétés d'une classe. Il se règle par projet (`mode`) et peut être surchargé par type.

---

## Sommaire
- [Choisir un mode](#choisir-un-mode)
- [`auto`](#auto)
- [`runtime`](#runtime)
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

Le bundle crée une instance **sans appeler son constructeur** (`ReflectionClass::newInstanceWithoutConstructor()`) et la passe au serializer de l'application avec les groupes demandés. Les clés obtenues sont exactement celles de votre API : custom normalizers, `NameConverter` (ex. `snake_case`) et getters calculés compris.

Les attributs qui portent `#[Groups]` mais que la normalisation n'a pas produits (getter qui échoue sur un objet vide, par exemple) sont ajoutés depuis les métadonnées du serializer.

> [!WARNING]
> **Effets de bord**
>
> Vos getters et normalizers sont **réellement exécutés**, sur un objet dont les propriétés ne sont pas initialisées. Un normalizer qui interroge la base ou appelle une API le fera aussi pendant la génération. Pour ces classes, utilisez `mode: static`.

Si la normalisation lève une exception, elle est journalisée en `warning` (service `logger`) et le bundle continue avec les seules métadonnées. Avec la configuration Monolog par défaut en `dev`, ces messages s'affichent dans la console. Sinon, consultez `var/log/dev.log`.

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
5. **Valeur normalisée** (mode `runtime` uniquement) : `number`, `string`, `boolean`, `any[]` ou `Record<string, any>` selon la valeur obtenue.

Sans aucune information, la propriété est typée `any`.

> [!TIP]
> **Génériques PHPDoc**
>
> Les PHPDoc sont toujours lus par le bundle, mais installer `phpstan/phpdoc-parser` permet aussi à PropertyInfo de résoudre les génériques (`Collection<int, Tag>`), ce qui fiabilise l'étape 2.
