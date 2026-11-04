# API picstosell (v1)

Base : `/api/v1`. JSON partout. Authentification par jeton Bearer (Sanctum) :
`Authorization: Bearer <token>`.

Dossier client (garage) courant :
- un gérant ou un employé travaille toujours sur son garage ;
- un super-admin Mending choisit le garage avec l'en-tête `X-Garage-Id: <id>`.
  Sans en-tête, les routes garage répondent 409 `{"message":"Choisissez un garage"}`.

Erreurs : `422` validation `{message, errors:{champ:[...]}}`, `401`, `403`, `404`, `409`.

Montants : `price` en euros entiers TTC. Coûts IA : `cost_usd` décimal (string, ex. `"0.050"`).
Dates : ISO 8601.

## Valeurs fermées

- `role` : `super_admin`, `manager` (gérant, tout faire), `photographer` (photos seulement)
- `vehicle.status` : `to_photograph`, `processing`, `to_review`, `ready`, `online`, `sold`, `archived`
- `fuel` : `essence`, `diesel`, `hybride`, `hybride_rechargeable`, `electrique`, `gpl`, `autre`
- `gearbox` : `manuelle`, `automatique`
- `shot_key` (angles extérieurs) : `front`, `front_left`, `front_right`, `left`, `right`, `rear`, `rear_left`, `rear_right`
- `photo.kind` : `exterior` (lié à un angle), `interior`, `detail`
- `render.status` : `pending`, `processing`, `to_review`, `approved`, `failed`, `kept_original`
- `render.rating` : `publishable`, `redo`, `unusable` (ou null)
- `lead.type` : `vehicle`, `trade_in`, `other` ; `lead.status` : `new`, `done`
- `ai_config.method` : `A` (génération complète), `B` (détourage puis décor)

## Objets

```jsonc
// User
{"id":1,"name":"Romain","email":"r@x.fr","role":"super_admin","garage_id":null}

// GarageSummary (liste du sélecteur)
{"id":1,"name":"Valognes Automobiles","slug":"valognes-automobiles","status":"pilot","initials":"VA","color":"#1F4FD8","todo_count":5}

// Garage (complet)
{"id":1,"name":"...","slug":"...","status":"pilot","city":"Valognes","phone":"","email":"","address":"",
 "hours":"","about":"","brand_color":"#B8222A","logo_url":null,"domain":null,"vehicle_quota":40,
 "site_url":"https://…/s/valognes-automobiles",
 "settings":{"hide_plate":true,"add_logo":true,"review_before_publish":true,"export_size":"marketplace"},
 "ai_config_id":1}

// Vehicle
{"id":12,"plate":"GH-512-KL","vin":null,"make":"Renault","model":"Clio V","version":"1.0 TCe 90 Evolution",
 "first_registration":"2021-03-01","fuel":"essence","gearbox":"manuelle","power_hp":90,"fiscal_hp":5,
 "color":"Gris","doors":5,"seats":5,"mileage":38200,"price":13490,"description":"…",
 "equipment":[{"group":"Confort","items":["Climatisation"]}],
 "history_url":null,"status":"to_review","days_in_stock":3,"leads_count":0,"photos_count":8,"exterior_photos_count":6,
 "cover_url":"https://…","published_at":null,"sold_at":null,"created_at":"…",
 "photos":[Photo], "renders":[Render]}          // photos/renders seulement sur GET /vehicles/{id}

// Photo
{"id":5,"vehicle_id":12,"shot_key":"front_left","kind":"exterior","url":"https://…","thumb_url":"https://…","position":1}

// Render
{"id":9,"photo_id":5,"vehicle_id":12,"shot_key":"front_left","status":"to_review","url":"https://…",
 "provider":"fake","model":"fake-composite","method":"B","cost_usd":"0.000","duration_ms":1200,
 "error":null,"rating":null,"attempt":1,"ai_config_id":1,"comparison_id":null}

// Shot (angle du showroom)
{"id":3,"showroom_id":1,"key":"front_left","label":"Avant gauche","position":2,"image_url":null,
 "placement":{"scale":66,"bottom":12,"offset_x":0},"configured":false,"overlay_url":"/api/v1/shots/3/overlay"}

// Lead
{"id":1,"type":"vehicle","name":"…","phone":"…","email":null,"message":"…","vehicle":{"id":7,"label":"Renault Zoe","price":14900}|null,
 "trade_in":{"plate":"AB-123-CD","mileage":112000}|null,"status":"new","note":null,"source":"fiche","created_at":"…"}

// AiConfig
{"id":1,"name":"GPT Image 2.5, méthode B","provider":"openai","model":"gpt-image-2.5","method":"B","quality":"2K",
 "prompt":"…","unit_cost_usd":"0.014","active":true}
```

## Routes

### Authentification
- `POST /auth/login` `{email,password,device_name}` → `{token, user}`
- `POST /auth/logout` → 204
- `GET /me` → `{user, garage: Garage|null, garages: GarageSummary[]}` (`garages` : tous pour un super-admin, sinon le sien)

### Accueil
- `GET /dashboard` → `{counts:{to_photograph,processing,to_review,ready,online,sold_month,online_without_leads,sold_still_online}, todo:[{type:"vehicle"|"lead", id, plate, title, detail, status, action}], usage:{vehicles_month, quota}}`
  - `action` : `review`, `publish`, `request_photos`, `unpublish`, `open_lead`

### Véhicules
- `GET /vehicles?status=&q=` → `{data: Vehicle[]}` (`status=to_photograph` inclut `processing`)
- `GET /lookup/plate/{plate}` → `{found:bool, data:{make,model,version,first_registration,fuel,gearbox,power_hp,fiscal_hp,color,doors,seats,vin}}`
- `POST /vehicles` `{plate, ...champs Vehicle, photographer_id?}` → Vehicle
- `GET /vehicles/{id}` → Vehicle avec `photos` et `renders` (dernier rendu par photo)
- `PATCH /vehicles/{id}` champs modifiables → Vehicle
- `DELETE /vehicles/{id}` → 204
- `POST /vehicles/{id}/price` `{price}` → Vehicle (action rapide)
- `POST /vehicles/{id}/sold` → Vehicle (retire aussi l'annonce)
- `POST /vehicles/{id}/publish` `{published:true|false}` → Vehicle
- `POST /vehicles/{id}/history` `{url}` → Vehicle
- `POST /vehicles/{id}/description` → `{description}` (proposition, non enregistrée)
- `POST /vehicles/{id}/uploads` `{files:[{shot_key|null, kind, content_type}]}` → `{uploads:[{path, upload_url, headers}]}` (PUT direct vers le bucket)
- `POST /vehicles/{id}/photos` soit JSON `{path, shot_key, kind}` après un upload signé, soit multipart `file`, `shot_key`, `kind` → Photo
- `DELETE /photos/{id}` → 204
- `POST /vehicles/{id}/renders` → `{data: Render[]}` (lance un rendu par photo extérieure sans rendu actif)
- `GET /vehicles/{id}/export` → archive ZIP des photos validées

### Rendus
- `POST /renders/{id}/approve` → Render
- `POST /renders/{id}/retry` → Render (nouveau rendu)
- `POST /renders/{id}/keep-original` → Render
- `POST /renders/{id}/rate` `{rating}` → Render
- `POST /vehicles/{id}/approve-all` → Vehicle (valide tous les rendus `to_review`, passe en `ready`)

### Showroom
- `GET /showrooms` → `{data:[{id,name,shots:Shot[]}]}`
- `PATCH /shots/{id}` `{placement?, label?}` → Shot
- `POST /shots/{id}/image` multipart `file` → Shot
- `GET /shots/{id}/overlay` → SVG de la silhouette (public, pas d'auth)

### Demandes
- `GET /leads?status=` → `{data: Lead[], new_count}`
- `PATCH /leads/{id}` `{status?, note?}` → Lead

### Site et réglages du garage
- `GET /garage` → Garage ; `PATCH /garage` → Garage ; `POST /garage/logo` multipart `file` → Garage
- `GET /users` → `{data:[User + {invited:bool}]}` ; `POST /users` `{name,email,role}` → User (mot de passe provisoire renvoyé une fois : `{user, temporary_password}`) ; `DELETE /users/{id}`
- `GET /usage` → `{month:"2026-11", vehicles, quota, renders, cost_usd, extras:[]}`

### Espace Mending (super-admin seulement)
- `GET /admin/overview` → `{garages_active, vehicles_month, cost_usd_month, cost_per_vehicle_usd, redo_rate, garages:[{...GarageSummary, vehicles_month, quota, cost_usd, cost_per_vehicle_usd, redo_rate, ai_config_id}], errors:[{at, level, message, ref}]}`
- `POST /admin/garages` `{name, city?, status?}` → Garage ; `PATCH /admin/garages/{id}` `{status?, vehicle_quota?, ai_config_id?}` → Garage
- `GET /admin/ai-configs` → `{data: AiConfig[]}` ; `POST /admin/ai-configs` ; `PATCH /admin/ai-configs/{id}`
- `GET /admin/comparisons` → `{data:[{id,name,status,created_at,ai_config_ids,vehicle_ids}]}`
- `POST /admin/comparisons` `{name, garage_id, ai_config_ids:[], vehicle_ids:[]}` → Comparison (lance les rendus)
- `GET /admin/comparisons/{id}` → `{id,name,status, configs:AiConfig[], vehicles:[{id,plate,label}], summary:[{ai_config_id, rated, publishable, redo, unusable, publishable_pct, cost_per_vehicle_usd, avg_duration_ms}], estimate_usd, grid:{<vehicle_id>:{<shot_key>:{source_url, renders:{<ai_config_id>: Render}}}}}`
- `POST /admin/comparisons/{id}/run` → relance avec les réglages actuels

### Public (sans authentification)
- `GET /public/{slug}/listings` → `{garage:{name,phone,...}, data: Vehicle[] (publiées)}`
- `POST /public/{slug}/leads` `{type,name,phone,email?,message?,vehicle_id?,trade_in_plate?,trade_in_mileage?,wants_trade_in?}` → 201
