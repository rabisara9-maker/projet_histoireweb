<?php
session_start();
require_once __DIR__ . '/db.php';

cleanOldRooms();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['username'])) {

        $_SESSION['username'] = htmlspecialchars(trim($_POST['username']), ENT_QUOTES, 'UTF-8');
        $_SESSION['age'] = !empty($_POST['age']) ? intval($_POST['age']) : null;

        $avatarsAutorises = ['👤','🧙','⚔️','🏺','👑','🛡️','📜','🗡️','🏹','🔱'];
        $avatar = $_POST['avatar'] ?? '👤';
        $_SESSION['avatar'] = in_array($avatar, $avatarsAutorises) ? $avatar : '👤';

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

function trouverRoomDisponible(): int {
    return findAvailableRoomId();
}

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
  margin-top: 20px;
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
  box-shadow: inset 0 2px 4px rgba(255,255,255,0.06), 0 6px 14px rgba(0,0,0,0.35);
  transition: 0.2s ease;
}

.avatar-picker label:hover {
  transform: translateY(-3px) scale(1.05);
  border-color: #facc15;
}

.avatar-picker input[type=radio]:checked + label {
  border-color: #facc15;
  background: linear-gradient(145deg, #d97706, #facc15);
  color: #1c1208;
  transform: scale(1.08);
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

.step,
.rule-step {
  display: none;
  text-align: center;
  animation: fadeIn 0.4s ease;
}

.step.active,
.rule-step.active {
  display: block;
}

.bubble {
  display: inline-block;
  background: #fff7ed;
  color: #2c170a;
  border: 2px solid #facc15;
  border-radius: 24px;
  padding: 18px 24px;
  margin-bottom: 25px;
  font-size: 1.4rem;
  font-weight: bold;
  box-shadow: 0 8px 20px rgba(0,0,0,0.35);
  position: relative;
}

.bubble::after {
  content: "";
  position: absolute;
  bottom: -14px;
  left: 35px;
  border-width: 14px 14px 0 14px;
  border-style: solid;
  border-color: #fff7ed transparent transparent transparent;
}

.rule-text {
  color: #fff7ed;
  font-size: 1.05rem;
  margin-bottom: 20px;
}

.theme-list {
  margin-top: 18px;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(15px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
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

        <section class="step active" id="step1">
          <div class="bubble">💬 Quel est ton nom ?</div>

          <div class="form-group">
            <input
              type="text"
              name="username"
              placeholder="Entrez votre nom"
              value="<?= htmlspecialchars((string)$currentUsername) ?>"
              required
              maxlength="30"
            />
          </div>

          <button type="button" class="btn btn-primary" onclick="nextStep(2)">
            Entrer dans l’arène ⚔️
          </button>
        </section>

        <section class="step" id="step2">
          <div class="bubble">🎂 Quel âge as-tu ?</div>

          <div class="form-group">
            <input
              type="number"
              name="age"
              placeholder="Votre âge"
              value="<?= htmlspecialchars((string)$currentAge) ?>"
              min="10"
              max="100"
              required
            />
          </div>

          <button type="button" class="btn btn-primary" onclick="nextStep(3)">
            Parfait, on continue ➜
          </button>
        </section>

        <section class="step" id="step3">
          <div class="bubble">🎭 Choisis ton avatar</div>

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

          <button type="button" class="btn btn-primary" onclick="nextStep(4)">
            Valider mon avatar 👑
          </button>
        </section>

        <section class="step" id="step4">
          <div class="bubble">🏰 Créer ou rejoindre une room ?</div>

          <div class="form-group">
            <input
              type="number"
              name="room_code"
              placeholder="Code room optionnel — Ex : 42"
              min="1"
            />
          </div>

          <button type="submit" class="btn btn-primary">
            Lancer le duel 🔥
          </button>
        </section>

      </form>
    </main>

    <footer>
      <section class="rules game-info card-section">
        <h2>Règles du jeu</h2>
        <p class="section-label">Avant de lancer le duel</p>

        <div class="rule-step active" id="rule1">
          <div class="bubble">⚔️ Bienvenue dans Quiz Battle !</div>
          <p class="rule-text">Tu vas affronter un autre joueur dans une room.</p>
          <button type="button" class="btn btn-primary" onclick="nextRule(2)">
            Découvrir les règles ➜
          </button>
        </div>

        <div class="rule-step" id="rule2">
          <div class="bubble">🏁 3 manches maximum</div>
          <p class="rule-text">Le premier joueur qui gagne 2 manches remporte la partie.</p>
          <button type="button" class="btn btn-primary" onclick="nextRule(3)">
            J’ai compris ✅
          </button>
        </div>

        <div class="rule-step" id="rule3">
          <div class="bubble">📜 8 questions par manche</div>
          <p class="rule-text">Les deux joueurs répondent aux mêmes questions historiques.</p>
          <button type="button" class="btn btn-primary" onclick="nextRule(4)">
            Suite de la mission ➜
          </button>
        </div>

        <div class="rule-step" id="rule4">
          <div class="bubble">⏳ 30 secondes</div>
          <p class="rule-text">Tu dois répondre rapidement pour marquer des points.</p>
          <button type="button" class="btn btn-primary" onclick="nextRule(5)">
            Je suis prêt ⚔️
          </button>
        </div>

        <div class="rule-step" id="rule5">
          <div class="bubble">👑 Prêt pour le duel ?</div>
          <p class="rule-text">Choisis ton joueur en haut, puis lance la partie !</p>

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

          <br>

          <button type="button" class="btn btn-primary" onclick="restartRules()">
            Revoir l’entraînement 🔁
          </button>
        </div>
      </section>

      <p class="card-footer">Quiz Battle — Projet pédagogique PHP</p>
    </footer>

  </div>
</div>

<script>
function nextStep(stepNumber) {
  document.querySelectorAll('.step').forEach(step => {
    step.classList.remove('active');
  });

  document.getElementById('step' + stepNumber).classList.add('active');
}

function nextRule(ruleNumber) {
  document.querySelectorAll('.rule-step').forEach(rule => {
    rule.classList.remove('active');
  });

  document.getElementById('rule' + ruleNumber).classList.add('active');
}

function restartRules() {
  document.querySelectorAll('.rule-step').forEach(rule => {
    rule.classList.remove('active');
  });

  document.getElementById('rule1').classList.add('active');
}
</script>

<script src="assets/js/music.js"></script>
</body>
</html>
