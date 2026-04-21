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
$manche = $etat['manche'];
$questionIndex = $etat['question_actuelle'];
$questionsManche = $etat['questions_manches'][$manche];
$currentQuestion = $questionsManche[$questionIndex];

// Identifier le joueur actuel
$sharedRoomFile = "shared_room_{$roomId}.json";
if (!file_exists($sharedRoomFile)) {
    header("Location: room.php");
    exit();
}
$roomData = json_decode(file_get_contents($sharedRoomFile), true);
$joueur = ($roomData['joueur1'] == $_SESSION['username']) ? 'joueur1' : 'joueur2';

// Stocker la réponse du joueur pour cette question (écrase si déjà répondu)
$reponse = $_POST['reponse'] ?? '';
$etat['reponses'][$questionIndex][$joueur] = $reponse;

// Vérifier si les deux joueurs ont répondu
if (isset($etat['reponses'][$questionIndex]['joueur1']) && isset($etat['reponses'][$questionIndex]['joueur2'])) {
    // Calculer les points
    $lettreCorrecte = chr(64 + $currentQuestion['correct']);
    if (!empty($etat['reponses'][$questionIndex]['joueur1']) && $etat['reponses'][$questionIndex]['joueur1'] === $lettreCorrecte) {
        $etat['score_joueur1']++;
    }
    if (!empty($etat['reponses'][$questionIndex]['joueur2']) && $etat['reponses'][$questionIndex]['joueur2'] === $lettreCorrecte) {
        $etat['score_joueur2']++;
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

