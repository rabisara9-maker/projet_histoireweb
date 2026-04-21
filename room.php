<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();  
}

$username = $_SESSION['username']; 
$avatar = $_SESSION['avatar'];
$roomId = $_SESSION['room_id'] ?? 1; // Par défaut room 1

// Fichier partagé pour la room
$sharedRoomFile = "shared_room_{$roomId}.json";
function lireRoomPartage() {
    global $sharedRoomFile;
    if (file_exists($sharedRoomFile)) {
        return json_decode(file_get_contents($sharedRoomFile), true);
    }
    return ['joueur1' => null, 'joueur2' => null, 'avatar1' => null, 'avatar2' => null];
}
function ecrireRoomPartage($room) {
    global $sharedRoomFile;
    file_put_contents($sharedRoomFile, json_encode($room));
}

$room = lireRoomPartage();

// Gérer les joueurs dans le fichier partagé
if (!$room['joueur1']) {
    $room['joueur1'] = $username;
    $room['avatar1'] = $avatar;
    ecrireRoomPartage($room);
} elseif (!$room['joueur2'] && $room['joueur1'] !== $username) {
    $room['joueur2'] = $username;
    $room['avatar2'] = $avatar;
    ecrireRoomPartage($room);
}

// Vérifier si 2 joueurs sont présents
$joueur1 = $room['joueur1'];
$joueur2 = $room['joueur2'];
$deuxJoueurs = $joueur1 && $joueur2;
?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Quiz Battle - Salle d'attente</title>
  <link rel="stylesheet" href="waiting-room.css" />
</head>

<body>
  <div class="page">
    <div class="card">
      <header class="card-header">
        <h1>Salle d’attente</h1>
        <p>Les deux joueurs doivent être connectés avant de commencer la partie.</p>
      </header>

      <section class="room-box">
        <p>Code de la room : <strong><?php echo $roomId; ?></strong></p>
      </section>

      <section class="players">
        <div class="player-card">
          <div class="avatar"><?php echo $room['avatar1'] ? "<img src='{$room['avatar1']}' alt='Avatar'>" : "👤"; ?></div>
          <h2>Joueur 1</h2>
          <p class="player-name"><?php echo $joueur1 ?? 'En attente...'; ?></p>
          <p class="status <?php echo $joueur1 ? 'status-online' : 'status-offline'; ?>"><?php echo $joueur1 ? 'Connecté' : 'Non connecté'; ?></p>
        </div>

        <div class="versus">VS</div>

        <div class="player-card">
          <div class="avatar"><?php echo $room['avatar2'] ? "<img src='{$room['avatar2']}' alt='Avatar'>" : "👤"; ?></div>
          <h2>Joueur 2</h2>
          <p class="player-name"><?php echo $joueur2 ?? 'En attente...'; ?></p>
          <p class="status <?php echo $joueur2 ? 'status-online' : 'status-offline'; ?>"><?php echo $joueur2 ? 'Connecté' : 'Non connecté'; ?></p>
        </div>
      </section>

      <section class="info-box">
        <h2>Informations</h2>
        <ul>
          <li>La partie se déroule en 3 quiz</li>
          <li>Chaque quiz contient 8 questions aléatoires</li>
          <li>Le premier joueur qui gagne 2 quiz remporte la partie</li>
        </ul>
      </section>

      <?php if ($deuxJoueurs): ?>
        <form action="quiz.php" method="POST">
          <button class="start-btn" type="submit">Commencer la partie</button>
        </form>
      <?php else: ?>
        <p>En attente d'un deuxième joueur...</p>
        <script>setTimeout(() => { location.reload(); }, 2000);</script> <!-- Auto-refresh pour voir les nouveaux joueurs -->
      <?php endif; ?>
    </div>
  </div>
</body>

</html>