# Configuration locale pour l'export Joconde

Ce répertoire permet de surcharger la configuration par défaut des templates d'export Joconde.

## Utilisation

Pour personnaliser les templates d'export, créez un fichier `joconde_templates.php` dans ce répertoire.

Ce fichier doit retourner un tableau PHP avec la même structure que le fichier de configuration par défaut (`../joconde_templates.php`).

### Exemple : Surcharge partielle

```php
<?php
/**
 * Configuration locale des templates Joconde
 * Ce fichier surcharge la configuration par défaut
 */

// Charger la configuration par défaut
$defaultConfig = require(__DIR__ . '/../joconde_templates.php');

// Modifier uniquement les champs souhaités
$defaultConfig['templates']['LOCA'] = "^ca_objects.custom_location_field";
$defaultConfig['templates']['STAT'] = "^ca_objects.custom_status_field";

return $defaultConfig;
```

### Exemple : Surcharge complète

```php
<?php
return [
	'headers' => ["REF", "INV", "DOMN", ...],
	'templates' => [
		'REF' => function($vt_object, $museo, $item) {
			return $museo.$item;
		},
		'INV' => "^ca_objects.idno",
		// ... autres champs
	]
];
```

## Structure du fichier de configuration

- **headers** : Tableau des codes de champs Joconde dans l'ordre d'export
- **templates** : Tableau associatif où :
  - La clé est le code du champ (ex: 'REF', 'INV', 'DOMN'...)
  - La valeur peut être :
    - Une chaîne de template CollectiveAccess (ex: `"^ca_objects.idno"`)
    - Une fonction anonyme pour les champs calculés dynamiquement

## Ordre d'application

1. Le système charge d'abord le fichier de configuration par défaut : `conf/joconde_templates.php`
2. Si le fichier `conf/local/joconde_templates.php` existe, il remplace complètement la configuration par défaut
3. Pour une surcharge partielle, chargez la config par défaut puis modifiez-la (voir exemple ci-dessus)

## Variables disponibles dans les fonctions

Les fonctions anonymes reçoivent les paramètres suivants :

- `$vt_object` : L'objet CollectiveAccess courant
- `$museo` : Le code du musée (ex: "M0767")
- `$item` : Le numéro d'item dans l'export
- `$credits` : Les crédits photo
- `$medianame` : Le nom du fichier média

## Débogage

Pour vérifier quelle configuration est chargée, vous pouvez ajouter temporairement dans le contrôleur :

```php
var_dump($jocondeConfig);
exit;
```
