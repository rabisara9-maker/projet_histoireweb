<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php"); exit();
}

$roomId         = $_SESSION['room_id'] ?? 1;
$sharedFile     = "shared_game_{$roomId}.json";
$sharedRoomFile = "shared_room_{$roomId}.json";

if (!file_exists($sharedRoomFile)) {
    header("Location: room.php"); exit();
}
$roomData = json_decode(file_get_contents($sharedRoomFile), true);
if (!is_array($roomData)) {
    header("Location: room.php"); exit();
}

$joueur         = ($roomData['joueur1'] === $_SESSION['username']) ? 'joueur1' : 'joueur2';
$reponsePost    = $_POST['reponse']        ?? '';
$questionIndexP = (int)($_POST['question_index'] ?? -1);
$reponsePropre  = in_array($reponsePost, ['A','B','C']) ? $reponsePost : '';

// ══════════════════════════════════════════════════════════════════════════════
// SECTION CRITIQUE — UN SEUL PROCESS À LA FOIS GRÂCE À flock(LOCK_EX)
//
// Sans flock :
//   J1 lit {reponses:[]}  →  J2 lit {reponses:[]}  (même snapshot)
//   J1 écrit {joueur1:"A"}  →  J2 écrit {joueur2:"B"}  ← ÉCRASE J1
//   Résultat : jamais les deux réponses ensemble → bloqué indéfiniment
//
// Avec flock :
//   J1 prend le verrou, lit, écrit {joueur1:"A"}, libère
//   J2 attend, prend le verrou, lit {joueur1:"A"}, écrit {joueur1:"A", joueur2:"B"}
//   Les deux réponses sont présentes → score calculé → question_actuelle++
// ══════════════════════════════════════════════════════════════════════════════

if (!file_exists($sharedFile)) {
    header("Location: quiz.php"); exit();
}

$fp = fopen($sharedFile, 'c+');
if (!$fp) {
    header("Location: quiz.php"); exit();
}

flock($fp, LOCK_EX); // ← bloque jusqu'à obtenir le verrou exclusif

// Lire sous verrou
$contenu = '';
while (!feof($fp)) $contenu .= fread($fp, 8192);
$etat = json_decode($contenu, true);

if (!is_array($etat)) {
    flock($fp, LOCK_UN); fclose($fp);
    header("Location: quiz.php"); exit();
}

$manche        = (int)($etat['manche']            ?? 1);
$mancheKey     = (string)$manche;
$questionIndex = (int)($etat['question_actuelle'] ?? 0);

// Question soumise ≠ question courante → double-soumission, ignorer
if ($questionIndexP !== $questionIndex) {
    flock($fp, LOCK_UN); fclose($fp);
    header("Location: quiz.php"); exit();
}

// Manche déjà terminée → ignorer
if (!empty($etat['manche_terminee'])) {
    flock($fp, LOCK_UN); fclose($fp);
    header("Location: quiz.php"); exit();
}

// Initialiser reponses[$questionIndex] comme tableau associatif si besoin
if (!isset($etat['reponses']) || !is_array($etat['reponses'])) {
    $etat['reponses'] = [];
}
// Détecter si reponses est un array indexé (ex: venant de reponses:[]) et le corriger
// Un array associatif vide est OK ; un array numérique est corrompu
if (isset($etat['reponses'][0]) && !isset($etat['reponses'][$questionIndex])) {
    // Array numérique corrompu → reset
    $etat['reponses'] = [];
}
if (!isset($etat['reponses'][$questionIndex]) || !is_array($etat['reponses'][$questionIndex])) {
    $etat['reponses'][$questionIndex] = [];
}

if (!isset($etat['score_calcule']) || !is_array($etat['score_calcule'])) {
    $etat['score_calcule'] = [];
}

// Ce joueur a déjà répondu → ignorer
if (isset($etat['reponses'][$questionIndex][$joueur])) {
    flock($fp, LOCK_UN); fclose($fp);
    header("Location: quiz.php"); exit();
}

// ── Enregistrer la réponse ────────────────────────────────────────────────────
$etat['reponses'][$questionIndex][$joueur] = $reponsePropre;

// ── Les deux ont répondu ? ────────────────────────────────────────────────────
$j1OK = isset($etat['reponses'][$questionIndex]['joueur1']);
$j2OK = isset($etat['reponses'][$questionIndex]['joueur2']);

if ($j1OK && $j2OK && !isset($etat['score_calcule'][$questionIndex])) {

    $questionsManche = $etat['questions_manches'][$mancheKey] ?? [];
    $currentQuestion = $questionsManche[$questionIndex]       ?? null;

    if (is_array($currentQuestion)) {
        $lettreCorrecte = chr(64 + (int)($currentQuestion['correct'] ?? 1));

        $rep1 = $etat['reponses'][$questionIndex]['joueur1'];
        $rep2 = $etat['reponses'][$questionIndex]['joueur2'];

        if ($rep1 !== '' && $rep1 === $lettreCorrecte)
            $etat['score_joueur1'] = (int)($etat['score_joueur1'] ?? 0) + 1;
        if ($rep2 !== '' && $rep2 === $lettreCorrecte)
            $etat['score_joueur2'] = (int)($etat['score_joueur2'] ?? 0) + 1;

        $etat['score_calcule'][$questionIndex] = true;
        $etat['question_actuelle']             = $questionIndex + 1;
        $etat['question_start_time']           = time(); // reset du timer pour la prochaine question
    }
}

// ── Écrire et libérer le verrou ───────────────────────────────────────────────
rewind($fp);
ftruncate($fp, 0);
fwrite($fp, json_encode($etat));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

header("Location: quiz.php");
exit();
