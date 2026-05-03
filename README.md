# Quiz Battle - Projet Histoire Web

Quiz Battle est un jeu de quiz multijoueur interactif développé en PHP natif (sans framework). Deux joueurs rejoignent une salle d'attente, s'affrontent simultanément sur des séries de questions thématiques, et le premier à gagner 2 manches remporte la partie. 

Ce projet met en avant la synchronisation des clients en quasi temps-réel grâce à des requêtes **AJAX (polling)**, ainsi que la gestion de données complexes via l'utilisation du format **JSON directement dans MySQL**.

## Membres du groupe

- RABI Sara
- LAKHAL Oumaima

## Fonctionnalités

- Connexion avec pseudo, âge et avatar
- Création ou accès à une room avec un code
- Salle d'attente pour deux joueurs
- Quiz en 3 manches maximum
- 8 questions aléatoires par manche
- Timer de 30 secondes par question
- Score partagé entre les deux joueurs
- Page de résultat avec récapitulatif
- Petits effets sonores pendant le quiz
- Nettoyage des anciennes rooms

## Contenu du rendu

- Code source du projet: https://github.com/rabisara9-maker/projet_histoireweb.git
- Fichier `database.sql`
- Diagramme d'architecture : voir `architecture.drawio`
- Vidéo

## Installation

1. Copier le dossier du projet dans :

```txt
C:\xampp\htdocs\
```

2. Lancer XAMPP :

- Apache
- MySQL

3. Ouvrir phpMyAdmin :

```txt
http://localhost/phpmyadmin
```

4. Importer le fichier :

```txt
database.sql
```

5. Lancer le site :

```txt
http://localhost/projet_histoireweb/login.php
```

## Base de données

Le projet utilise une base MySQL appelée :

```txt
histoire_quiz
```

Les identifiants sont dans `includes/db.php` :

```txt
Utilisateur : root
Mot de passe : vide
```

## Organisation des fichiers

```txt
ajax/      scripts asynchrones pour le temps réel
assets/
  css/     feuilles de style
data/
  questions.json
includes/  logique métier et connexion BDD
*.php      pages vues (login, room, quiz...)
database.sql
```

## Remarque

Le projet est prévu pour fonctionner en local avec XAMPP.
