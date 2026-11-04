# Thèmes du site public

Chaque dossier ici est un thème. Il contient un `theme.json` et des vues Blade.

```
themes/
  classique/   thème de base, toutes les pages
  prestige/    thème haut de gamme, s'appuie sur classique
  mon-garage/  thème sur mesure pour un garage (exemple)
```

## theme.json

```json
{
  "name": "Prestige",
  "description": "Une phrase qui s'affiche dans l'espace Mending.",
  "extends": "classique",
  "garages": [],
  "options": [
    { "key": "hero_title", "label": "Grand titre", "type": "text", "default": "", "help": "Laisser vide pour le texte par défaut." }
  ]
}
```

- `extends` : thème parent. Une page absente du thème est prise chez le parent (puis chez `classique`). Un thème sur mesure peut donc ne réécrire que `layout` ou `index`.
- `garages` : liste de slugs. Vide, le thème est proposé à tous les garages. Rempli, il n'apparaît que pour ces garages : c'est comme ça qu'on fait un thème réservé à un client.
- `options` : réglages affichés dans l'espace Mending pour le garage. Types : `text`, `textarea`, `number`, `toggle`. Les valeurs sont rangées dans `garages.settings.theme_options.<thème>.<clé>`.

## Dans les vues

- `$theme->view('page')` donne la vue à utiliser, avec repli sur le parent.
- `$theme->opt('cle')` lit une option (valeur du garage, sinon valeur par défaut).
- `$g` est le garage. Le thème actif est dans `settings.site_theme`.

## Aperçu

L'espace Mending ouvre une adresse signée valable une heure qui montre le site avec un autre thème, sans rien changer pour les visiteurs.
