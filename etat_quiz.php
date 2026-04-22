<?php
// etat_quiz.php - Retourne l'état du quiz en JSON (pour le polling JS)
session_start();
header('Content-Type: application/json');

// CORRECTION BUG #7 : vérifier proprement la session avant de lire le fichier
if (!isset($_SESSION['room_id'])) {
    echo json_encode(['error' => 'no_session']);
    exit();
}

$roomId     = (int)$_SESSION['room_id'];
$sharedFile = "shared_game_{$roomId}.json";

if (file_exists($sharedFile)) {
    $data = json_decode(file_get_contents($sharedFile), true);
    if (is_array($data)) {
        // Ne renvoyer que les champs utiles au client (pas les questions entières)
        echo json_encode([
            'manche'           => $data['manche']            ?? 1,
            'question_actuelle'=> $data['question_actuelle'] ?? 0,
            'reponses'         => $data['reponses']          ?? [],
            'score_joueur1'    => $data['score_joueur1']     ?? 0,
            'score_joueur2'    => $data['score_joueur2']     ?? 0,
        ]);
    } else {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
