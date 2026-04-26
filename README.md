# Quiz Battle - Projet Histoire Web

Quiz Battle est un petit jeu de quiz en PHP. Deux joueurs rejoignent une room, répondent aux mêmes questions et gagnent des manches. Le premier joueur qui gagne 2 manches remporte la partie.

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
- Musique et petits effets sonores
- Nettoyage des anciennes rooms

## Contenu du rendu

- Code source du projet (apres)
- Fichier `database.sql`
- Diagramme d'architecture : voir `architecture.drawio`
- Vidéo(apres)

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

Les identifiants sont dans `db.php` :

```txt
Utilisateur : root
Mot de passe : vide
```

## Organisation des fichiers

```txt
assets/
  audio/   musique du jeu
  css/     feuilles de style
  js/      scripts communs
data/
  questions.json
*.php      pages et traitements du jeu
database.sql
```

## Remarque

Le projet est prévu pour fonctionner en local avec XAMPP.
