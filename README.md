# Codifyo TypeScript Generator Bundle (`ts-generator-bundle`)

Bundle Symfony 7.3+ & 8 permettant de générer automatiquement des fichiers de définitions TypeScript (`.ts`) à partir du **Symfony Serializer**, des **Custom Normalizers**, des **groupes de sérialisation** et de **JMS Serializer**.

---

## 🚀 Fonctionnalités

- **Multi-Projets & Multi-Répertoires** : Configurez plusieurs projets cibles avec des dossiers de sortie distants (ex: `assets/ts/types`, `public/ts/types`).
- **Filtrage par Groupes de Sérialisation** : Génération exacte des propriétés exposées par vos attributs `#[Groups(['user:read'])]`.
- **Formats d'Export Configurables** : Choisissez de générer des `interface` (par défaut), des `class` ou des `type` TypeScript via l'option `kind`.
- **Modes d'Extraction Flexibles (`mode`)** :
  - `runtime` : Exécute la véritable chaîne de normalisation (`$serializer->normalize()`) pour capturer le résultat exact des **Custom Normalizers**, des **`NameConverter`** et des **accesseurs dynamiques / getters calculés**.
    > ⚠️ L'objet est instancié **sans son constructeur** et vos getters / normalizers sont réellement exécutés. Si un normalizer a des effets de bord (requête SQL, appel HTTP…), utilisez le mode `static` pour ce type. Les échecs de normalisation sont journalisés (service `logger`), puis le bundle se replie sur les métadonnées.
  - `static` : Analyse statique via `ClassMetadataFactory` et la réflexion PHP.
  - `jms` : Support des métadonnées `JMS\Serializer`.
  - `auto` *(par défaut)* : Teste d'abord le mode `runtime`, puis bascule automatiquement en `static` / `jms` en cas de fallback.
- **Support des Relations Doctrine ORM** :
  - Interprétation automatique des relations `OneToMany` et `ManyToMany` en `Array<Target>`.
  - Interprétation des `ManyToOne` et `OneToOne` en `Target`, rendu nullable (`target?: Target | null`) sauf si le type PHP est non nullable ou si `#[JoinColumn(nullable: false)]` est déclaré.
  - Détection du typage générique PHPDoc `@var Collection<int, Target>` ou `@var Target[]`.
- **Dépendances & Imports Automatiques** :
  - Génération d'imports relatifs (`import { Role } from './Role';`).
  - Option **`auto_generate_deps: true`** pour découvrir et générer automatiquement toutes les entités référencées.
  - Sans `auto_generate_deps`, les classes référencées sont décrites **inline**, récursivement (`author: { id: number; role?: { id: number; name: string } | null }`).
- **Groupes stricts** : si aucune propriété ne correspond aux groupes demandés, le type généré est vide (comme la sortie du serializer). Le bundle n'expose jamais les propriétés d'autres groupes.
- **Détection des collisions** : deux classes produisant le même nom TypeScript (ex. `App\Entity\Tag` et `App\Blog\Tag`) provoquent une erreur explicite. Déclarez l'une d'elles avec l'option `name`.

---

## 📦 Installation

Installez le bundle via Composer :

```bash
composer require codifyo/ts-generator-bundle
```

Si Symfony Flex n'enregistre pas le bundle automatiquement, ajoutez-le dans `config/bundles.php` :

```php
return [
    // ...
    Codifyo\TsGeneratorBundle\CodifyoTsGeneratorBundle::class => ['all' => true],
];
```

---

## ⚙️ Configuration

Créez le fichier de configuration `config/packages/codifyo_ts_generator.yaml` :

```yaml
codifyo_ts_generator:
    projects:
        frontend:
            dir: 'assets/ts/types'
            mode: 'auto' # 'auto' | 'runtime' | 'static' | 'jms' (optionnel, défaut: 'auto')
            types:
                - class: 'App\Entity\User'
                  groups: ['user:read']
                  kind: 'interface' # 'interface' | 'class' | 'type' (optionnel, défaut: 'interface')
                  auto_generate_deps: true # Découvre et génère automatiquement les entités liées (ex: Role)

                - class: 'App\Entity\Product'
                  groups: ['product:read', 'product:detail']
                  name: 'ProductDTO' # Nom du type TS personnalisé (optionnel)
                  mode: 'runtime' # Surchargé spécifiquement pour ce type
```

---

## 💻 Utilisation

Exécutez la commande console Symfony pour générer les fichiers TypeScript :

```bash
# Générer tous les projets configurés
php bin/console codifyo:ts-generator:generate

# Générer uniquement un projet spécifique
php bin/console codifyo:ts-generator:generate --project=frontend
# ou version courte
php bin/console codifyo:ts-generator:generate -p frontend
```

---

## 📄 Exemple de Rendu

### Entité PHP (`App\Entity\User.php`)

```php
namespace App\Entity;

use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Attribute\Groups;

class User
{
    #[Groups(['user:read'])]
    private int $id;

    #[Groups(['user:read'])]
    private string $username;

    #[Groups(['user:read'])]
    private ?string $email = null;

    /**
     * @var Collection<int, Role>
     */
    #[Groups(['user:read'])]
    private Collection $roles;

    #[Groups(['user:read'])]
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }
}
```

### Fichier TypeScript Généré (`assets/ts/types/User.ts`)

```typescript
/* tslint:disable */
/* eslint-disable */
// Auto-generated by CodifyoTsGeneratorBundle. Do not edit manually.

import { Role } from './Role';

export interface User {
  id: number;
  username: string;
  email?: string | null;
  roles: Array<Role>;
  fullName: string;
}
```

---

## 🧪 Tests

```bash
composer test     # PHPUnit
composer phpstan  # analyse statique (niveau 6)
composer cs       # vérification du style (PHP-CS-Fixer) ; composer cs-fix pour corriger
```

La CI GitHub Actions exécute ces vérifications sur Symfony 7.3 (dépendances minimales), 7.4, 8.0 et 8.x.

---

## 📜 Licence

Ce bundle est distribué sous la licence [MIT](LICENSE).
