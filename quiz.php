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
// CORRECTION BUG #1 : fonctions définies AVANT leur premier appel
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
    // CORRECTION BUG #3 : écriture atomique pour éviter la race condition
    $tmp = $sharedFile . '.tmp.' . getmypid();
    file_put_contents($tmp, json_encode($etat));
    rename($tmp, $sharedFile);
}

// ── Vérifications session ────────────────────────────────────────────────────
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
$NB_MANCHES           = 3;

$defaultEtat = [
    'manche'            => 1,
    'score_joueur1'     => 0,
    'score_joueur2'     => 0,
    'manches_gagnees_j1' => 0,
    'manches_gagnees_j2' => 0,
    'questions_manches' => [],
    'theme_manche'      => null,
    'question_actuelle' => 0,
    'reponses'          => [],
    'score_calcule'     => [],   // CORRECTION BUG #4 : marquer les questions déjà scorées
    'start_time'        => time() + 3,
];

$etat = lireEtatPartage();
if (empty($etat)) {
    $etat = $defaultEtat;
} else {
    // Fusionner avec les clés par défaut sans écraser les valeurs existantes
    foreach ($defaultEtat as $k => $v) {
        if (!array_key_exists($k, $etat)) $etat[$k] = $v;
    }
}

// ── Chargement des questions ─────────────────────────────────────────────────
$questions = json_decode(file_get_contents('questions.json'), true);
$themes    = array_values(array_unique(array_column($questions, 'theme')));

function choisirThemeAleatoire($themes, $themesUtilises = []) {
    $dispo = array_diff($themes, $themesUtilises);
    if (empty($dispo)) $dispo = $themes; // réutiliser si tous épuisés
    $dispo = array_values($dispo);
    return $dispo[rand(0, count($dispo) - 1)];
}
function filtrerQuestionsParTheme($questions, $theme) {
    return array_values(array_filter($questions, fn($q) => $q['theme'] === $theme));
}
function melangerOptions($question) {
    // Mélange A/B/C et recalcule la position correcte
    $options = ['A' => $question['a'], 'B' => $question['b'], 'C' => $question['c']];
    $cles    = array_keys($options);
    shuffle($cles);
    $optionsMelangees        = array_map(fn($k) => $options[$k], $cles);
    $nouvellePositionCorrecte = array_search('A', $cles) + 1; // 'A' est toujours la bonne réponse dans le JSON
    return [
        'question' => $question['question'],
        'options'  => $optionsMelangees,
        'correct'  => $nouvellePositionCorrecte,
    ];
}

// ── Initialiser les questions de la manche courante ──────────────────────────
$manche     = (int)($etat['manche'] ?? 1);
$mancheKey  = (string)$manche; // CORRECTION BUG #2 : toujours utiliser string comme clé JSON

if (!isset($etat['questions_manches'][$mancheKey])) {
    $themesUtilises = $etat['themes_utilises'] ?? [];
    $themeChoisi    = choisirThemeAleatoire($themes, $themesUtilises);

    $etat['themes_utilises'][]         = $themeChoisi;
    $etat['theme_manche']              = $themeChoisi;
    $etat['question_actuelle']         = 0;
    $etat['reponses']                  = [];
    $etat['score_calcule']             = [];

    $questionsFiltrees     = filtrerQuestionsParTheme($questions, $themeChoisi);
    shuffle($questionsFiltrees);
    $questionsSelectionnees = array_slice($questionsFiltrees, 0, $QUESTIONS_PAR_MANCHE);

    $etat['questions_manches'][$mancheKey] = array_map('melangerOptions', $questionsSelectionnees);
    ecrireEtatPartage($etat);
}

// ── Identité du joueur courant ────────────────────────────────────────────────
$joueur      = ($roomData['joueur1'] === $_SESSION['username']) ? 'joueur1' : 'joueur2';
$joueurAdv   = ($joueur === 'joueur1') ? 'joueur2' : 'joueur1';
$nomJoueur   = $roomData[$joueur]  ?? 'Joueur';
$nomAdverse  = $roomData[$joueurAdv] ?? 'Adversaire';
$avatarJoueur = $roomData['avatar' . ($joueur === 'joueur1' ? '1' : '2')] ?? '👤';
$avatarAdverse = $roomData['avatar' . ($joueur === 'joueur1' ? '2' : '1')] ?? '👤';
$scoreJoueur = (int)($etat['score_' . $joueur]  ?? 0);
$scoreAdverse= (int)($etat['score_' . $joueurAdv] ?? 0);
$manchesJ    = (int)($etat['manches_gagnees_' . ($joueur === 'joueur1' ? 'j1' : 'j2')] ?? 0);
$manchesAdv  = (int)($etat['manches_gagnees_' . ($joueur === 'joueur1' ? 'j2' : 'j1')] ?? 0);

// ── Question actuelle ─────────────────────────────────────────────────────────
$questionIndex  = (int)($etat['question_actuelle'] ?? 0);
$questionsManche= $etat['questions_manches'][$mancheKey] ?? [];
$totalQuestions = count($questionsManche);

// Fin de manche
if ($questionIndex >= $totalQuestions && $totalQuestions > 0) {
    // Déterminer qui a gagné la manche
    if ($etat['score_joueur1'] > $etat['score_joueur2'])      $etat['manches_gagnees_j1']++;
    elseif ($etat['score_joueur2'] > $etat['score_joueur1'])  $etat['manches_gagnees_j2']++;
    // sinon égalité = personne ne gagne la manche

    // CORRECTION BUG #5 : vérifier victoire AVANT d'incrémenter la manche
    if ($etat['manches_gagnees_j1'] >= 2 || $etat['manches_gagnees_j2'] >= 2 || $manche >= $NB_MANCHES) {
        ecrireEtatPartage($etat);
        header("Location: score.php");
        exit();
    }

    // Prochaine manche
    $etat['manche']++;
    $etat['question_actuelle'] = 0;
    $etat['score_joueur1']     = 0;
    $etat['score_joueur2']     = 0;
    $etat['reponses']          = [];
    $etat['score_calcule']     = [];
    ecrireEtatPartage($etat);
    header("Location: quiz.php");
    exit();
}

$currentQuestion = $questionsManche[$questionIndex] ?? [];
$theme           = $etat['theme_manche'] ?? 'Thème';
$dejaRepondu     = isset($etat['reponses'][$questionIndex][$joueur]);
$deuxReponses    = isset($etat['reponses'][$questionIndex]['joueur1'])
                && isset($etat['reponses'][$questionIndex]['joueur2']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Quiz Battle – Manche <?= $manche ?></title>
  <link rel="stylesheet" href="quiz.css"/>
  <style>
    /* Attente adversaire */
    .waiting-msg { text-align:center; padding:28px 0; }
    .waiting-msg .spinner {
      display:inline-block; width:36px; height:36px;
      border:4px solid rgba(255,255,255,0.2);
      border-top-color:#22c55e; border-radius:50%;
      animation:spin .8s linear infinite; margin-bottom:12px;
    }
    @keyframes spin { to { transform:rotate(360deg); } }
    /* Manches */
    .manches-bar { display:flex; gap:8px; align-items:center; }
    .manche-dot  { width:22px;height:22px;border-radius:50%;border:2px solid rgba(255,255,255,.3);
                   display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:bold; }
    .manche-dot.won  { background:#22c55e;border-color:#22c55e; }
    .manche-dot.lost { background:#ef4444;border-color:#ef4444; }
    /* Réponse déjà choisie */
    .answer-btn.disabled { opacity:.55; cursor:not-allowed; }
    .answer-btn.already  { background:#3b82f6; color:#fff; }
  </style>
</head>
<body>
<div class="page">
  <div class="quiz-layout">

    <!-- TOP BAR -->
    <header class="top-bar">
      <div class="top-left">
        <span class="badge">Manche <?= $manche ?> / 3</span>
        <span class="badge">📚 <?= htmlspecialchars($theme) ?></span>
      </div>
      <div class="timer-box">
        <p>Temps restant</p>
        <div class="timer-bar"><div class="timer-fill" id="timerBar"></div></div>
        <span id="timer"><?= $dejaRepondu ? 'Répondu ✓' : '30 s' ?></span>
      </div>
    </header>

    <!-- JOUEURS -->
    <section class="players">
      <div class="player-card">
        <div class="avatar"><?= htmlspecialchars($avatarJoueur) ?></div>
        <h2>Vous</h2>
        <p class="player-name"><?= htmlspecialchars($nomJoueur) ?></p>
        <p class="score">Score manche : <?= $scoreJoueur ?></p>
        <div class="manches-bar" style="justify-content:center;margin-top:8px;">
          <?php for ($i=0;$i<3;$i++): $won=($i<$manchesJ); $lost=($i<$manchesAdv); ?>
            <div class="manche-dot <?= $won?'won':'' ?>">M<?= $i+1 ?></div>
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
          <?php for ($i=0;$i<3;$i++): $won=($i<$manchesAdv); ?>
            <div class="manche-dot <?= $won?'won':'' ?>">M<?= $i+1 ?></div>
          <?php endfor; ?>
        </div>
      </div>
    </section>

    <!-- QUESTION -->
    <main class="quiz-card">
      <p class="question-number">Question <?= $questionIndex+1 ?> / <?= $totalQuestions ?></p>
      <h1 class="question"><?= htmlspecialchars($currentQuestion['question'] ?? '') ?></h1>

      <?php if ($deuxReponses): ?>
        <!-- Les deux ont répondu → auto-reload -->
        <div class="waiting-msg">
          <div class="spinner"></div>
          <p>Les deux joueurs ont répondu. Passage à la suivante…</p>
        </div>
        <script>setTimeout(() => location.reload(), 1200);</script>

      <?php elseif ($dejaRepondu): ?>
        <!-- Ce joueur a déjà répondu, attend l'adversaire -->
        <div class="waiting-msg" data-question-index="<?= $questionIndex ?>">
          <div class="spinner"></div>
          <p>En attente de l'adversaire…</p>
        </div>
        <!-- Afficher les options grisées avec sa réponse surlignée -->
        <div class="answers" style="margin-top:20px;pointer-events:none;">
          <?php
          $reponseJoueur = $etat['reponses'][$questionIndex][$joueur] ?? '';
          foreach ($currentQuestion['options'] as $i => $option):
            $lettre = chr(65 + $i);
            $cls    = 'disabled' . ($lettre === $reponseJoueur ? ' already' : '');
          ?>
            <button class="answer-btn <?= $cls ?>" disabled><?= $lettre ?>. <?= htmlspecialchars($option) ?></button>
          <?php endforeach; ?>
        </div>

      <?php else: ?>
        <!-- Formulaire de réponse -->
        <form id="quizForm" action="verifier_reponses.php" method="POST">
          <div class="answers">
            <?php foreach ($currentQuestion['options'] as $i => $option): ?>
              <?php $lettre = chr(65 + $i); ?>
              <button class="answer-btn" type="button"
                      onclick="selectAnswer('<?= $lettre ?>', this)">
                <?= $lettre ?>. <?= htmlspecialchars($option) ?>
              </button>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="reponse"        id="selectedAnswer" value="">
          <input type="hidden" name="question_index" value="<?= $questionIndex ?>">
          <div class="action-box">
            <button class="next-btn" type="submit" id="submitBtn" disabled
                    style="opacity:.5;cursor:not-allowed">
              Soumettre la réponse
            </button>
          </div>
        </form>
      <?php endif; ?>
    </main>

  </div>
</div>

<script>
// ── Timer ──────────────────────────────────────────────────────────────────
<?php if (!$dejaRepondu): ?>
(function() {
  const TOTAL = 30;
  let left    = TOTAL;
  const lbl   = document.getElementById('timer');
  const bar   = document.getElementById('timerBar');

  const t = setInterval(() => {
    left--;
    lbl.textContent = left + ' s';
    bar.style.width = (left / TOTAL * 100) + '%';
    bar.style.background = left <= 10 ? '#ef4444' : '#22c55e';
    if (left <= 0) {
      clearInterval(t);
      // Soumettre réponse vide si rien sélectionné
      if (!document.getElementById('selectedAnswer').value) {
        document.getElementById('selectedAnswer').value = '';
      }
      document.getElementById('quizForm').submit();
    }
  }, 1000);
})();
<?php else: ?>
// CORRECTION BUG #6 : polling limité, s'arrête dès que l'adversaire a répondu
(function() {
  const qIndex = <?= $questionIndex ?>;
  let attempts = 0;
  const MAX    = 60; // max 60 secondes d'attente

  const iv = setInterval(() => {
    attempts++;
    if (attempts > MAX) { clearInterval(iv); return; }

    fetch('etat_quiz.php')
      .then(r => r.json())
      .then(data => {
        const rep = data.reponses;
        if (rep && rep[qIndex] &&
            rep[qIndex]['joueur1'] !== undefined &&
            rep[qIndex]['joueur2'] !== undefined) {
          clearInterval(iv);
          location.reload();
        }
        // Si la question a avancé (l'autre a soumis et avancé)
        if ((data.question_actuelle ?? 0) > qIndex) {
          clearInterval(iv);
          location.reload();
        }
      })
      .catch(() => {}); // ignorer les erreurs réseau silencieusement
  }, 1500);
})();
<?php endif; ?>

// ── Sélection de réponse ───────────────────────────────────────────────────
function selectAnswer(letter, btn) {
  document.querySelectorAll('.answer-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  document.getElementById('selectedAnswer').value = letter;
  const submit = document.getElementById('submitBtn');
  if (submit) {
    submit.disabled = false;
    submit.style.opacity  = '1';
    submit.style.cursor   = 'pointer';
  }
}

// Empêcher double-soumission
document.getElementById('quizForm')?.addEventListener('submit', function() {
  this.querySelectorAll('button').forEach(b => b.disabled = true);
});
</script>
</body>
</html>
