<?php
session_start();
// etat_quiz.php - Retourne l'état actuel du quiz en JSON
$roomId = $_SESSION['room_id'] ?? 1;
$sharedFile = "shared_game_{$roomId}.json";
if (file_exists($sharedFile)) {
    echo file_get_contents($sharedFile);
} else {
    echo json_encode([]);
}
?>