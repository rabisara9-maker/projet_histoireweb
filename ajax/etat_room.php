<?php
// Petite route appelée par JavaScript pour savoir si la room est prête.
session_start();
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['room_id'])) {
    echo json_encode(['error' => 'no_session']);
    exit();
}

$roomId = $_SESSION['room_id'];
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
