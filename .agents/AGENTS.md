# Règles du Projet

- **Périmètre des modifications** : **Ne jamais** modifier de fichiers en dehors du projet `ts-generator-bundle`.
- **Tagging Git obligatoire** : Il faut absolument créer un tag Git à chaque nouvelle version/développement.
- **Gestion des tags locaux non pushés** : Afin d'éviter d'avoir plusieurs tags non pushés en même temps, si un tag local non pushé existe déjà lors de la réalisation d'un nouveau commit, il faut décaler ce tag existant vers le nouveau commit (`git tag -f <tag_name>`) au lieu d'en recréer un nouveau.
