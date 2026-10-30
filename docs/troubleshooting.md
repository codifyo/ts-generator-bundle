# Dépannage

Les problèmes les plus fréquents, avec leur cause et leur solution.

---

## Le type généré est vide

**Cause** : aucune propriété de la classe n'appartient aux `groups` demandés. Depuis la version 0.2, le bundle n'expose plus toutes les propriétés dans ce cas : le type est vide, comme la sortie réelle du serializer.

**Solution** : vérifiez les noms de groupes (`user:read` ≠ `user_read`) et que les attributs `#[Groups]` sont bien importés depuis `Symfony\Component\Serializer\Attribute\Groups`. Avec `auto_generate_deps`, une dépendance hérite des groupes de son parent : déclarez-la explicitement avec ses propres groupes si besoin.

## Une propriété est typée `any` ou `any[]`

**Cause** : aucune source n'a fourni de type précis (voir [Résolution du type des propriétés](extraction-modes.md#résolution-du-type-des-propriétés)). Cas typiques : propriété sans type natif ni PHPDoc, `array` ou `Collection` sans générique.

**Solution** : ajoutez un type natif ou un PHPDoc (`@var Collection<int, Tag>`, `@return array<Tag>`). Installer `phpstan/phpdoc-parser` améliore aussi la résolution des génériques par PropertyInfo.

## Une classe référencée apparaît comme `Record<string, any>`

**Causes possibles** :

- la classe n'a aucune propriété dans les groupes demandés ;
- c'est une référence circulaire décrite inline (voir [Références circulaires](references-and-imports.md#références-circulaires)) ;
- la classe n'existe pas ou n'est pas chargeable par l'autoloader.

**Solution** : générez la classe (déclaration explicite ou `auto_generate_deps: true`) pour qu'elle soit importée par son nom.

## `both generate the TypeScript type "X"`

Deux classes de namespaces différents ont le même nom court. Donnez un `name` distinct à l'une d'elles (voir [Noms personnalisés et collisions](references-and-imports.md#noms-personnalisés-et-collisions)).

## `is not a valid TypeScript identifier`

L'option `name` contient un caractère interdit (tiret, point, slash…). Utilisez un identifiant comme `UserDTO` ou `User_Read`.

## `Class "…" does not exist.`

Le FQCN déclaré dans `class` est faux, ou la classe n'est pas chargeable par l'autoloader dans l'environnement où la commande tourne. Vérifiez le namespace, et lancez la commande dans l'environnement où le bundle est activé (`dev` par défaut).

## La génération déclenche des requêtes SQL ou des appels HTTP

Le mode `runtime` (utilisé par `auto`) exécute vos normalizers et getters. Passez la classe concernée en `mode: static` (voir [`runtime`](extraction-modes.md#runtime)).

## Une propriété attendue manque en mode `runtime`

La normalisation d'un objet vide a pu échouer : le bundle journalise alors un `warning` et se replie sur les métadonnées. Cherchez « Runtime normalization of » dans la sortie de la commande ou dans `var/log/dev.log`, ou passez la classe en `mode: static`.

## Le mode `jms` ne trouve aucune propriété

JMSSerializerBundle n'est pas installé ou pas activé : le service `jms_serializer.metadata_factory` est alors absent et le mode renvoie un type vide. Vérifiez `config/bundles.php`.

## Un ancien fichier `.ts` est toujours là

Le bundle réécrit les fichiers des types configurés mais ne supprime jamais rien. Supprimez les fichiers des types retirés de la configuration, ou videz le dossier avant la génération.
