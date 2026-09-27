# PPHP

Préprocesseur PHP avec typage statique obligatoire, exécuté à la volée.

## Usage

    php bin/pphp script.pphp [args...]        # compile et exécute
    php bin/pphp --emit script.pphp           # affiche le PHP généré
    php bin/pphp --emit=out.php script.pphp   # écrit le PHP généré

## Statut

Étape 0 : squelette. Le pipeline de compilation est encore un identity pass.