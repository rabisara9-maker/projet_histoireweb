<?php
session_start();
require_once __DIR__ . '/db.php';

$roomId = $_SESSION['room_id'] ?? null;

// Nettoyer les donnees SQL de l'ancienne partie
if ($roomId) {
    deleteRoomAndGame((int)$roomId);
}

// On repart sur une session propre en gardant le pseudo et la langue.
$username = $_SESSION['username'] ?? null;
$language = $_SESSION['language'] ?? 'fr';

session_destroy();
session_start();

if ($username) {
    $_SESSION['username'] = $username;
    $_SESSION['language'] = $language;
    // Nouveau room_id sera attribué lors du login ou de la room
}

header("Location: login.php");
exit();
