# Politique de sécurité

[English](SECURITY.md) | Français

Signaler les vulnérabilités en privé via [GitHub Security Advisories](https://github.com/PDFToolkit/opentype/security/advisories/new), avec la version, une entrée minimale et les limites configurées.

Les lecteurs reçoivent les octets de l'application. Ils n'ouvrent ni chemin ni URL
et n'exécutent pas le bytecode des fontes. `ReadLimits` borne les octets, tables,
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
capacités d'accès aux points sont fournies explicitement. La bibliothèque
n'exécute ni instructions de fonte, ni scripts, ni moteur externe.

La série `0.2.x` reçoit les corrections de sécurité. PHP 8.3+ sur une plateforme
64 bits est requis ; la CI couvre PHP 8.3, 8.4 et 8.5.
