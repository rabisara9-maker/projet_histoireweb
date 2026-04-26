<?php
// Page d'accueil : on récupère les infos du joueur avant de l'envoyer en salle.
session_start();
require_once __DIR__ . '/db.php';

cleanOldRooms();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!empty($_POST['username'])) {

        // Infos gardées en session pour reconnaître le joueur pendant la partie.
        $_SESSION['username'] = htmlspecialchars(trim($_POST['username']), ENT_QUOTES, 'UTF-8');
        $_SESSION['age']      = !empty($_POST['age']) ? intval($_POST['age']) : null;

        // On accepte seulement les avatars proposés dans le formulaire.
        $avatarsAutorises = ['👤','🧙','⚔️','🏺','👑','🛡️','📜','🗡️','🏹','🔱'];
        $avatar = $_POST['avatar'] ?? '👤';
        $_SESSION['avatar'] = in_array($avatar, $avatarsAutorises) ? $avatar : '👤';

        // Avec un code on rejoint une room précise, sinon on prend une room libre.
        $roomId = null;
        if (!empty($_POST['room_code'])) {
            $roomId = intval($_POST['room_code']);
            if ($roomId <= 0) $roomId = null;
        }
        if (!$roomId) {
            $roomId = trouverRoomDisponible();
        }
        $_SESSION['room_id'] = $roomId;

        header("Location: room.php");
        exit();
    }
}


function trouverRoomDisponible(): int{
    return findAvailableRoomId();
}

// Avatars affichés dans le choix du joueur.
$avatarsDisponibles = ['👤','🧙','⚔️','🏺','👑','🛡️','📜','🗡️','🏹','🔱'];
$currentUsername = $_SESSION['username'] ?? '';
$currentAge = $_SESSION['age'] ?? '';
$currentAvatar = $_SESSION['avatar'] ?? '👤';
$loginError = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Quiz Battle – Accueil</title>

    <link rel="stylesheet" href="assets/css/style.css?v=2"/>
    <style>
.avatar-picker {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 10px;
  justify-content: center;
}

.avatar-picker input[type=radio] {
  display: none;
}

.avatar-picker label {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.7rem;
  cursor: pointer;

  border: 2px solid rgba(250, 204, 21, 0.18);

  background: linear-gradient(145deg, #4a2b13, #2c170a);
  color: #fff7ed;

  box-shadow:
    inset 0 2px 4px rgba(255,255,255,0.06),
    0 6px 14px rgba(0,0,0,0.35);

  transition: 0.2s ease;
}

.avatar-picker label:hover {
  transform: translateY(-3px) scale(1.05);
  border-color: #facc15;
  box-shadow:
    0 0 14px rgba(250, 204, 21, 0.25),
    0 8px 18px rgba(0,0,0,0.45);
}

.avatar-picker input[type=radio]:checked + label {
  border-color: #facc15;
  background: linear-gradient(145deg, #d97706, #facc15);
  color: #1c1208;
  transform: scale(1.08);
  box-shadow:
    0 0 18px rgba(250, 204, 21, 0.45),
    0 10px 20px rgba(0,0,0,0.45);
}

.error-box {
  background: rgba(127, 29, 29, 0.85);
  border: 1px solid #fca5a5;
  border-radius: 12px;
  color: #fee2e2;
  font-weight: bold;
  margin-bottom: 20px;
  padding: 14px 16px;
  text-align: center;
}
  </style>
</head>

<body>
<div class="page">
  <div class="card">

    <header class="card-header">
      <h1>⚔️ Quiz Battle</h1>
      <p>Créez une room ou rejoignez une partie pour affronter un adversaire sur 3 manches.</p>
    </header>

    <?php if ($loginError): ?>
      <div class="error-box"><?= htmlspecialchars($loginError) ?></div>
    <?php endif; ?>


    <main>
      <form class="form" action="login.php" method="POST">

        <section class="card-section">
          <div class="form-group">
            <label for="player-name">Nom du joueur</label>
            <input
              type="text"
              id="player-name"
              name="username"
              placeholder="Entrez votre nom"
              value="<?= htmlspecialchars((string)$currentUsername) ?>"
              required
              maxlength="30"
            />
          </div>

          <div class="form-group">
            <label for="player-age">Âge</label>
            <input
              type="number"
              id="player-age"
              name="age"
              placeholder="Votre âge"
              value="<?= htmlspecialchars((string)$currentAge) ?>"
              min="10"
              max="100"
              required
            />
          </div>

          <div class="form-group">
            <label>Avatar</label>
            <div class="avatar-picker">
              <?php foreach ($avatarsDisponibles as $index => $emoji) : ?>
                <div class="avatar-item">
                  <input
                    type="radio"
                    name="avatar"
                    id="av<?= $index ?>"
                    value="<?= $emoji ?>"
                    <?= $emoji === $currentAvatar ? 'checked' : '' ?>
                  />
                  <label for="av<?= $index ?>"><?= $emoji ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>

        <section class="card-section">
          <div class="form-group">
            <label for="room-code">
              Code de la room
              <span class="label-note">(optionnel – laisser vide pour créer)</span>
            </label>
            <input
              type="number"
              id="room-code"
              name="room_code"
              placeholder="Ex : 42"
              min="1"
            />
          </div>
        </section>

        <section class="card-section">
          <div class="button-group">
            <button type="submit" class="btn btn-primary">Rejoindre / Créer</button>
          </div>
        </section>

      </form>
    </main>


    <footer>
      <section class="rules game-info card-section">
        <h2>Règles du jeu</h2>
        <p class="section-label">Avant de lancer le duel</p>
        <p class="rules-intro">
          Chaque manche choisit un thème historique au hasard. Les deux joueurs répondent aux mêmes questions,
          et les scores sont mis à jour en même temps pendant la partie.
        </p>
        <div class="rules-grid">
          <div class="rule-card"><span>⚔️ </span><strong>2 joueurs </strong><small>un duel par room</small></div>
          <div class="rule-card"><span>🏁 </span><strong>3 manches </strong><small>maximum par partie</small></div>
          <div class="rule-card"><span>📜 </span><strong>8 questions </strong><small>par manche</small></div>
          <div class="rule-card"><span>⏳ </span><strong>30 secondes </strong><small>pour répondre</small></div>
          <div class="rule-card"><span>👑 </span><strong>2 manches </strong><small>pour gagner</small></div>
        </div>
        <div class="themes-box">
          <h3>Thèmes possibles</h3>
          <div class="theme-list">
            <span>Antiquité</span>
            <span>Moyen Âge</span>
            <span>Grandes découvertes</span>
            <span>Histoire de France</span>
            <span>Personnages historiques</span>
            <span>Guerres mondiales</span>
            <span>Histoire de l'IA</span>
          </div>
        </div>
      </section>

      <p class="card-footer">Quiz Battle — Projet pédagogique PHP</p>
    </footer>

  </div>
</div>
<script src="assets/js/music.js"></script>
</body>
</html>
