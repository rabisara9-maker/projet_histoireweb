<?php
session_start();

$roomId = $_SESSION['room_id'] ?? 1;

// Vérifier si le jeu est terminé
$sharedRoomFile = "shared_room_{$roomId}.json";
if (!file_exists($sharedRoomFile)) {
    header("Location: room.php");
    exit();
}
$roomData = json_decode(file_get_contents($sharedRoomFile), true);
if (!$roomData['joueur1'] || !$roomData['joueur2']) {
    header("Location: room.php");
    exit();
}

$sharedFile = "shared_game_{$roomId}.json";
function lireEtatPartage() {
    global $sharedFile;
    if (file_exists($sharedFile)) {
        return json_decode(file_get_contents($sharedFile), true);
    }
    return [];
}

$etat = lireEtatPartage();
if (($etat['manche'] ?? 1) < 3) {
    header("Location: quiz.php");
    exit();
}

$joueur1 = $roomData['joueur1'];
$joueur2 = $roomData['joueur2'];
$score1 = $etat['score_joueur1'] ?? 0;
$score2 = $etat['score_joueur2'] ?? 0;

// Déterminer le gagnant
$gagnant = ($score1 > $score2) ? $joueur1 : (($score2 > $score1) ? $joueur2 : 'Égalité');

echo "<h1>Résultats finaux</h1>";
echo "<p>$joueur1 : $score1 points</p>";
echo "<p>$joueur2 : $score2 points</p>";
echo "<h2>Gagnant : $gagnant</h2>";

// Nettoyer les fichiers partagés
if (file_exists($sharedFile)) unlink($sharedFile);
if (file_exists($sharedRoomFile)) unlink($sharedRoomFile);
?></content>
<parameter name="filePath">/mnt/c/xampp/htdocs/projet_histoireweb/score.php