<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit();
}

$roomId = (int)($_SESSION['room_id'] ?? 1);
$roomData = getRoom($roomId);

if (!$roomData || !($roomData['joueur1'] ?? null) || !($roomData['joueur2'] ?? null)) {
    header("Location: ../room.php");
    exit();
}
if ($roomData['joueur1'] !== $_SESSION['username'] && $roomData['joueur2'] !== $_SESSION['username']) {
    header("Location: ../login.php");
    exit();
}

$joueur=($roomData['joueur1'] === $_SESSION['username']) ? 'joueur1' : 'joueur2';
$reponsePost=$_POST['reponse'] ?? '';
$questionIndexP=(int)($_POST['question_index'] ?? -1);
$reponsePropre=in_array($reponsePost, ['A', 'B', 'C'], true) ? $reponsePost : '';

$pdo=db();
$pdo->beginTransaction();

$stmt=$pdo->prepare("SELECT * FROM games WHERE room_id = ? FOR UPDATE");
$stmt->execute([$roomId]);
$row=$stmt->fetch();

if(!$row){
    $etat=defaultGameState();
    saveGameState($roomId, $etat);
    $stmt->execute([$roomId]);
    $row=$stmt->fetch();
}

$etat=normalizeGameState($row);
$manche=(int)($etat['manche'] ?? 1);
$mancheKey=(string)$manche;
$questionIndex=(int)($etat['question_actuelle'] ?? 0);

if($questionIndexP !== $questionIndex || !empty($etat['manche_terminee'])){
    $pdo->commit();
    header("Location: ../quiz.php");
    exit();
}

if(!isset($etat['reponses']) || !is_array($etat['reponses'])){
    $etat['reponses']=[];
}
if(!isset($etat['reponses'][$questionIndex]) || !is_array($etat['reponses'][$questionIndex])){
    $etat['reponses'][$questionIndex]=[];
}
if(!isset($etat['score_calcule']) || !is_array($etat['score_calcule'])){
    $etat['score_calcule']=[];
}

if(!isset($etat['reponses'][$questionIndex][$joueur])){
    $etat['reponses'][$questionIndex][$joueur]=$reponsePropre;
}

$j1OK=isset($etat['reponses'][$questionIndex]['joueur1']);
$j2OK=isset($etat['reponses'][$questionIndex]['joueur2']);

if ($j1OK && $j2OK && !isset($etat['score_calcule'][$questionIndex])) {
    $questionsManche=$etat['questions_manches'][$mancheKey] ?? [];
    $currentQuestion=$questionsManche[$questionIndex] ?? null;

    if (is_array($currentQuestion)) {
        $lettreCorrecte=chr(64+(int)($currentQuestion['correct'] ?? 1));

        $rep1=$etat['reponses'][$questionIndex]['joueur1'];
        $rep2=$etat['reponses'][$questionIndex]['joueur2'];
        $j1Correct=($rep1 !== '' && $rep1 === $lettreCorrecte);
        $j2Correct=($rep2 !== '' && $rep2 === $lettreCorrecte);

        if ($j1Correct) {
            $etat['score_joueur1'] = (int)($etat['score_joueur1'] ?? 0) + 1;
        }
        if ($j2Correct) {
            $etat['score_joueur2'] = (int)($etat['score_joueur2'] ?? 0) + 1;
        }

        $etat['score_calcule'][$questionIndex] = [
            'correct' => $lettreCorrecte,
            'joueur1_correct' => $j1Correct,
            'joueur2_correct' => $j2Correct,
        ];
        $etat['question_result_until'] = time() + 3;
    }
}

saveGameState($roomId, $etat);
$pdo->commit();

header("Location: ../quiz.php");
exit();
