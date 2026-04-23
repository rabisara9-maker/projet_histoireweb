<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$roomId = $_SESSION['room_id'] ?? 1;
$sharedRoomFile = "shared_room_{$roomId}.json";
$sharedFile     = "shared_game_{$roomId}.json";

// ── Fonctions ────────────────────────────────────────────────────────────────
function lireEtatPartage() {
    global $sharedFile;
    if (file_exists($sharedFile)) {
        $data = json_decode(file_get_contents($sharedFile), true);
        return is_array($data) ? $data : [];
    }
    return [];
}

function ecrireEtatPartage($etat) {
    global $sharedFile;
    $tmp = $sharedFile . '.tmp.' . getmypid();
    file_put_contents($tmp, json_encode($etat));
    rename($tmp, $sharedFile);
}

// ── Vérifications session / room ─────────────────────────────────────────────
if (!file_exists($sharedRoomFile)) {
    header("Location: room.php");
    exit();
}

$roomData = json_decode(file_get_contents($sharedRoomFile), true);
if (!($roomData['joueur1'] ?? null) || !($roomData['joueur2'] ?? null)) {
    header("Location: room.php");
    exit();
}

// ── Constantes du jeu ────────────────────────────────────────────────────────
$QUESTIONS_PAR_MANCHE = 8;
$NB_MANCHES = 3;

$defaultEtat = [
    'manche'              => 1,
    'score_joueur1'       => 0,
    'score_joueur2'       => 0,
    'manches_gagnees_j1'  => 0,
    'manches_gagnees_j2'  => 0,
    'questions_manches'   => [],
    'theme_manche'        => null,
    'themes_utilises'     => [],
    'question_actuelle'   => 0,
    'reponses'            => [],
    'score_calcule'       => [],
    'manche_terminee'     => false,
    'start_time'          => time() + 3,
];

$etat = lireEtatPartage();
if (empty($etat)) {
    $etat = $defaultEtat;
} else {
    foreach ($defaultEtat as $k => $v) {
        if (!array_key_exists($k, $etat)) {
            $etat[$k] = $v;
        }
    }
}

// ── Chargement des questions ─────────────────────────────────────────────────
$questions = json_decode(file_get_contents('questions.json'), true);
$themes    = array_values(array_unique(array_column($questions, 'theme')));

function choisirThemeAleatoire($themes, $themesUtilises = []) {
    $dispo = array_diff($themes, $themesUtilises);
    if (empty($dispo)) {
        $dispo = $themes;
    }
    $dispo = array_values($dispo);
    return $dispo[rand(0, count($dispo) - 1)];
}

function filtrerQuestionsParTheme($questions, $theme) {
    return array_values(array_filter($questions, fn($q) => $q['theme'] === $theme));
}

function melangerOptions($question) {
    $options = [
        'A' => $question['a'],
        'B' => $question['b'],
        'C' => $question['c']
    ];

    $cles = array_keys($options);
    shuffle($cles);

    $optionsMelangees = array_map(fn($k) => $options[$k], $cles);
    $nouvellePositionCorrecte = array_search('A', $cles) + 1;

    return [
        'question' => $question['question'],
        'options'  => $optionsMelangees,
        'correct'  => $nouvellePositionCorrecte,
    ];
}

// ── Initialiser les questions de la manche courante ─────────────────────────
$manche    = (int)($etat['manche'] ?? 1);
$mancheKey = (string)$manche;

if (!isset($etat['questions_manches'][$mancheKey])) {
    $themesUtilises = $etat['themes_utilises'] ?? [];
    $themeChoisi    = choisirThemeAleatoire($themes, $themesUtilises);

    $etat['themes_utilises'][] = $themeChoisi;
    $etat['theme_manche'] = $themeChoisi;
    $etat['question_actuelle'] = 0;
    $etat['reponses'] = [];
    $etat['score_calcule'] = [];
    $etat['manche_terminee'] = false;

    $questionsFiltrees = filtrerQuestionsParTheme($questions, $themeChoisi);
    shuffle($questionsFiltrees);
    $questionsSelectionnees = array_slice($questionsFiltrees, 0, $QUESTIONS_PAR_MANCHE);

    $etat['questions_manches'][$mancheKey] = array_map('melangerOptions', $questionsSelectionnees);
    $etat['question_start_time'] = time(); // Démarrer le timer pour la 1ère question
    ecrireEtatPartage($etat);
}

// ── Enregistrer le start_time si question vient de changer (nouveau chargement sans timer) ──
// Si question_start_time n'existe pas ou correspond à une ancienne question, le créer
if (!isset($etat['question_start_time'])) {
    $etat['question_start_time'] = time();
    ecrireEtatPartage($etat);
}

// ── Identité du joueur courant ───────────────────────────────────────────────
$joueur       = ($roomData['joueur1'] === $_SESSION['username']) ? 'joueur1' : 'joueur2';
$joueurAdv    = ($joueur === 'joueur1') ? 'joueur2' : 'joueur1';
$nomJoueur    = $roomData[$joueur] ?? 'Joueur';
$nomAdverse   = $roomData[$joueurAdv] ?? 'Adversaire';
$avatarJoueur = $roomData['avatar' . ($joueur === 'joueur1' ? '1' : '2')] ?? '👤';
$avatarAdverse = $roomData['avatar' . ($joueur === 'joueur1' ? '2' : '1')] ?? '👤';

$scoreJoueur  = (int)($etat['score_' . $joueur] ?? 0);
$scoreAdverse = (int)($etat['score_' . $joueurAdv] ?? 0);

$manchesJ = (int)($etat['manches_gagnees_' . ($joueur === 'joueur1' ? 'j1' : 'j2')] ?? 0);
$manchesAdv = (int)($etat['manches_gagnees_' . ($joueur === 'joueur1' ? 'j2' : 'j1')] ?? 0);

// ── Question actuelle ────────────────────────────────────────────────────────
$questionIndex   = (int)($etat['question_actuelle'] ?? 0);
$questionsManche = $etat['questions_manches'][$mancheKey] ?? [];
$totalQuestions  = count($questionsManche);

// ── Fin de manche ────────────────────────────────────────────────────────────
if ($questionIndex >= $totalQuestions && $totalQuestions > 0) {

    // Recharger l'état le plus récent pour éviter les conflits entre joueurs
    $etat = lireEtatPartage();
    foreach ($defaultEtat as $k => $v) {
        if (!array_key_exists($k, $etat)) {
            $etat[$k] = $v;
        }
    }

    $manche = (int)($etat['manche'] ?? 1);
    $mancheKey = (string)$manche;
    $questionIndex = (int)($etat['question_actuelle'] ?? 0);
    $questionsManche = $etat['questions_manches'][$mancheKey] ?? [];
    $totalQuestions = count($questionsManche);

    if ($questionIndex >= $totalQuestions && $totalQuestions > 0) {

if (!empty($etat['manche_terminee'])) {
    $manchesJ1 = (int)($etat['manches_gagnees_j1'] ?? 0);
    $manchesJ2 = (int)($etat['manches_gagnees_j2'] ?? 0);
    $mancheActuelle = (int)($etat['manche'] ?? 1);

    // Partie terminée
    if ($manchesJ1 >= 2 || $manchesJ2 >= 2 || $mancheActuelle > 3) {
        header("Location: score.php");
        exit();
    }

    // Sinon on repart sur le quiz
    $etat['manche_terminee'] = false;
    ecrireEtatPartage($etat);

    header("Location: quiz.php");
    exit();
}
        // Verrou logique
        $etat['manche_terminee'] = true;
        ecrireEtatPartage($etat);

        // Relecture immédiate pour être sûr d’avoir la dernière version
        $etat = lireEtatPartage();
        foreach ($defaultEtat as $k => $v) {
            if (!array_key_exists($k, $etat)) {
                $etat[$k] = $v;
            }
        }

        // Si quelqu’un a déjà avancé entre-temps
        if ((int)($etat['question_actuelle'] ?? 0) < $totalQuestions) {
            header("Location: quiz.php");
            exit();
        }

        // Déterminer le gagnant de la manche
        if ($etat['score_joueur1'] > $etat['score_joueur2']) {
            $etat['manches_gagnees_j1']++;
        } elseif ($etat['score_joueur2'] > $etat['score_joueur1']) {
            $etat['manches_gagnees_j2']++;
        }

        // Fin de partie
        if (
            $etat['manches_gagnees_j1'] >= 2 ||
            $etat['manches_gagnees_j2'] >= 2 ||
            $manche >= $NB_MANCHES
        ) {
            ecrireEtatPartage($etat);
            header("Location: score.php");
            exit();
        }

        // Manche suivante
        $etat['manche']++;
        $etat['question_actuelle'] = 0;
        $etat['score_joueur1'] = 0;
        $etat['score_joueur2'] = 0;
        $etat['reponses'] = [];
        $etat['score_calcule'] = [];
        $etat['manche_terminee'] = false;
        $etat['theme_manche'] = null;

        $nouvelleMancheKey = (string)$etat['manche'];
        if (!isset($etat['questions_manches'][$nouvelleMancheKey])) {
            $themesUtilises = $etat['themes_utilises'] ?? [];
            $themeChoisi = choisirThemeAleatoire($themes, $themesUtilises);

            $etat['themes_utilises'][] = $themeChoisi;
            $etat['theme_manche'] = $themeChoisi;

            $questionsFiltrees = filtrerQuestionsParTheme($questions, $themeChoisi);
            shuffle($questionsFiltrees);
            $questionsSelectionnees = array_slice($questionsFiltrees, 0, $QUESTIONS_PAR_MANCHE);

            $etat['questions_manches'][$nouvelleMancheKey] = array_map('melangerOptions', $questionsSelectionnees);
        }

        ecrireEtatPartage($etat);
        header("Location: quiz.php");
        exit();
    }
}

$currentQuestion = $questionsManche[$questionIndex] ?? [];
$theme           = $etat['theme_manche'] ?? 'Thème';
$dejaRepondu     = isset($etat['reponses'][$questionIndex][$joueur]);

// ── Calcul du temps restant RÉEL (résiste aux actualisations) ─────────────────
$TIMER_DUREE     = 30;
$questionStart   = (int)($etat['question_start_time'] ?? time());
$tempsEcoule     = time() - $questionStart;
$tempsRestant    = max(0, $TIMER_DUREE - $tempsEcoule);
// Si le timer est expiré et que le joueur n'a pas encore répondu → auto-submit côté serveur
// (géré en JS, mais on passe la valeur réelle au frontend)
$deuxReponses    = isset($etat['reponses'][$questionIndex]['joueur1']) &&
                   isset($etat['reponses'][$questionIndex]['joueur2']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Quiz Battle – Manche <?= $manche ?></title>
  <link rel="stylesheet" href="quiz.css"/>
<style>
  .waiting-msg {
    text-align: center;
    padding: 30px 0;
    color: #fde68a;
  }

  .waiting-msg .spinner {
    display: inline-block;
    width: 38px;
    height: 38px;
    border: 4px solid rgba(250, 204, 21, 0.18);
    border-top-color: #facc15;
    border-radius: 50%;
    animation: spin .8s linear infinite;
    margin-bottom: 12px;
    box-shadow: 0 0 14px rgba(250, 204, 21, 0.35);
  }

  @keyframes spin {
    to { transform: rotate(360deg); }
  }

  .manches-bar {
    display: flex;
    gap: 9px;
    align-items: center;
  }

  .manche-dot {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 2px solid rgba(250, 204, 21, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .7rem;
    font-weight: bold;
    color: #fef3c7;
    background: rgba(41, 24, 12, 0.75);
  }

  .manche-dot.won {
    background: linear-gradient(135deg, #d97706, #facc15);
    border-color: #facc15;
    color: #1c1208;
  }

  .manche-dot.lost {
    background: linear-gradient(135deg, #7f1d1d, #dc2626);
    border-color: #ef4444;
    color: #fff7ed;
  }

  .answer-btn.disabled {
    opacity: .55;
    cursor: not-allowed;
    filter: grayscale(0.4);
  }

  .answer-btn.already {
    background: linear-gradient(135deg, #92400e, #f59e0b);
    color: #fff7ed;
    border-color: #facc15;
  }
</style>
</head>
<body>
<div class="page">
  <div class="quiz-layout">

    <header class="top-bar">
      <div class="top-left">
        <span class="badge">Manche <?= $manche ?> / 3</span>
        <span class="badge">📚 <?= htmlspecialchars($theme) ?></span>
      </div>
      <div class="timer-box">
        <p>Temps restant</p>
        <div class="timer-bar"><div class="timer-fill" id="timerBar"></div></div>
        <span id="timer"><?= $dejaRepondu ? 'Répondu ✓' : $tempsRestant . ' s' ?></span>
      </div>
    </header>

    <section class="players">
      <div class="player-card">
        <div class="avatar"><?= htmlspecialchars($avatarJoueur) ?></div>
        <h2>Vous</h2>
        <p class="player-name"><?= htmlspecialchars($nomJoueur) ?></p>
        <p class="score">Score manche : <?= $scoreJoueur ?></p>
        <div class="manches-bar" style="justify-content:center;margin-top:8px;">
          <?php for ($i = 0; $i < 3; $i++): $won = ($i < $manchesJ); ?>
            <div class="manche-dot <?= $won ? 'won' : '' ?>">M<?= $i + 1 ?></div>
          <?php endfor; ?>
        </div>
      </div>

      <div class="versus">VS</div>

      <div class="player-card">
        <div class="avatar"><?= htmlspecialchars($avatarAdverse) ?></div>
        <h2>Adversaire</h2>
        <p class="player-name"><?= htmlspecialchars($nomAdverse) ?></p>
        <p class="score">Score manche : <?= $scoreAdverse ?></p>
        <div class="manches-bar" style="justify-content:center;margin-top:8px;">
          <?php for ($i = 0; $i < 3; $i++): $won = ($i < $manchesAdv); ?>
            <div class="manche-dot <?= $won ? 'won' : '' ?>">M<?= $i + 1 ?></div>
          <?php endfor; ?>
        </div>
      </div>
    </section>

    <main class="quiz-card">
      <p class="question-number">Question <?= $questionIndex + 1 ?> / <?= $totalQuestions ?></p>
      <h1 class="question"><?= htmlspecialchars($currentQuestion['question'] ?? '') ?></h1>

      <?php if ($deuxReponses): ?>
        <div class="waiting-msg">
          <div class="spinner"></div>
          <p>Les deux joueurs ont répondu. Passage à la suivante…</p>
        </div>
        <script>
          setTimeout(() => location.reload(), 1200);
        </script>

      <?php elseif ($dejaRepondu): ?>
        <div class="waiting-msg" data-question-index="<?= $questionIndex ?>">
          <div class="spinner"></div>
          <p>En attente de l'adversaire…</p>
        </div>

        <div class="answers" style="margin-top:20px;pointer-events:none;">
          <?php
          $reponseJoueur = $etat['reponses'][$questionIndex][$joueur] ?? '';
          foreach ($currentQuestion['options'] as $i => $option):
              $lettre = chr(65 + $i);
              $cls = 'disabled' . ($lettre === $reponseJoueur ? ' already' : '');
          ?>
            <button class="answer-btn <?= $cls ?>" disabled>
              <?= $lettre ?>. <?= htmlspecialchars($option) ?>
            </button>
          <?php endforeach; ?>
        </div>

      <?php else: ?>
        <form id="quizForm" action="verifier_reponses.php" method="POST">
          <div class="answers">
            <?php foreach ($currentQuestion['options'] as $i => $option): ?>
              <?php $lettre = chr(65 + $i); ?>
              <button class="answer-btn" type="button" onclick="selectAnswer('<?= $lettre ?>', this)">
                <?= $lettre ?>. <?= htmlspecialchars($option) ?>
              </button>
            <?php endforeach; ?>
          </div>

          <input type="hidden" name="reponse" id="selectedAnswer" value="">
          <input type="hidden" name="question_index" value="<?= $questionIndex ?>">

          <div class="action-box">
            <button class="next-btn" type="submit" id="submitBtn" disabled style="opacity:.5;cursor:not-allowed">
              Soumettre la réponse
            </button>
          </div>
        </form>
      <?php endif; ?>
    </main>

  </div>
</div>

<script>
<?php if (!$dejaRepondu): ?>
(function() {
  const TOTAL = 30;
  // Temps restant RÉEL calculé côté serveur — résiste aux actualisations de page
  let left = <?= $tempsRestant ?>;

  const lbl = document.getElementById('timer');
  const bar = document.getElementById('timerBar');

  // Affichage immédiat de la valeur correcte
  lbl.textContent = left + ' s';
  bar.style.width = (left / TOTAL * 100) + '%';
  bar.style.background = left <= 10 ? '#ef4444' : '#22c55e';

  if (left <= 0) {
    // Déjà expiré au chargement → soumettre immédiatement
    document.getElementById('quizForm').submit();
    return;
  }

  const t = setInterval(() => {
    left--;
    lbl.textContent = left + ' s';
    bar.style.width = (left / TOTAL * 100) + '%';
    bar.style.background = left <= 10 ? '#ef4444' : '#22c55e';

    if (left <= 0) {
      clearInterval(t);
      if (!document.getElementById('selectedAnswer').value) {
        document.getElementById('selectedAnswer').value = '';
      }
      document.getElementById('quizForm').submit();
    }
  }, 1000);
})();
<?php else: ?>
(function() {
  const qIndex = <?= $questionIndex ?>;
  const mancheCourante = <?= $manche ?>;
  let attempts = 0;
  const MAX = 90;

  const iv = setInterval(() => {
    attempts++;
    if (attempts > MAX) {
      clearInterval(iv);
      return;
    }

    fetch('etat_quiz.php?ts=' + Date.now())
      .then(r => r.json())
      .then(data => {
        if (data.error) {
          clearInterval(iv);
          return;
        }

        const rep = data.reponses || {};
        const nouvelleQuestion = Number(data.question_actuelle ?? 0);
        const nouvelleManche = Number(data.manche ?? mancheCourante);
        const manchesJ1 = Number(data.manches_gagnees_j1 ?? 0);
        const manchesJ2 = Number(data.manches_gagnees_j2 ?? 0);

        if (
          rep[qIndex] &&
          rep[qIndex]['joueur1'] !== undefined &&
          rep[qIndex]['joueur2'] !== undefined
        ) {
          clearInterval(iv);
          location.reload();
          return;
        }

        if (nouvelleManche !== mancheCourante) {
          clearInterval(iv);
          location.reload();
          return;
        }

        if (nouvelleQuestion !== qIndex) {
          clearInterval(iv);
          location.reload();
          return;
        }

        if (manchesJ1 >= 2 || manchesJ2 >= 2) {
          clearInterval(iv);
          location.reload();
          return;
        }
      })
      .catch(() => {});
  }, 1000);
})();
<?php endif; ?>

function selectAnswer(letter, btn) {
  document.querySelectorAll('.answer-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  document.getElementById('selectedAnswer').value = letter;

  const submit = document.getElementById('submitBtn');
  if (submit) {
    submit.disabled = false;
    submit.style.opacity = '1';
    submit.style.cursor = 'pointer';
  }
}

document.getElementById('quizForm')?.addEventListener('submit', function() {
  this.querySelectorAll('button').forEach(b => b.disabled = true);
});
</script>
</body>
</html>
