<?php
session_start();
require_once __DIR__ . '/db.php';

$roomId = $_SESSION['room_id'] ?? null;

// On supprime l'ancienne partie avant d'en créer une autre.
if ($roomId) {
    deleteRoomAndGame((int)$roomId);
}

// On repart sur une nouvelle room en gardant les informations du joueur.
$username = $_SESSION['username'] ?? null;
$age = $_SESSION['age'] ?? null;
$avatar = $_SESSION['avatar'] ?? '👤';

session_destroy();
session_start();

if ($username) {
    $_SESSION['username'] = $username;
    $_SESSION['age'] = $age;
    $_SESSION['avatar'] = $avatar;
    $_SESSION['room_id'] = findAvailableRoomId();

    header("Location: room.php");
    exit();
}

header("Location: login.php");
exit();
