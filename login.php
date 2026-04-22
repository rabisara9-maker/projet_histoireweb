<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['username'])) {
        $_SESSION['username'] = htmlspecialchars(trim($_POST['username']), ENT_QUOTES, 'UTF-8');
        $_SESSION['age']      = !empty($_POST['age']) ? intval($_POST['age']) : null;
        $_SESSION['language'] = !empty($_POST['language']) ? htmlspecialchars($_POST['language'], ENT_QUOTES, 'UTF-8') : 'fr';

        // CORRECTION BUG #10 : l'avatar est un emoji, pas un upload de fichier
        $avatarsAutorisés = ['👤','🧙','⚔️','🏺','👑','🛡️','📜','🗡️','🏹','🔱'];
        $avatar = $_POST['avatar'] ?? '👤';
        $_SESSION['avatar'] = in_array($avatar, $avatarsAutorisés) ? $avatar : '👤';

        // Trouver ou créer une room
        $roomId = null;
        if (!empty($_POST['room_code'])) {
            $roomId = intval($_POST['room_code']);
            if ($roomId <= 0) $roomId = null;
        }
        if (!$roomId) {
            $roomId = trouverRoomDisponible();
        }
        $_SESSION['room_id'] = $roomId;

        header("Location: room.php"); exit();
    }
}

function trouverRoomDisponible() {
    $roomId = 1;
    while (true) {
        $f = "shared_room_{$roomId}.json";
        if (!file_exists($f)) {
            $room = ['joueur1'=>null,'joueur2'=>null,'avatar1'=>null,'avatar2'=>null,'partie_lancee'=>false];
            file_put_contents($f, json_encode($room));
            return $roomId;
        }
        $room = json_decode(file_get_contents($f), true);
        // Room disponible si elle a de la place ET que la partie n'est pas encore lancée
        if ((!$room['joueur1'] || !$room['joueur2']) && empty($room['partie_lancee'])) {
            return $roomId;
        }
        $roomId++;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Quiz Battle – Accueil</title>
  <link rel="stylesheet" href="style.css"/>
  <style>
    .avatar-picker { display:flex; flex-wrap:wrap; gap:10px; margin-top:8px; }
    .avatar-picker input[type=radio] { display:none; }
    .avatar-picker label {
      width:44px; height:44px; border-radius:8px;
      display:flex; align-items:center; justify-content:center;
      font-size:1.5rem; cursor:pointer;
      border:2px solid transparent;
      background:rgba(255,255,255,.1);
      transition:.15s;
    }
    .avatar-picker input[type=radio]:checked + label {
      border-color:#22c55e; background:rgba(34,197,94,.2);
    }
    .avatar-picker label:hover { border-color:rgba(255,255,255,.4); }
  </style>
</head>
<body>
<div class="page">
  <div class="card">
    <header class="card-header">
      <h1>⚔️ Quiz Battle</h1>
      <p>Créez une room ou rejoignez une partie pour affronter un adversaire sur 3 manches.</p>
    </header>

    <form class="form" action="login.php" method="POST">
      <div class="form-group">
        <label for="player-name">Nom du joueur</label>
        <input type="text" id="player-name" name="username" placeholder="Entrez votre nom" required maxlength="30"/>
      </div>

      <div class="form-group">
        <label for="player-age">Âge</label>
        <input type="number" id="player-age" name="age" placeholder="Votre âge" min="10" max="100" required/>
      </div>

      <div class="form-group">
        <label for="language">Langue</label>
        <select id="language" name="language">
          <option value="fr">Français</option>
          <option value="en">English</option>
          <option value="ar">العربية</option>
        </select>
      </div>

      <div class="form-group">
        <label>Avatar</label>
        <div class="avatar-picker">
          <?php
          $avs = ['👤','🧙','⚔️','🏺','👑','🛡️','📜','🗡️','🏹','🔱'];
          foreach ($avs as $i => $av):
          ?>
          <input type="radio" name="avatar" id="av<?= $i ?>" value="<?= $av ?>" <?= $i===0?'checked':'' ?>>
          <label for="av<?= $i ?>"><?= $av ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-group">
        <label for="room-code">Code de la room <span style="font-weight:normal;color:#dbeafe;">(optionnel – laisser vide pour créer)</span></label>
        <input type="number" id="room-code" name="room_code" placeholder="Ex : 42" min="1"/>
      </div>

      <div class="button-group">
        <button type="submit" class="btn btn-primary">Rejoindre / Créer</button>
      </div>
    </form>

    <section class="rules">
      <h2>Règles du jeu</h2>
      <ul>
        <li>2 joueurs par room</li>
        <li>3 manches par partie</li>
        <li>8 questions aléatoires par manche</li>
        <li>30 secondes pour répondre à chaque question</li>
        <li>Le premier à gagner 2 manches remporte la partie</li>
      </ul>
    </section>
  </div>
</div>
</body>
</html>
