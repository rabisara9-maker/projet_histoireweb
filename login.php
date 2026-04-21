<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!empty($_POST['username'])) {
        $_SESSION['username'] = $_POST['username']; 
        $_SESSION['avatar'] = $_POST['avatar'];  
        $_SESSION['age'] = $_POST['age'] ?? null;
        $_SESSION['language'] = $_POST['language'] ?? 'fr';

        $roomId = null;
        if (!empty($_POST['room_code'])) {
            // Rejoindre une room spécifique
            $roomId = intval($_POST['room_code']); // Assuming room_code is numeric
        } else {
            // Créer ou trouver une room disponible
            $roomId = trouverRoomDisponible();
        }
        $_SESSION['room_id'] = $roomId;

        header("Location: room.php");
        exit();
    }
}

function trouverRoomDisponible() {
    $roomId = 1;
    while (true) {
        $sharedRoomFile = "shared_room_{$roomId}.json";
        if (!file_exists($sharedRoomFile)) {
            // Créer une nouvelle room vide
            $room = ['joueur1' => null, 'joueur2' => null, 'avatar1' => null, 'avatar2' => null];
            file_put_contents($sharedRoomFile, json_encode($room));
            return $roomId;
        } else {
            $room = json_decode(file_get_contents($sharedRoomFile), true);
            if (!$room['joueur1'] || !$room['joueur2']) {
                // Room disponible
                return $roomId;
            }
        }
        $roomId++;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Quiz Battle - Accueil</title>
  <link rel="stylesheet" href="style.css" />
</head>

<body>
  <div class="page">
    <div class="card">
      <header class="card-header">
        <h1>Quiz Battle</h1>
        <p>
          Créez une room ou rejoignez une partie pour affronter un autre joueur
          sur 3 quiz.
        </p>
      </header>

      <form class="form" action="login.php" method="POST">
        <div class="form-group">
          <label for="player-name">Nom du joueur</label>
          <input type="text" id="player-name" name="username" placeholder="Entrez votre nom" required />
        </div>

        <div class="form-group">
          <label for="player-age">Âge</label>
          <input type="number" id="player-age" name="age" placeholder="Entrez votre âge" min="10" max="100" />
        </div>

        <div class="form-group">
          <label for="language">Langue</label>
          <select id="language" name="language">
            <option value="">Choisir une langue</option>
            <option value="fr">Français</option>
            <option value="en">English</option>
            <option value="ar">العربية</option>
          </select>
        </div>

        <div class="form-group">
          <label for="room-code">Code de la room</label>
          <input type="text" id="room-code" name="room_code" placeholder="Exemple : ROOM123" />
        </div>

        <div class="form-group">
          <label for="avatar">Avatar</label>
          <input type="file" id="avatar" name="avatar" accept="image/*" />
        </div>

        <div class="button-group">
          <button type="submit" name="action" value="create" class="btn btn-primary">Créer une room</button>
          <button type="submit" name="action" value="join" class="btn btn-secondary">Rejoindre une room</button>
        </div>
      </form>

      <section class="rules">
        <h2>Règles du jeu</h2>
        <ul>
          <li>2 joueurs par room</li>
          <li>3 quiz par partie</li>
          <li>8 questions aléatoires par quiz</li>
          <li>Le premier joueur à gagner 2 quiz remporte la partie</li>
        </ul>
      </section>
    </div>
  </div>
</body>

</html>