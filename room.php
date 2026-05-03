<?php
session_start();
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php"); exit();
}

$username = $_SESSION['username'];
$avatar   = $_SESSION['avatar'] ?? 'default';
$roomId   = $_SESSION['room_id'] ?? findAvailableRoomId();
$_SESSION['room_id'] = $roomId;

$existingRoom = getRoom((int)$roomId);
$alreadyJoinedHere = (int)($_SESSION['joined_room_id'] ?? 0) === (int)$roomId;

if (
    $existingRoom &&
    !$alreadyJoinedHere &&
    (($existingRoom['joueur1'] ?? null) === $username || ($existingRoom['joueur2'] ?? null) === $username)
) {
    $_SESSION['login_error'] = "Ce pseudo est déjà utilisé dans cette room.";
    unset($_SESSION['room_id']);
    header("Location: login.php");
    exit();
}

$room = joinRoom((int)$roomId, $username, $avatar);
$_SESSION['joined_room_id'] = (int)$roomId;

if (($room['joueur1'] ?? null) !== $username && ($room['joueur2'] ?? null) !== $username) {
    $_SESSION['room_id'] = findAvailableRoomId();
    unset($_SESSION['joined_room_id']);
    header("Location: room.php");
    exit();
}

// Si la partie a déjà commencé, on ne reste pas en salle d'attente.
if (!empty($room['partie_lancee'])) {
    header("Location: quiz.php"); exit();
}

// Le bouton commence seulement si les deux joueurs sont là.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'start') {
    if (startRoomIfReady((int)$roomId)) {
        header("Location: quiz.php"); exit();
    }
    $room = getRoom((int)$roomId);
}

$joueur1     = $room['joueur1'] ?? null;
$joueur2     = $room['joueur2'] ?? null;
$avatar1     = $room['avatar1'] ?? '👤';
$avatar2     = $room['avatar2'] ?? '👤';
$deuxJoueurs = $joueur1 && $joueur2;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Quiz Battle – Salle d'attente</title>
  <link rel="stylesheet" href="assets/css/waiting-room.css?v=2"/>
</head>
<body>
<div class="page">
  <div class="card">
    <header class="card-header">
      <h1>Salle d'attente</h1>
      <p>Les deux joueurs doivent être connectés avant de commencer.</p>
    </header>

    <section class="room-box">
      <p>Code de la room : <strong><?= htmlspecialchars((string)$roomId) ?></strong></p>
      <p class="room-note">
        Partagez ce code à votre adversaire pour qu'il rejoigne la même room.
      </p>
    </section>

    <section class="players">
      <div class="player-card">
        <div class="avatar"><?= htmlspecialchars($avatar1) ?></div>
        <h2>Joueur 1</h2>
        <p class="player-name"><?= htmlspecialchars($joueur1 ?? 'En attente…') ?></p>
        <p class="status <?= $joueur1 ? 'status-online' : 'status-offline' ?>">
          <?= $joueur1 ? 'Connecté' : 'Non connecté' ?>
        </p>
      </div>

      <div class="versus">VS</div>

      <div class="player-card">
        <div class="avatar"><?= htmlspecialchars($avatar2) ?></div>
        <h2>Joueur 2</h2>
        <p class="player-name"><?= htmlspecialchars($joueur2 ?? 'En attente…') ?></p>
        <p class="status <?= $joueur2 ? 'status-online' : 'status-offline' ?>">
          <?= $joueur2 ? 'Connecté' : 'Non connecté' ?>
        </p>
      </div>
    </section>

    <section class="info-box">
      <h2>Règles</h2>
      <ul>
        <li>2 joueurs par room</li>
        <li>3 manches par partie</li>
        <li>8 questions aléatoires par manche</li>
        <li>Le premier à gagner 2 manches remporte la partie</li>
      </ul>
    </section>

    <?php if ($deuxJoueurs): ?>
      <form action="room.php" method="POST">
        <input type="hidden" name="action" value="start">
        <button class="start-btn" type="submit">⚔️ Commencer la partie</button>
      </form>
    <?php else: ?>
      <p class="wait-message">En attente d'un deuxième joueur…</p>
    <?php endif; ?>
  </div>
</div>

<script>
// On vérifie régulièrement si l'autre joueur est arrivé.
(function() {
  const deuxJoueurs = <?= json_encode($deuxJoueurs) ?>;
  const iv = setInterval(() => {
    fetch('ajax/etat_room.php')
      .then(r => r.json())
      .then(d => {
        if (d.error === 'no_session') { clearInterval(iv); location.href = 'login.php'; return; }
        if (d.partie_lancee) { clearInterval(iv); location.href = 'quiz.php'; return; }
        if (!deuxJoueurs && d.joueur1 && d.joueur2) { clearInterval(iv); location.reload(); }
      })
      .catch(() => {});
  }, 2000);
})();
</script>
</body>
</html>
