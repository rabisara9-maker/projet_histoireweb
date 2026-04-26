<?php
session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['room_id'])) {
    echo json_encode(['error' => 'no_session']);
    exit();
}

$roomId = (int) $_SESSION['room_id'];
$room = getRoom($roomId);

if (!$room) {
    echo json_encode(['error' => 'room_missing']);
    exit();
}

$data = getGameState($roomId);

echo json_encode([
    'manche'             => (int)($data['manche'] ?? 1),
    'question_actuelle'  => (int)($data['question_actuelle'] ?? 0),
    'reponses'           => $data['reponses'] ?? [],
    'score_joueur1'      => (int)($data['score_joueur1'] ?? 0),
    'score_joueur2'      => (int)($data['score_joueur2'] ?? 0),
    'manches_gagnees_j1' => (int)($data['manches_gagnees_j1'] ?? 0),
    'manches_gagnees_j2' => (int)($data['manches_gagnees_j2'] ?? 0),
]);
