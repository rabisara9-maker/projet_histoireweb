<?php
/*Rôle : collecter les infos du joueur, lui attribuer une room,
  puis rediriger vers room.php (salle d'attente). */
session_start();


/* --------------------------------------------------------------------------
   TRAITEMENT DU FORMULAIRE (méthode POST)
   S'exécute uniquement quand le joueur soumet ses informations.
   -------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!empty($_POST['username'])) {

        // --- Informations du joueur ---
        $_SESSION['username'] = htmlspecialchars(trim($_POST['username']), ENT_QUOTES, 'UTF-8');
        $_SESSION['age']      = !empty($_POST['age']) ? intval($_POST['age']) : null;
        $_SESSION['language'] = !empty($_POST['language'])
                                ? htmlspecialchars($_POST['language'], ENT_QUOTES, 'UTF-8')
                                : 'fr';

        // --- Validation de l'avatar (liste blanche d'emojis autorisés) ---
        $avatarsAutorises = ['👤','🧙','⚔️','🏺','👑','🛡️','📜','🗡️','🏹','🔱'];
        $avatar = $_POST['avatar'] ?? '👤';
        $_SESSION['avatar'] = in_array($avatar, $avatarsAutorises) ? $avatar : '👤';

        // --- Attribution d'une room ---
        // Si un code room est fourni, on tente de rejoindre cette room précise.
        // Sinon, on cherche une room libre ou on en crée une nouvelle.
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


/* --------------------------------------------------------------------------
   FONCTION : trouverRoomDisponible()
   Parcourt les fichiers shared_room_N.json pour trouver une room
   avec de la place. Si aucune n'est disponible, en crée une nouvelle.
   -------------------------------------------------------------------------- */
function trouverRoomDisponible(): int{
    $roomId = 1;

    while (true) {
        $fichier = "shared_room_{$roomId}.json";

        // Aucun fichier → room vierge, on la crée et on la retourne
        if (!file_exists($fichier)) {
            $nouvelleRoom = [
                'joueur1'       => null,
                'joueur2'       => null,
                'avatar1'       => null,
                'avatar2'       => null,
                'partie_lancee' => false,
            ];
            file_put_contents($fichier, json_encode($nouvelleRoom));
            return $roomId;
        }

        // La room existe : vérifier si elle a encore de la place
        $room = json_decode(file_get_contents($fichier), true);
        $aDePlace      = !$room['joueur1'] || !$room['joueur2'];
        $partieEnCours = !empty($room['partie_lancee']);

        if ($aDePlace && !$partieEnCours) {
            return $roomId;
        }

        // Room pleine ou partie déjà lancée → essayer la suivante
        $roomId++;
    }
}

/* --------------------------------------------------------------------------
   Liste des avatars disponibles (utilisée dans le formulaire HTML)
   -------------------------------------------------------------------------- */
$avatarsDisponibles = ['👤','🧙','⚔️','🏺','👑','🛡️','📜','🗡️','🏹','🔱'];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Quiz Battle – Accueil</title>

    <!-- Feuille de styles externe (styles globaux + styles spécifiques login) -->
    <link rel="stylesheet" href="style.css"/>
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
  </style>
</head>

<body>
<div class="page">
  <div class="card">

    <!-- ====================================================================
         HEADER — Titre et accroche de la page
         ==================================================================== -->
    <header class="card-header">
      <h1>⚔️ Quiz Battle</h1>
      <p>Créez une room ou rejoignez une partie pour affronter un adversaire sur 3 manches.</p>
    </header>


    <!-- ====================================================================
         MAIN — Formulaire de connexion
         Divisé en 3 sections :
           1. Informations du joueur (nom, âge, langue, avatar)
           2. Rejoindre ou créer une room
           3. Bouton de soumission
         ==================================================================== -->
    <main>
      <form class="form" action="login.php" method="POST">

        <!-- Section 1 : Informations personnelles du joueur -->
        <section class="card-section">
          <div class="form-group">
            <label for="player-name">Nom du joueur</label>
            <input
              type="text"
              id="player-name"
              name="username"
              placeholder="Entrez votre nom"
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
              min="10"
              max="100"
              required
            />
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
              <?php foreach ($avatarsDisponibles as $index => $emoji) : ?>
                <div class="avatar-item">
                  <input
                    type="radio"
                    name="avatar"
                    id="av<?= $index ?>"
                    value="<?= $emoji ?>"
                    <?= $index === 0 ? 'checked' : '' ?>
                  />
                  <label for="av<?= $index ?>"><?= $emoji ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>

        <!-- Section 2 : Rejoindre ou créer une room -->
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

        <!-- Section 3 : Soumission -->
        <section class="card-section">
          <div class="button-group">
            <button type="submit" class="btn btn-primary">Rejoindre / Créer</button>
          </div>
        </section>

      </form>
    </main>


    <!-- ====================================================================
         FOOTER — Règles du jeu (information statique)
         ==================================================================== -->
    <footer>
      <section class="rules card-section">
        <h2>Règles du jeu</h2>
        <ul>
          <li>2 joueurs par room</li>
          <li>3 manches par partie</li>
          <li>8 questions aléatoires par manche</li>
          <li>30 secondes pour répondre à chaque question</li>
          <li>Le premier à gagner 2 manches remporte la partie</li>
        </ul>
      </section>

      <p class="card-footer">Quiz Battle — Projet pédagogique PHP</p>
    </footer>

  </div><!-- /.card -->
</div><!-- /.page -->
</body>
</html>