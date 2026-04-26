<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$roomId = (int)($_SESSION['room_id'] ?? 1);

// Petits raccourcis pour lire et sauvegarder l'état de la partie.
function lireEtatPartage() {
    global $roomId;
    return getGameState($roomId);
}

function ecrireEtatPartage($etat) {
    global $roomId;
    saveGameState($roomId, $etat);
}

// On vérifie que le joueur appartient bien à cette room.
$roomData = getRoom($roomId);
if (!($roomData['joueur1'] ?? null) || !($roomData['joueur2'] ?? null)) {
    header("Location: room.php");
    exit();
}
if ($roomData['joueur1'] !== $_SESSION['username'] && $roomData['joueur2'] !== $_SESSION['username']) {
    header("Location: login.php");
    exit();
}

// Réglages principaux du quiz.
$QUESTIONS_PAR_MANCHE = 8;
$NB_MANCHES = 3;

$defaultEtat = defaultGameState();

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

// Les questions viennent du fichier JSON.
$questions = json_decode(file_get_contents(__DIR__ . '/data/questions.json'), true);
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

// Au début d'une manche, on choisit un thème et 8 questions.
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
    $etat['question_start_time'] = time();
    ecrireEtatPartage($etat);
}

// Sécurité au cas où le timer n'aurait pas encore été lancé.
if (!isset($etat['question_start_time'])) {
    $etat['question_start_time'] = time();
    ecrireEtatPartage($etat);
}

// On sait ici si la session correspond au joueur 1 ou au joueur 2.
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
$manchesResultats = $etat['manches_resultats'] ?? [];

// Question affichée maintenant.
$questionIndex   = (int)($etat['question_actuelle'] ?? 0);
$questionsManche = $etat['questions_manches'][$mancheKey] ?? [];
$totalQuestions  = count($questionsManche);

if (advanceExpiredQuestionResult($roomId)) {
    header("Location: quiz.php");
    exit();
}

// Quand toutes les questions sont passées, on calcule le gagnant de la manche.
if ($questionIndex >= $totalQuestions && $totalQuestions > 0) {

    // On relit l'état récent, car les deux joueurs peuvent arriver ici presque en même temps.
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

    // La partie est finie dès qu'un joueur a 2 manches.
    if ($manchesJ1 >= 2 || $manchesJ2 >= 2 || $mancheActuelle > 3) {
        header("Location: score.php");
        exit();
    }

    // Sinon, on laisse l'autre joueur revenir sur la bonne page.
    $etat['manche_terminee'] = false;
    ecrireEtatPartage($etat);

    header("Location: quiz.php");
    exit();
}
        // Petit verrou pour éviter de compter deux fois la même manche.
        $etat['manche_terminee'] = true;
        ecrireEtatPartage($etat);

        // On relit une fois après le verrou.
        $etat = lireEtatPartage();
        foreach ($defaultEtat as $k => $v) {
            if (!array_key_exists($k, $etat)) {
                $etat[$k] = $v;
            }
        }

        // Si l'autre joueur a déjà avancé, on suit simplement.
        if ((int)($etat['question_actuelle'] ?? 0) < $totalQuestions) {
            header("Location: quiz.php");
            exit();
        }

        // Score final de la manche.
        $etat['manches_resultats'] = $etat['manches_resultats'] ?? [];
        if ($etat['score_joueur1'] > $etat['score_joueur2']) {
            $etat['manches_gagnees_j1']++;
            $etat['manches_resultats'][$mancheKey] = 'joueur1';
        } elseif ($etat['score_joueur2'] > $etat['score_joueur1']) {
            $etat['manches_gagnees_j2']++;
            $etat['manches_resultats'][$mancheKey] = 'joueur2';
        } else {
            $etat['manches_resultats'][$mancheKey] = 'egalite';
        }

        // Fin de partie si un joueur a gagné assez de manches.
        if (
            $etat['manches_gagnees_j1'] >= 2 ||
            $etat['manches_gagnees_j2'] >= 2 ||
            $manche >= $NB_MANCHES
        ) {
            ecrireEtatPartage($etat);
            header("Location: score.php");
            exit();
        }

        // Préparation de la manche suivante.
        $etat['manche']++;
        $etat['question_actuelle'] = 0;
        $etat['score_joueur1'] = 0;
        $etat['score_joueur2'] = 0;
        $etat['reponses'] = [];
        $etat['score_calcule'] = [];
        $etat['manche_terminee'] = false;
        $etat['theme_manche'] = null;
        $etat['question_result_until'] = null;

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
$resultatQuestion = $etat['score_calcule'][$questionIndex] ?? null;
$afficherResultat = is_array($resultatQuestion);
$lettreCorrecte = $afficherResultat ? ($resultatQuestion['correct'] ?? '') : '';
$indexCorrect = $lettreCorrecte ? (ord($lettreCorrecte) - 65) : null;
$texteBonneReponse = isset($currentQuestion['options'][$indexCorrect])
    ? $currentQuestion['options'][$indexCorrect]
    : '';
$reponseJoueur = $etat['reponses'][$questionIndex][$joueur] ?? '';
$bonneReponseJoueur = $afficherResultat && $reponseJoueur !== '' && $reponseJoueur === $lettreCorrecte;

// Timer calculé côté serveur pour éviter de tricher avec un simple refresh.
$TIMER_DUREE     = 30;
$questionStart   = (int)($etat['question_start_time'] ?? time());
$tempsEcoule     = time() - $questionStart;
$tempsRestant    = max(0, $TIMER_DUREE - $tempsEcoule);
// Si le temps est fini, le formulaire part sans réponse.
$deuxReponses    = isset($etat['reponses'][$questionIndex]['joueur1']) &&
                   isset($etat['reponses'][$questionIndex]['joueur2']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Quiz Battle – Manche <?= $manche ?></title>
  <link rel="stylesheet" href="assets/css/quiz.css?v=2"/>
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

  .answer-btn.correct {
    background: linear-gradient(135deg, #15803d, #22c55e);
    color: #f0fdf4;
    border-color: #86efac;
  }

  .answer-btn.wrong {
    background: linear-gradient(135deg, #991b1b, #ef4444);
    color: #fff7ed;
    border-color: #fecaca;
  }

  .result-msg {
    text-align: center;
    padding: 18px 0 24px;
    color: #fff7ed;
    font-weight: bold;
  }

  .result-msg.good {
    color: #86efac;
  }

  .result-msg.bad {
    color: #fecaca;
  }

  .sound-toggle {
    min-width: 48px;
    height: 48px;
    border: 1px solid rgba(250, 204, 21, 0.45);
    border-radius: 12px;
    background: rgba(41, 24, 12, 0.88);
    color: #facc15;
    cursor: pointer;
    font-size: 1.15rem;
    font-weight: bold;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.35);
  }

  .sound-toggle:hover {
    background: rgba(255, 247, 237, 0.12);
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
        <span id="timer"><?= $afficherResultat ? 'Résultat' : ($dejaRepondu ? 'Répondu ✓' : $tempsRestant . ' s') ?></span>
      </div>
      <button class="sound-toggle" id="soundToggle" type="button" title="Activer ou couper les effets">🔊</button>
    </header>

    <section class="players">
      <div class="player-card">
        <div class="avatar"><?= htmlspecialchars($avatarJoueur) ?></div>
        <h2>Vous</h2>
        <p class="player-name"><?= htmlspecialchars($nomJoueur) ?></p>
        <p class="score">Score manche : <?= $scoreJoueur ?></p>
        <div class="manches-bar" style="justify-content:center;margin-top:8px;">
          <?php for ($i = 0; $i < 3; $i++):
              $roundWinner = $manchesResultats[(string)($i + 1)] ?? null;
              $roundClass = ($roundWinner === $joueur) ? 'won' : (($roundWinner && $roundWinner !== 'egalite') ? 'lost' : '');
          ?>
            <div class="manche-dot <?= $roundClass ?>">M<?= $i + 1 ?></div>
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
          <?php for ($i = 0; $i < 3; $i++):
              $roundWinner = $manchesResultats[(string)($i + 1)] ?? null;
              $roundClass = ($roundWinner === $joueurAdv) ? 'won' : (($roundWinner && $roundWinner !== 'egalite') ? 'lost' : '');
          ?>
            <div class="manche-dot <?= $roundClass ?>">M<?= $i + 1 ?></div>
          <?php endfor; ?>
        </div>
      </div>
    </section>

    <main class="quiz-card">
      <p class="question-number">Question <?= $questionIndex + 1 ?> / <?= $totalQuestions ?></p>
      <h1 class="question"><?= htmlspecialchars($currentQuestion['question'] ?? '') ?></h1>

      <?php if ($afficherResultat): ?>
        <div class="result-msg <?= $bonneReponseJoueur ? 'good' : 'bad' ?>">
          <?= $bonneReponseJoueur ? 'Bonne réponse !' : 'Mauvaise réponse.' ?>
          <?php if ($lettreCorrecte): ?>
            La bonne réponse était <?= htmlspecialchars($lettreCorrecte) ?>
            <?php if ($texteBonneReponse): ?>
              : <?= htmlspecialchars($texteBonneReponse) ?>
            <?php endif; ?>.
          <?php endif; ?>
        </div>

        <div class="answers" style="margin-top:10px;pointer-events:none;">
          <?php foreach ($currentQuestion['options'] as $i => $option):
              $lettre = chr(65 + $i);
              $cls = 'disabled';
              if ($lettre === $lettreCorrecte) {
                  $cls .= ' correct';
              } elseif ($lettre === $reponseJoueur) {
                  $cls .= ' wrong';
              }
          ?>
            <button class="answer-btn <?= $cls ?>" disabled>
              <?= $lettre ?>. <?= htmlspecialchars($option) ?>
            </button>
          <?php endforeach; ?>
        </div>

        <script>
          setTimeout(() => location.reload(), 3200);
        </script>

      <?php elseif ($deuxReponses): ?>
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
let soundMuted = localStorage.getItem('quiz_sound_muted') === '1';
let soundCtx = null;
let lastTickSecond = null;

function updateSoundButton() {
  const btn = document.getElementById('soundToggle');
  if (btn) {
    btn.textContent = soundMuted ? '🔇' : '🔊';
  }
}

function getAudioContext() {
  soundCtx = soundCtx || new (window.AudioContext || window.webkitAudioContext)();
  if (soundCtx.state === 'suspended') {
    soundCtx.resume();
  }
  return soundCtx;
}

function playTone(freq, duration, type = 'sine', volume = 0.05, delay = 0) {
  if (soundMuted) return;

  const ctx = getAudioContext();
  const osc = soundCtx.createOscillator();
  const gain = soundCtx.createGain();
  const startAt = ctx.currentTime + delay;

  osc.type = type;
  osc.frequency.value = freq;
  gain.gain.setValueAtTime(0.001, startAt);
  gain.gain.exponentialRampToValueAtTime(volume, startAt + 0.015);
  gain.gain.exponentialRampToValueAtTime(0.001, startAt + duration);

  osc.connect(gain);
  gain.connect(ctx.destination);

  osc.start(startAt);
  osc.stop(startAt + duration + 0.02);
}

function playSound(name) {
  if (name === 'select') {
    playTone(460, 0.06, 'sine', 0.022);
    playTone(620, 0.07, 'sine', 0.018, 0.05);
  } else if (name === 'good') {
    playTone(520, 0.08, 'sine', 0.028);
    playTone(660, 0.10, 'sine', 0.03, 0.08);
    playTone(880, 0.12, 'sine', 0.032, 0.17);
  } else if (name === 'bad') {
    playTone(260, 0.11, 'sine', 0.026);
    playTone(210, 0.16, 'sine', 0.022, 0.10);
  } else if (name === 'tick') {
    playTone(720, 0.035, 'sine', 0.012);
  }
}

document.getElementById('soundToggle')?.addEventListener('click', function() {
  soundMuted = !soundMuted;
  localStorage.setItem('quiz_sound_muted', soundMuted ? '1' : '0');
  updateSoundButton();
  if (!soundMuted) playSound('select');
});

updateSoundButton();

<?php if ($afficherResultat): ?>
window.addEventListener('load', () => {
  playSound(<?= json_encode($bonneReponseJoueur ? 'good' : 'bad') ?>);
});
<?php endif; ?>

<?php if (!$dejaRepondu && !$afficherResultat): ?>
(function() {
  const TOTAL = 30;
  // Valeur envoyée par PHP, donc un refresh ne redonne pas 30 secondes.
  let left = <?= $tempsRestant ?>;

  const lbl = document.getElementById('timer');
  const bar = document.getElementById('timerBar');

  lbl.textContent = left + ' s';
  bar.style.width = (left / TOTAL * 100) + '%';
  bar.style.background = left <= 10 ? '#ef4444' : '#22c55e';

  if (left <= 0) {
    document.getElementById('quizForm').submit();
    return;
  }

  const t = setInterval(() => {
    left--;
    lbl.textContent = left + ' s';
    bar.style.width = (left / TOTAL * 100) + '%';
    bar.style.background = left <= 10 ? '#ef4444' : '#22c55e';

    if (left <= 5 && left > 0 && lastTickSecond !== left) {
      lastTickSecond = left;
      playSound('tick');
    }

    if (left <= 0) {
      clearInterval(t);
      if (!document.getElementById('selectedAnswer').value) {
        document.getElementById('selectedAnswer').value = '';
      }
      document.getElementById('quizForm').submit();
    }
  }, 1000);
})();
<?php elseif (!$afficherResultat): ?>
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
          if (data.error === 'room_missing') {
            location.href = 'room.php';
          }
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
  playSound('select');
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
<script src="assets/js/music.js"></script>
</body>
</html>
