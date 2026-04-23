<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$roomId = $_SESSION['room_id'] ?? 1;
$sharedFile = "shared_game_{$roomId}.json";
$sharedRoomFile = "shared_room_{$roomId}.json";

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
    $tmp = $sharedFile . '.tmp.' . getmypid();
    file_put_contents($tmp, json_encode($etat));
    rename($tmp, $sharedFile);
}

if (!file_exists($sharedRoomFile)) {
    header("Location: room.php");
    exit();
}

$roomData = json_decode(file_get_contents($sharedRoomFile), true);
if (!is_array($roomData)) {
    header("Location: room.php");
    exit();
}

$joueur = ($roomData['joueur1'] === $_SESSION['username']) ? 'joueur1' : 'joueur2';

$reponsePost = $_POST['reponse'] ?? '';
$questionIndexP = (int)($_POST['question_index'] ?? 0);
$reponsePropre = in_array($reponsePost, ['A', 'B', 'C', '']) ? $reponsePost : '';

// Lire l’état courant
$etat = lireEtatPartage();
if (empty($etat)) {
    header("Location: quiz.php");
    exit();
}

$manche = (int)($etat['manche'] ?? 1);
$mancheKey = (string)$manche;
$questionIndex = (int)($etat['question_actuelle'] ?? 0);

// Si on n'est plus sur la bonne question, on recharge
if ($questionIndexP !== $questionIndex) {
    header("Location: quiz.php");
    exit();
}

// Initialisations
if (!isset($etat['reponses']) || !is_array($etat['reponses'])) {
    $etat['reponses'] = [];
}
if (!isset($etat['reponses'][$questionIndex]) || !is_array($etat['reponses'][$questionIndex])) {
    $etat['reponses'][$questionIndex] = [];
}
if (!isset($etat['score_calcule']) || !is_array($etat['score_calcule'])) {
    $etat['score_calcule'] = [];
}
if (!isset($etat['score_joueur1'])) {
    $etat['score_joueur1'] = 0;
}
if (!isset($etat['score_joueur2'])) {
    $etat['score_joueur2'] = 0;
}
if (!isset($etat['manche_terminee'])) {
    $etat['manche_terminee'] = false;
}

// Si la manche est déjà terminée, ne plus rien traiter
if (!empty($etat['manche_terminee'])) {
    header("Location: quiz.php");
    exit();
}

// Si ce joueur a déjà répondu, ne pas dupliquer
if (isset($etat['reponses'][$questionIndex][$joueur])) {
    header("Location: quiz.php");
    exit();
}

// Enregistrer la réponse du joueur
$etat['reponses'][$questionIndex][$joueur] = $reponsePropre;
ecrireEtatPartage($etat);

// Relecture immédiate après écriture
$etat = lireEtatPartage();

$manche = (int)($etat['manche'] ?? 1);
$mancheKey = (string)$manche;
$questionIndex = (int)($etat['question_actuelle'] ?? 0);

$j1Repondu = isset($etat['reponses'][$questionIndex]['joueur1']);
$j2Repondu = isset($etat['reponses'][$questionIndex]['joueur2']);

if ($j1Repondu && $j2Repondu) {
    // Recalculer depuis le dernier état pour éviter les conflits
    if (!isset($etat['score_calcule'][$questionIndex])) {
        $questionsManche = $etat['questions_manches'][$mancheKey] ?? [];
        $currentQuestion = $questionsManche[$questionIndex] ?? null;

        if (is_array($currentQuestion)) {
            $lettreCorrecte = chr(64 + (int)($currentQuestion['correct'] ?? 1));

            $rep1 = $etat['reponses'][$questionIndex]['joueur1'] ?? '';
            $rep2 = $etat['reponses'][$questionIndex]['joueur2'] ?? '';

            if ($rep1 !== '' && $rep1 === $lettreCorrecte) {
                $etat['score_joueur1'] = (int)$etat['score_joueur1'] + 1;
            }
            if ($rep2 !== '' && $rep2 === $lettreCorrecte) {
                $etat['score_joueur2'] = (int)$etat['score_joueur2'] + 1;
            }

            $etat['score_calcule'][$questionIndex] = true;

            // Avancer UNE SEULE FOIS à la question suivante
            if ((int)($etat['question_actuelle'] ?? 0) === $questionIndex) {
                $etat['question_actuelle'] = $questionIndex + 1;
            }

            ecrireEtatPartage($etat);
        }
    }
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    header("Location: quiz.php");
}
exit();
