<?php
session_start();

$roomId = $_SESSION['room_id'] ?? 1;

// Nettoyer les fichiers partagés de cette room
$sharedGameFile = "shared_game_{$roomId}.json";
$sharedRoomFile = "shared_room_{$roomId}.json";
if (file_exists($sharedGameFile)) {
    unlink($sharedGameFile);
}
if (file_exists($sharedRoomFile)) {
    unlink($sharedRoomFile);
}

// Rediriger vers la room
header("Location: room.php");
exit();
?></content>
<parameter name="filePath">/mnt/c/xampp/htdocs/projet_histoireweb/rejouer.php