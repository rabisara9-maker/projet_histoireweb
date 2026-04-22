<?php
// etat_room.php - Retourne l'état de la room en JSON (pour le polling JS)
session_start();
header('Content-Type: application/json');

$roomId         = $_SESSION['room_id'] ?? 1;
$sharedRoomFile = "shared_room_{$roomId}.json";

if (file_exists($sharedRoomFile)) {
    $data = json_decode(file_get_contents($sharedRoomFile), true);
    echo json_encode([
        'joueur1'       => $data['joueur1']       ?? null,
        'joueur2'       => $data['joueur2']       ?? null,
        'partie_lancee' => !empty($data['partie_lancee']),
    ]);
} else {
    echo json_encode(['joueur1'=>null,'joueur2'=>null,'partie_lancee'=>false]);
}
