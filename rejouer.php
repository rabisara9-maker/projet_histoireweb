<?php
session_start();

$roomId = $_SESSION['room_id'] ?? null;

// Nettoyer les fichiers partagés de l'ancienne partie
if ($roomId) {
    $sharedGameFile = "shared_game_{$roomId}.json";
    $sharedRoomFile = "shared_room_{$roomId}.json";
    if (file_exists($sharedGameFile)) unlink($sharedGameFile);
    if (file_exists($sharedRoomFile)) unlink($sharedRoomFile);
}

// CORRECTION BUG #8 : réinitialiser les données de jeu dans la session
// tout en conservant le pseudo et la langue
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
