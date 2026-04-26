# Diagramme d'architecture - Quiz Battle

Ce schéma peut être refait sur draw.io, Canva ou à la main sur une page.

```txt
                            ┌──────────────────────┐
                            │      Navigateur       │
                            │ Joueur 1 / Joueur 2   │
                            └──────────┬───────────┘
                                       │
                                       │ HTTP + AJAX
                                       ▼
┌────────────────────────────────────────────────────────────────┐
│                         Serveur XAMPP                           │
│                         Apache + PHP                            │
│                                                                │
│  ┌────────────┐   ┌───────────┐   ┌──────────┐   ┌──────────┐  │
│  │ login.php  │──▶│ room.php  │──▶│ quiz.php │──▶│ score.php│  │
│  └────────────┘   └───────────┘   └──────────┘   └──────────┘  │
│        │                │              │              │         │
│        │                │              │              │         │
│        ▼                ▼              ▼              ▼         │
│  ┌────────────┐   ┌──────────────┐ ┌───────────────────────┐   │
│  │ Sessions   │   │ etat_room.php│ │ etat_quiz.php          │   │
│  │ PHP        │   │ AJAX room    │ │ AJAX état de partie    │   │
│  └────────────┘   └──────────────┘ └───────────────────────┘   │
│                                                                │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ db.php : connexion PDO + fonctions rooms / games         │  │
│  └──────────────────────────────────────────────────────────┘  │
└───────────────────────────────┬────────────────────────────────┘
                                │
                                │ Requêtes SQL
                                ▼
                      ┌──────────────────────┐
                      │ Base MySQL            │
                      │ histoire_quiz         │
                      │                      │
                      │ rooms : joueurs       │
                      │ games : état quiz     │
                      └──────────────────────┘

Fichiers séparés :

assets/css/        styles du site
assets/js/         script musique
assets/audio/      musique
data/questions.json questions du quiz
database.sql       création de la base
```

## Explication courte

- Le navigateur affiche les pages HTML générées par PHP.
- Les sessions PHP gardent le pseudo, l'âge, l'avatar et la room du joueur.
- MySQL garde les rooms et l'état de la partie pour que les deux joueurs voient les mêmes données.
- `etat_room.php` et `etat_quiz.php` sont appelés en AJAX pour actualiser la salle et le quiz sans tout refaire à la main.
- Les fichiers `assets/` et `data/` servent seulement à ranger les ressources du projet.
