<?php
session_start();

$roomId     = $_SESSION['room_id'] ?? 1;
$sharedFile = "shared_game_{$roomId}.json";

function lireEtatPartage() {
    global $sharedFile;
    if (file_exists($sharedFile)) {
        $d = json_decode(file_get_contents($sharedFile), true);
        return is_array($d) ? $d : [];
    }
    return [];
}
function ecrireEtatPartage($etat) {
    global $sharedFile;
    // CORRECTION BUG #3 : écriture atomique (rename est atomique sur même FS)
    $tmp = $sharedFile . '.tmp.' . getmypid();
    file_put_contents($tmp, json_encode($etat));
    rename($tmp, $sharedFile);
}

// ── Validation de l'entrée ───────────────────────────────────────────────────
$sharedRoomFile = "shared_room_{$roomId}.json";
if (!file_exists($sharedRoomFile)) {
    header("Location: room.php"); exit();
}
$roomData = json_decode(file_get_contents($sharedRoomFile), true);
$joueur   = ($roomData['joueur1'] === $_SESSION['username']) ? 'joueur1' : 'joueur2';

$reponsePost    = $_POST['reponse']        ?? '';
$questionIndexP = (int)($_POST['question_index'] ?? 0);

// Valider que la réponse est A, B ou C (ou vide pour timeout)
$reponsePropre = in_array($reponsePost, ['A','B','C','']) ? $reponsePost : '';

// ── Lecture de l'état ─────────────────────────────────────────────────────────
$etat          = lireEtatPartage();
$manche        = (int)($etat['manche'] ?? 1);
$mancheKey     = (string)$manche;
$questionIndex = (int)($etat['question_actuelle'] ?? 0);

// Ignorer si la question soumise ne correspond plus à la courante (double-soumission)
if ($questionIndexP !== $questionIndex) {
    header("Location: quiz.php"); exit();
}

// Initialiser les tableaux si nécessaire
if (!isset($etat['reponses']))                    $etat['reponses']      = [];
if (!isset($etat['reponses'][$questionIndex]))    $etat['reponses'][$questionIndex] = [];
if (!isset($etat['score_calcule']))               $etat['score_calcule'] = [];

// Ignorer si ce joueur a déjà répondu à cette question
if (isset($etat['reponses'][$questionIndex][$joueur])) {
    header("Location: quiz.php"); exit();
}

// Enregistrer la réponse
$etat['reponses'][$questionIndex][$joueur] = $reponsePropre;

// ── Vérifier si les deux joueurs ont répondu ──────────────────────────────────
$j1Repondu = isset($etat['reponses'][$questionIndex]['joueur1']);
$j2Repondu = isset($etat['reponses'][$questionIndex]['joueur2']);

if ($j1Repondu && $j2Repondu) {
    // CORRECTION BUG #4 : vérifier qu'on n'a pas déjà calculé le score pour cette question
    if (!isset($etat['score_calcule'][$questionIndex])) {
        $questionsManche = $etat['questions_manches'][$mancheKey] ?? [];
        $currentQuestion = $questionsManche[$questionIndex] ?? [];
        $lettreCorrecte  = chr(64 + (int)($currentQuestion['correct'] ?? 1));

        $rep1 = $etat['reponses'][$questionIndex]['joueur1'];
        $rep2 = $etat['reponses'][$questionIndex]['joueur2'];

        if ($rep1 !== '' && $rep1 === $lettreCorrecte) {
            $etat['score_joueur1'] = (int)($etat['score_joueur1'] ?? 0) + 1;
        }
        if ($rep2 !== '' && $rep2 === $lettreCorrecte) {
            $etat['score_joueur2'] = (int)($etat['score_joueur2'] ?? 0) + 1;
        }

        // Marquer cette question comme scorée
        $etat['score_calcule'][$questionIndex] = true;

        // Avancer à la question suivante
        $etat['question_actuelle']++;
    }
}

ecrireEtatPartage($etat);

// Réponse JSON pour AJAX, redirect sinon
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    header("Location: quiz.php");
}
exit();
