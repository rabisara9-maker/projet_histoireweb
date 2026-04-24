<?php
session_start();
require_once __DIR__ . '/db.php';

$roomId         = $_SESSION['room_id'] ?? 1;
$roomId = (int)$roomId;

$roomData = getRoom($roomId);
if (!($roomData['joueur1'] ?? null) || !($roomData['joueur2'] ?? null)) {
    header("Location: room.php"); exit();
}
if ($roomData['joueur1'] !== ($_SESSION['username'] ?? null) && $roomData['joueur2'] !== ($_SESSION['username'] ?? null)) {
    header("Location: login.php"); exit();
}

$etat = getGameState($roomId);
if (!$etat) {
    header("Location: room.php"); exit();
}

// CORRECTION BUG #5 : la page score est maintenant accessible dès que quiz.php
// redirige ici — on ne vérifie plus juste $manche < 3

$joueur1 = $roomData['joueur1'];
$joueur2 = $roomData['joueur2'];

$manchesJ1 = (int)($etat['manches_gagnees_j1'] ?? 0);
$manchesJ2 = (int)($etat['manches_gagnees_j2'] ?? 0);

// Identifier le joueur courant pour personnaliser l'affichage
$estJoueur1  = ($_SESSION['username'] === $joueur1);
$nomMoi      = $estJoueur1 ? $joueur1 : $joueur2;
$nomAdverse  = $estJoueur1 ? $joueur2 : $joueur1;
$manchesMoi  = $estJoueur1 ? $manchesJ1 : $manchesJ2;
$manchesAdv  = $estJoueur1 ? $manchesJ2 : $manchesJ1;

if ($manchesJ1 > $manchesJ2)       $gagnant = $joueur1;
elseif ($manchesJ2 > $manchesJ1)   $gagnant = $joueur2;
else                               $gagnant = null; // Égalité parfaite

$jaiGagne = ($gagnant === $_SESSION['username']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Quiz Battle – Résultats</title>
  <link rel="stylesheet" href="style.css"/>
  <style>
    .results-card {
      width:100%; max-width:700px;
      background:rgba(255,255,255,.10);
      border:1px solid rgba(255,255,255,.12);
      border-radius:18px; padding:40px 32px;
      text-align:center;
      box-shadow:0 10px 30px rgba(0,0,0,.25);
    }
    .trophy { font-size:5rem; margin-bottom:12px; }
    .result-title { font-size:2rem; margin-bottom:8px; }
    .result-subtitle { color:#dbeafe; margin-bottom:32px; }

    .scores-row {
      display:flex; align-items:center; justify-content:center;
      gap:24px; margin-bottom:32px;
    }
    .score-box {
      flex:1; max-width:200px;
      background:rgba(255,255,255,.08);
      border-radius:14px; padding:20px;
    }
    .score-box h3 { margin-bottom:8px; font-size:1.1rem; }
    .score-box .big { font-size:2.5rem; font-weight:bold; color:#facc15; }
    .score-box .sub { color:#dbeafe; font-size:.9rem; margin-top:4px; }
    .score-box.winner { border:2px solid #22c55e; }
    .versus-txt { font-size:1.8rem; font-weight:bold; }

    .btn-group { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
    .btn-rejouer {
      padding:14px 32px; border:none; border-radius:10px;
      background:#22c55e; color:#fff;
      font-size:1rem; font-weight:bold; cursor:pointer;
      text-decoration:none; transition:.25s;
    }
    .btn-rejouer:hover { transform:translateY(-2px); }
    .btn-accueil {
      padding:14px 32px; border:1px solid rgba(255,255,255,.3); border-radius:10px;
      background:transparent; color:#fff;
      font-size:1rem; font-weight:bold; cursor:pointer;
      text-decoration:none; transition:.25s;
    }
    .btn-accueil:hover { background:rgba(255,255,255,.1); }
  </style>
</head>
<body>
<div class="page">
  <div class="results-card">

    <div class="trophy"><?= $jaiGagne ? '🏆' : ($gagnant === null ? '🤝' : '😤') ?></div>

    <h1 class="result-title">
      <?php if ($gagnant === null): ?>
        Égalité parfaite !
      <?php elseif ($jaiGagne): ?>
        Victoire, <?= htmlspecialchars($nomMoi) ?> !
      <?php else: ?>
        <?= htmlspecialchars($gagnant) ?> a gagné !
      <?php endif; ?>
    </h1>

    <p class="result-subtitle">
      <?= $gagnant === null
          ? 'Les deux joueurs ont remporté le même nombre de manches.'
          : htmlspecialchars($gagnant) . ' remporte la partie avec ' . max($manchesJ1,$manchesJ2) . ' manche(s) gagnée(s) sur 3.' ?>
    </p>

    <div class="scores-row">
      <div class="score-box <?= $manchesJ1 > $manchesJ2 ? 'winner' : '' ?>">
        <h3><?= htmlspecialchars($joueur1) ?></h3>
        <div class="big"><?= $manchesJ1 ?></div>
        <div class="sub">manche(s) gagnée(s)</div>
      </div>
      <div class="versus-txt">VS</div>
      <div class="score-box <?= $manchesJ2 > $manchesJ1 ? 'winner' : '' ?>">
        <h3><?= htmlspecialchars($joueur2) ?></h3>
        <div class="big"><?= $manchesJ2 ?></div>
        <div class="sub">manche(s) gagnée(s)</div>
      </div>
    </div>

    <div class="btn-group">
      <a href="rejouer.php" class="btn-rejouer">🔄 Rejouer</a>
      <a href="login.php"   class="btn-accueil">🏠 Accueil</a>
    </div>

  </div>
</div>
</body>
</html>
