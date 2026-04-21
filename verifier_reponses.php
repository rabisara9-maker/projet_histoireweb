<?php
session_start();

// Fichier partagé
$roomId = $_SESSION['room_id'] ?? 1;
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

// Ensure required keys exist
if (empty($etat)) {
    $etat = [
        'manche' => 1,
        'score_joueur1' => 0,
        'score_joueur2' => 0,
        'questions_manches' => [],
        'question_actuelle' => 0,
        'reponses' => []
    ];
}

$manche = $etat['manche'] ?? 1;
$questionIndex = $etat['question_actuelle'] ?? 0;
$questionsManche = $etat['questions_manches'][$manche] ?? [];
$currentQuestion = $questionsManche[$questionIndex] ?? [];

// Identifier le joueur actuel
$sharedRoomFile = "shared_room_{$roomId}.json";
if (!file_exists($sharedRoomFile)) {
    header("Location: room.php");
    exit();
}
$roomData = json_decode(file_get_contents($sharedRoomFile), true);
// Initialize reponses array if needed
if (!isset($etat['reponses'])) {
    $etat['reponses'] = [];
}
if (!isset($etat['reponses'][$questionIndex])) {
    $etat['reponses'][$questionIndex] = [];
}

$joueur = ($roomData['joueur1'] == $_SESSION['username']) ? 'joueur1' : 'joueur2';

// Stocker la réponse du joueur pour cette question (écrase si déjà répondu)
$reponse = $_POST['reponse'] ?? '';
$etat['reponses'][$questionIndex][$joueur] = $reponse;

// Vérifier si les deux joueurs ont répondu
if (isset($etat['reponses'][$questionIndex]['joueur1']) && isset($etat['reponses'][$questionIndex]['joueur2'])) {
    // Calculate points
    $lettreCorrecte = chr(64 + ($currentQuestion['correct'] ?? 1));
    if (!empty($etat['reponses'][$questionIndex]['joueur1']) && $etat['reponses'][$questionIndex]['joueur1'] === $lettreCorrecte) {
        $etat['score_joueur1'] = ($etat['score_joueur1'] ?? 0) + 1;
    }
    if (!empty($etat['reponses'][$questionIndex]['joueur2']) && $etat['reponses'][$questionIndex]['joueur2'] === $lettreCorrecte) {
        $etat['score_joueur2'] = ($etat['score_joueur2'] ?? 0) + 1;
    }
    
    // Passer à la question suivante
    $etat['question_actuelle']++;
}

// Sauvegarder
ecrireEtatPartage($etat);

// Vérifier si c'est une requête AJAX
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    // Retourner JSON pour les requêtes AJAX
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    // Rediriger pour les requêtes normales
    header("Location: quiz.php");
}
exit();
?>

