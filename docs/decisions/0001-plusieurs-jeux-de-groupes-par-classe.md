# 0001 — Une même classe sous plusieurs jeux de groupes

Statut : accepté (2026-11-14, issue #2)

## Contexte
Le générateur indexait les déclarations par classe seule : une seconde déclaration (autres groupes, autre nom) était ignorée en silence.

## Décision
- Une déclaration est identifiée par classe + groupes triés + nom ; les doublons exacts sont fusionnés, deux noms identiques lèvent une erreur de collision.
- Une référence vers une classe générée choisit : la déclaration aux mêmes groupes que le parent, sinon la déclaration unique de la classe, sinon une description inline avec les groupes du parent.
- `auto_generate_deps` : une dépendance déjà auto-générée avec d'autres groupes donne un nouveau type nommé `<NomCourt><Groupes en PascalCase>` (le premier découvert garde le nom court).

## Conséquences
- Le nom dépend de l'ordre de découverte ; pour le maîtriser, déclarer la dépendance explicitement.
- Suffixe vide (groupes sans caractère alphanumérique, ou `[]`) ou identique (`user:admin` / `user_admin`) : erreur de collision explicite.
