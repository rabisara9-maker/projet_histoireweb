<?php
session_start();

// Vérifier les joueurs depuis le fichier partagé de la room
$roomId = $_SESSION['room_id'] ?? 1;
$sharedRoomFile = "shared_room_{$roomId}.json";
$room = lireEtatPartage(); // Réutiliser la fonction, mais pour room
if (!file_exists($sharedRoomFile)) {
    header("Location: room.php");
    exit();
}
$roomData = json_decode(file_get_contents($sharedRoomFile), true);
if (!$roomData['joueur1'] || !$roomData['joueur2']) {
    header("Location: room.php");
    exit();
}

// Fichier partagé
$sharedFile = "shared_game_{$roomId}.json";
function lireEtatPartage() {
    global $sharedFile;
    if (file_exists($sharedFile)) {
        return json_decode(file_get_contents($sharedFile), true);
    }
    return [];
}
function ecrireEtatPartage($etat) {
    global $sharedFile;
    file_put_contents($sharedFile, json_encode($etat));
}

$etat = lireEtatPartage();
if (empty($etat)) {
    $etat = [
        'manche' => 1,
        'score_joueur1' => 0,
        'score_joueur2' => 0,
        'questions_manches' => [],
        'theme_manche' => null,
        'question_actuelle' => 0,
        'reponses' => [],
        'start_time' => time() + 5  // Démarre dans 5 secondes
    ];
}

// Lire les questions depuis le JSON
$questionsJson = file_get_contents('questions.json');
$questions = json_decode($questionsJson, true);

// Liste des thèmes
$themes = ['Antiquité', 'Moyen Âge', 'Grandes découvertes', 'Histoire de France', 'Personnages historiques', 'Guerres mondiales'];

// Fonctions...
function choisirThemeAleatoire($themes) {
    return $themes[rand(0, count($themes) - 1)];
}
function filtrerQuestionsParTheme($questions, $theme) {
    return array_filter($questions, function($q) use ($theme) {
        return $q['theme'] === $theme;
    });
}
function selectionnerQuestionsAleatoires($questionsFiltrees, $nombre) {
    shuffle($questionsFiltrees);
    return array_slice($questionsFiltrees, 0, $nombre);
}
function melangerOptions($question) {
    $options = ['A' => $question['a'], 'B' => $question['b'], 'C' => $question['c']];
    $cles = array_keys($options);
    shuffle($cles);
    $optionsMelangees = [];
    foreach ($cles as $cle) {
        $optionsMelangees[] = $options[$cle];
    }
    $nouvellePositionCorrecte = array_search('A', $cles) + 1;
    return [
        'question' => $question['question'],
        'options' => $optionsMelangees,
        'correct' => $nouvellePositionCorrecte
    ];
}

// Initialiser les questions si nouvelle manche
$manche = $etat['manche'];
if (!isset($etat['questions_manches'][$manche])) {
    $themeChoisi = choisirThemeAleatoire($themes);
    $etat['theme_manche'] = $themeChoisi;
    $questionsFiltrees = filtrerQuestionsParTheme($questions, $themeChoisi);
    $questionsSelectionnees = selectionnerQuestionsAleatoires($questionsFiltrees, 8);
    
    $etat['questions_manches'][$manche] = [];
    foreach ($questionsSelectionnees as $q) {
        $etat['questions_manches'][$manche][] = melangerOptions($q);
    }
    ecrireEtatPartage($etat);
}

// Afficher la question actuelle
$questionIndex = $etat['question_actuelle'];
$questionsManche = $etat['questions_manches'][$manche];

// Initialiser le start_time pour cette question si pas encore fait
if (!isset($etat['question_start_time'])) {
    $etat['question_start_time'] = time();
    ecrireEtatPartage($etat);
}

if ($questionIndex >= count($questionsManche)) {
    // Toutes les questions répondues, passer à la manche suivante
    if ($manche < 3) {
        $etat['manche']++;
        $etat['question_actuelle'] = 0;
        $etat['reponses'] = [];
        ecrireEtatPartage($etat);
        header("Location: quiz.php");
        exit();
    } else {
        header("Location: score.php");
        exit();
    }
}

$currentQuestion = $questionsManche[$questionIndex];
$theme = $etat['theme_manche'];

// Identifier le joueur
$joueur = ($roomData['joueur1'] == $_SESSION['username']) ? 'joueur1' : 'joueur2';
$joueurAdverse = ($joueur == 'joueur1') ? 'joueur2' : 'joueur1';
$nomJoueur = $roomData[$joueur];
$nomAdverse = $roomData[$joueurAdverse];
$scoreJoueur = $etat['score_' . $joueur];
$scoreAdverse = $etat['score_' . $joueurAdverse];

// Timer JavaScript (30 secondes) - calculer le temps restant depuis le début de la question
$timerScript = '';
if (!isset($etat['reponses'][$questionIndex][$joueur])) {
    $elapsedTime = time() - $etat['question_start_time'];
    $timeLeft = max(0, 30 - $elapsedTime);
    
    if ($timeLeft > 0) {
        $timerScript = "<script>
        let timeLeft = {$timeLeft};
        const timerElement = document.getElementById('timer');
        const countdown = setInterval(() => {
            timeLeft--;
            timerElement.textContent = 'Temps restant : ' + timeLeft + ' secondes';
            if (timeLeft <= 0) {
                clearInterval(countdown);
                // Auto-soumettre une réponse vide
                document.getElementById('quizForm').submit();
            }
        }, 1000);
        </script>";
    } else {
        // Temps écoulé, soumettre automatiquement
        $timerScript = "<script>
        document.getElementById('quizForm').submit();
        </script>";
    }
}

// Vérifier si les deux joueurs ont répondu à cette question
$deuxReponses = isset($etat['reponses'][$questionIndex]['joueur1']) && isset($etat['reponses'][$questionIndex]['joueur2']);

?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Quiz Battle - Quiz</title>
  <link rel="stylesheet" href="quiz.css" />
</head>

<body>
  <div class="page">
    <div class="quiz-layout">
      <header class="top-bar">
        <div class="top-left">
          <span class="badge">Quiz <?php echo $manche; ?> / 3</span>
          <span class="badge">Thème : <?php echo $theme; ?></span>
        </div>

        <div class="timer-box">
          <p>Temps restant</p>
          <div class="timer-bar">
            <div class="timer-fill"></div>
          </div>
          <span id="timer"><?php echo isset($etat['reponses'][$questionIndex][$joueur]) ? '' : '30 s'; ?></span>
        </div>
      </header>

      <section class="players">
        <div class="player-card">
          <div class="avatar">👤</div>
          <h2><?php echo ($joueur == 'joueur1') ? 'Vous' : 'Adversaire'; ?></h2>
          <p class="player-name"><?php echo $nomJoueur; ?></p>
          <p class="score">Score : <?php echo $scoreJoueur; ?></p>
        </div>

        <div class="versus">VS</div>

        <div class="player-card">
          <div class="avatar">👤</div>
          <h2><?php echo ($joueur == 'joueur2') ? 'Vous' : 'Adversaire'; ?></h2>
          <p class="player-name"><?php echo $nomAdverse; ?></p>
          <p class="score">Score : <?php echo $scoreAdverse; ?></p>
        </div>
      </section>

      <main class="quiz-card">
        <p class="question-number">Question <?php echo ($questionIndex + 1); ?> / <?php echo count($questionsManche); ?></p>

        <h1 class="question"><?php echo $currentQuestion['question']; ?></h1>

        <?php if ($deuxReponses): ?>
          <div class="waiting">
            <p>Les deux joueurs ont répondu. Passage à la suivante...</p>
          </div>
        <?php else: ?>
          <form id="quizForm" action="verifier_reponses.php" method="POST">
            <div class="answers">
              <?php foreach ($currentQuestion['options'] as $i => $option): ?>
                <?php $lettre = chr(65 + $i); ?>
                <button class="answer-btn" type="button" onclick="selectAnswer('<?php echo $lettre; ?>')"><?php echo $lettre; ?>. <?php echo $option; ?></button>
              <?php endforeach; ?>
            </div>

            <input type="hidden" name="reponse" id="selectedAnswer" value="">
            <input type="hidden" name="question_index" value="<?php echo $questionIndex; ?>">

            <div class="action-box">
              <button class="next-btn" type="submit">Soumettre</button>
            </div>
          </form>
        <?php endif; ?>
      </main>
    </div>
  </div>

  <?php echo $timerScript; ?>

  <script src="quiz.js"></script>
  <script>
    function selectAnswer(answer) {
      // Remove 'selected' class from all answer buttons
      document.querySelectorAll('.answer-btn').forEach(btn => btn.classList.remove('selected'));
      
      // Add 'selected' class to the clicked button
      event.target.classList.add('selected');
      
      // Set the hidden input value
      document.getElementById('selectedAnswer').value = answer;
    }
  </script>
</body>

</html>
