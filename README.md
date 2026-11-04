# picstosell

Une plaque et quelques photos prises sur le parking deviennent une annonce complète, avec des photos de showroom, publiée sur le site du garage. Garage pilote : Valognes Automobiles.

## Contenu du dépôt

| Dossier | Rôle |
| --- | --- |
| `backend/` | Laravel 13 : API `/api/v1`, site public des garages (`/s/{slug}`), traitement des photos (file Redis) |
| `web/` | Vue 3 : back-office des garages et espace Mending, compilé dans `backend/public/app` et servi sous `/app` |
| `docs/` | Contrat d'API (`API.md`) et maquettes validées (`maquette/`) |
| `Dockerfile`, `docker/` | Image unique utilisée par les services Railway `api` et `worker` |

L'appli mobile (Expo) est dans le dépôt `Mending-fr/picstosell-app`.

## Démarrer en local

```bash
cd backend
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed --seeder=DemoSeeder
php artisan serve
```

Comptes de démo : `admin@demo.test`, `gerant@demo.test`, `photo@demo.test`, mot de passe `password`.
En local, la file tourne en mode `sync` si `QUEUE_CONNECTION=sync`, sinon lancer `php artisan queue:work`.

Interface web : `cd web && npm install && npm run dev` (proxy vers `localhost:8000`).

Tests : `cd backend && php artisan test`.

## Production (Railway, projet `picstosell`, région Europe)

| Service | Rôle | Démarrage |
| --- | --- | --- |
| `api` | API, site public, back-office | `start web` (migrations et seed en pré-déploiement : `start release`) |
| `worker` | Traitement des photos | `start worker` |
| `Postgres`, `Redis` | Base et file | modèles Railway |
| `photos` | Bucket S3 des photos | variables `${{photos.*}}` |

Chaque service est limité à 0,5 vCPU et 0,5 Go.

### Variables à compléter (service `api`, le `worker` les reprend automatiquement)

| Variable | Usage |
| --- | --- |
| `ADMIN_PASSWORD` | Mot de passe du compte super-admin `ADMIN_EMAIL`, créé au prochain déploiement |
| `OPENAI_API_KEY` | GPT Image |
| `GEMINI_API_KEY` | Nano Banana (Gemini) |
| `FAL_KEY` | Seedream (fal) et détourage BiRefNet |
| `PLATE_PROVIDER`, `PLATE_API_KEY` | Données par la plaque. `fake` renvoie des données d'exemple tant que le fournisseur n'est pas branché |
| `VIGIE_SCRIPT` | Balise de mesure d'audience des sites garages |

Les identifiants exacts des modèles se règlent dans l'espace Mending (Modèles IA), sans redéploiement.

## Traitement des photos

- **Méthode B** (production) : détourage (BiRefNet via fal), pose de la voiture dans la photo du showroom selon le réglage de l'angle, harmonisation de l'ombre et de la lumière par le modèle choisi, puis recollage des pixels d'origine de la voiture pour garantir une carrosserie intacte.
- **Méthode A** (comparaison) : le modèle reçoit le décor et la voiture et produit l'image entière.
- Le fournisseur `fake` fait une composition simple sans IA : il permet de tester tout le parcours sans clé.
- Chaque rendu enregistre son fournisseur, son modèle, son coût et sa durée. Le comparatif de l'espace Mending envoie les mêmes photos à plusieurs réglages et calcule le taux de photos publiables.

## Limites connues

- Le floutage automatique de la plaque n'est pas encore fait : il demande un modèle de détection de plaque.
- La recherche par plaque renvoie des données d'exemple (`PLATE_PROVIDER=fake`) en attendant le contrat avec le fournisseur.
- Le texte d'annonce est rédigé à partir des seules données saisies, sans IA pour l'instant.
- Les sites garages sont servis sous `/s/{slug}`. Le branchement d'un domaine propre à chaque garage reste à faire.
