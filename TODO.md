# TODO

## Features reportées

### `foreach` avec filtre de valeur (`where`)

Syntaxe envisagée :
```php
foreach ($arr as int $n where $n % 2 === 0) { $pairs++; }
foreach ($genders as string $g where $g === "M") { $mcount++; }




### Valeurs par défaut des paramètres en override

Lorsqu'une méthode enfant override une méthode parent, les valeurs par défaut
des paramètres doivent être cohérentes. Règles à définir :
- Si le parent a une valeur par défaut, l'enfant doit aussi en avoir une
  (sinon incohérence d'appel).
- La valeur par défaut de l'enfant doit être "compatible" avec celle du parent
  (par exemple : un sous-type, ou la même valeur exacte).
- Cas particuliers : paramètres optionnels, variadiques, par référence.

Reporté après 4b-ii. Impacte OverrideChecker.


### Narrowing et réassignation dans le bloc

Actuellement, une fois qu'une variable est narrowée dans un bloc,
le narrowing persiste même si la variable est réassignée dans ce bloc.

Exemple :
```php
?int $x = f();
if ($x !== null) {
    $x = null;      // réassignation
    int $y = $x;    // PPHP : $y est int (narrowing persiste)
}