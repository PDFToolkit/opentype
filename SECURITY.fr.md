# Politique de sécurité

[English](SECURITY.md) | Français

Signaler les vulnérabilités en privé via [GitHub Security Advisories](https://github.com/PDFToolkit/opentype/security/advisories/new), avec la version, une entrée minimale et les limites configurées.

Les lecteurs reçoivent les octets de l'application. Ils n'ouvrent ni chemin ni URL
et n'activent aucune exécution implicite. `ReadLimits` borne les octets, tables,
enregistrements de layout, glyphes, opérations, profondeur des lookups et résultats. Les vues
binaires bornent les lectures ; les lecteurs vérifient indices, ordre et
références aux glyphes. Les mécanismes sélectionnés non pris en charge échouent.

Les tests couvrent les entrées tronquées et malformées, offsets et indices
invalides, erreurs de somme de contrôle et limites d'expansion. Des contrôles
indépendants comparent les résultats pris en charge avec fontTools et HarfBuzz.
Ils portent sur les lecteurs implémentés ; ce package n'assainit pas toutes les
tables de fonte. L'application contrôle l'accès aux fichiers et les quotas de
durée et de mémoire.

Chaque exécution GSUB/GPOS possède ses buffers et graphes d'attachement. Les
lookups récursifs consomment des budgets explicites de profondeur et d'opérations.
Les instances GDEF précalculent leurs deltas natifs ; l'échelle Device et les
capacités d'accès aux points sont fournies explicitement. L'assemblage TrueType
et l'exécution Type 2 CFF/CFF2 utilisent `OutlineLimits` pour borner enregistrements,
octets, opérations, profondeur, programmes et points développés. Les cycles de
composants/sous-programmes sont refusés ; l'entrée aléatoire CFF est explicite.
La bibliothèque n'exécute pas les instructions TrueType, n'applique pas les hints
d'un rastériseur, ne lance pas de script/moteur externe et ne décompresse pas
les conteneurs de fontes web.

La série `0.3.x` reçoit les corrections de sécurité. PHP 8.3+ sur une plateforme
64 bits est requis ; la CI couvre PHP 8.3, 8.4 et 8.5.
