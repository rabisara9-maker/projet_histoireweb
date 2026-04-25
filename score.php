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
      background:rgba(41, 24, 12, .88);
      border:2px solid #c0842d;
      border-radius:22px; padding:40px 32px;
      text-align:center;
      box-shadow:0 0 35px rgba(245,158,11,.25), 0 18px 45px rgba(0,0,0,.55);
      position:relative;
      overflow:hidden;
    }
    .results-card::before {
      content:"⚜";
      position:absolute;
      top:-27px;
      right:22px;
      font-size:8rem;
      color:rgba(250,204,21,.08);
    }
    .trophy { font-size:5rem; margin-bottom:12px; }
    .result-title { font-size:2rem; margin-bottom:8px; color:#facc15; }
    .result-subtitle { color:#fde68a; margin-bottom:32px; }

    .scores-row {
      display:flex; align-items:center; justify-content:center;
      gap:24px; margin-bottom:32px;
    }
    .score-box {
      flex:1; max-width:200px;
      background:rgba(255,247,237,.10);
      border:1px solid rgba(250,204,21,.35);
      border-radius:14px; padding:20px;
    }
    .score-box h3 { margin-bottom:8px; font-size:1.1rem; color:#facc15; }
    .score-box .big { font-size:2.5rem; font-weight:bold; color:#facc15; }
    .score-box .sub { color:#ffedd5; font-size:.9rem; margin-top:4px; }
    .score-box.winner { border:2px solid #22c55e; box-shadow:0 0 18px rgba(34,197,94,.25); }
    .versus-txt { font-size:1.8rem; font-weight:bold; color:#facc15; }

    .btn-group { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
    .btn-rejouer {
      padding:14px 32px; border:none; border-radius:12px;
      background:linear-gradient(135deg, #d97706, #facc15); color:#1c1208;
      font-size:1rem; font-weight:bold; cursor:pointer;
      text-decoration:none; transition:.25s;
    }
    .btn-rejouer:hover { transform:translateY(-3px); box-shadow:0 10px 20px rgba(0,0,0,.35); }
    .btn-accueil {
      padding:14px 32px; border:1px solid rgba(250,204,21,.45); border-radius:12px;
      background:rgba(255,247,237,.08); color:#fff7ed;
      font-size:1rem; font-weight:bold; cursor:pointer;
      text-decoration:none; transition:.25s;
    }
    .btn-accueil:hover { background:rgba(255,247,237,.16); transform:translateY(-3px); }
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
      <a href="quitter.php" class="btn-accueil">🏠 Accueil</a>
    </div>

  </div>
</div>
</body>
</html>
