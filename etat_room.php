<?php
// etat_room.php - Retourne l'état de la room en JSON (pour le polling JS)
session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$roomId         = $_SESSION['room_id'] ?? 1;
$room = getRoom((int)$roomId);

if ($room) {
    echo json_encode([
        'joueur1'       => $room['joueur1']       ?? null,
        'joueur2'       => $room['joueur2']       ?? null,
        'partie_lancee' => !empty($room['partie_lancee']),
    ]);
} else {
    echo json_encode(['joueur1'=>null,'joueur2'=>null,'partie_lancee'=>false]);
}
