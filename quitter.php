<?php
session_start();
require_once __DIR__ . '/db.php';

$roomId = $_SESSION['room_id'] ?? null;

if ($roomId) {
    deleteRoomAndGame((int)$roomId);
}

session_destroy();

header("Location: login.php");
exit();
