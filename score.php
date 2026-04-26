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
$avatar1 = $roomData['avatar1'] ?? '👤';
$avatar2 = $roomData['avatar2'] ?? '👤';

$manchesJ1 = (int)($etat['manches_gagnees_j1'] ?? 0);
$manchesJ2 = (int)($etat['manches_gagnees_j2'] ?? 0);

// On affiche le score du point de vue du joueur connecté.
$estJoueur1  = ($_SESSION['username'] === $joueur1);
$nomMoi      = $estJoueur1 ? $joueur1 : $joueur2;
$nomAdverse  = $estJoueur1 ? $joueur2 : $joueur1;
$manchesMoi  = $estJoueur1 ? $manchesJ1 : $manchesJ2;
$manchesAdv  = $estJoueur1 ? $manchesJ2 : $manchesJ1;

if ($manchesJ1 > $manchesJ2)       $gagnant = $joueur1;
elseif ($manchesJ2 > $manchesJ1)   $gagnant = $joueur2;
else                               $gagnant = null; // égalité

$jaiGagne = ($gagnant === $_SESSION['username']);
$titreResultat = $gagnant === null
    ? 'Duel sans vainqueur'
    : ($jaiGagne ? 'Victoire remportée' : 'Défaite honorable');
$messageResultat = $gagnant === null
    ? 'Les deux joueurs terminent à égalité. Personne ne lâche la couronne.'
    : ($jaiGagne
        ? 'Tu remportes le duel et tu gardes la couronne.'
        : htmlspecialchars($gagnant) . ' remporte le duel cette fois.');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Quiz Battle – Résultats</title>
  <link rel="stylesheet" href="assets/css/style.css?v=2"/>
  <style>
    .results-card {
      width:100%; max-width:860px;
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
    .confetti {
      position:absolute;
      top:-20px;
      width:8px;
      height:14px;
      border-radius:3px;
      animation:fall 4.5s linear infinite;
      opacity:.85;
    }
    .confetti:nth-child(1) { left:8%; background:#facc15; animation-delay:.1s; }
    .confetti:nth-child(2) { left:20%; background:#22c55e; animation-delay:.9s; }
    .confetti:nth-child(3) { left:36%; background:#f97316; animation-delay:.3s; }
    .confetti:nth-child(4) { left:52%; background:#38bdf8; animation-delay:1.2s; }
    .confetti:nth-child(5) { left:68%; background:#facc15; animation-delay:.6s; }
    .confetti:nth-child(6) { left:84%; background:#ef4444; animation-delay:1.5s; }
    @keyframes fall {
      0% { transform:translateY(-20px) rotate(0deg); }
      100% { transform:translateY(620px) rotate(280deg); }
    }
    .result-kicker {
      color:#fde68a;
      font-weight:bold;
      letter-spacing:.5px;
      text-transform:uppercase;
      font-size:.82rem;
      margin-bottom:10px;
    }
    .trophy {
      font-size:5rem;
      margin-bottom:12px;
      filter:drop-shadow(0 0 18px rgba(250,204,21,.35));
    }
    .result-title { font-size:2.25rem; margin-bottom:8px; color:#facc15; }
    .result-subtitle { color:#fde68a; margin-bottom:28px; font-size:1.05rem; }

    .scores-row {
      display:grid;
      grid-template-columns:1fr auto 1fr;
      align-items:stretch;
      gap:18px;
      margin-bottom:28px;
    }
    .score-box {
      background:rgba(255,247,237,.10);
      border:1px solid rgba(250,204,21,.35);
      border-radius:16px; padding:22px 18px;
      position:relative;
    }
    .score-box .avatar-score { font-size:2.4rem; margin-bottom:8px; }
    .score-box h3 { margin-bottom:8px; font-size:1.15rem; color:#facc15; }
    .score-box .big { font-size:2.5rem; font-weight:bold; color:#facc15; }
    .score-box .sub { color:#ffedd5; font-size:.9rem; margin-top:4px; }
    .score-box.winner {
      border:2px solid #22c55e;
      box-shadow:0 0 22px rgba(34,197,94,.30);
      transform:translateY(-6px);
    }
    .winner-label {
      display:inline-block;
      margin-top:10px;
      padding:6px 10px;
      border-radius:999px;
      background:rgba(34,197,94,.18);
      color:#bbf7d0;
      font-size:.78rem;
      font-weight:bold;
    }
    .versus-txt {
      align-self:center;
      font-size:1.8rem;
      font-weight:bold;
      color:#facc15;
      text-shadow:0 0 14px rgba(250,204,21,.35);
    }
    .round-track {
      display:flex;
      justify-content:center;
      gap:10px;
      margin:0 auto 28px;
      flex-wrap:wrap;
    }
    .round-pill {
      border:1px solid rgba(250,204,21,.35);
      border-radius:999px;
      padding:8px 12px;
      color:#ffedd5;
      background:rgba(255,247,237,.08);
      font-size:.9rem;
    }
    .round-pill.win { color:#bbf7d0; border-color:#22c55e; }
    .round-pill.draw { color:#fde68a; }

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
    @media (max-width:700px) {
      .scores-row { grid-template-columns:1fr; }
      .versus-txt { padding:4px 0; }
      .score-box.winner { transform:none; }
    }
  </style>
</head>
<body>
<div class="page">
  <div class="results-card">
    <?php if ($gagnant !== null): ?>
      <span class="confetti"></span><span class="confetti"></span><span class="confetti"></span>
      <span class="confetti"></span><span class="confetti"></span><span class="confetti"></span>
    <?php endif; ?>

    <p class="result-kicker">Fin de partie</p>
    <div class="trophy"><?= $jaiGagne ? '🏆' : ($gagnant === null ? '🤝' : '⚔️') ?></div>

    <h1 class="result-title"><?= htmlspecialchars($titreResultat) ?></h1>
    <p class="result-subtitle"><?= $messageResultat ?></p>

    <div class="round-track">
      <?php for ($i = 1; $i <= 3; $i++):
          $res = $etat['manches_resultats'][(string)$i] ?? null;
          $label = 'Manche ' . $i;
          $class = '';
          if ($res === 'joueur1') { $label .= ' : ' . $joueur1; $class = 'win'; }
          elseif ($res === 'joueur2') { $label .= ' : ' . $joueur2; $class = 'win'; }
          elseif ($res === 'egalite') { $label .= ' : égalité'; $class = 'draw'; }
          else { $label .= ' : non jouée'; }
      ?>
        <span class="round-pill <?= $class ?>"><?= htmlspecialchars($label) ?></span>
      <?php endfor; ?>
    </div>

    <div class="scores-row">
      <div class="score-box <?= $manchesJ1 > $manchesJ2 ? 'winner' : '' ?>">
        <div class="avatar-score"><?= htmlspecialchars($avatar1) ?></div>
        <h3><?= htmlspecialchars($joueur1) ?></h3>
        <div class="big"><?= $manchesJ1 ?></div>
        <div class="sub">manche(s) gagnée(s)</div>
        <?php if ($manchesJ1 > $manchesJ2): ?><span class="winner-label">Vainqueur</span><?php endif; ?>
      </div>
      <div class="versus-txt">VS</div>
      <div class="score-box <?= $manchesJ2 > $manchesJ1 ? 'winner' : '' ?>">
        <div class="avatar-score"><?= htmlspecialchars($avatar2) ?></div>
        <h3><?= htmlspecialchars($joueur2) ?></h3>
        <div class="big"><?= $manchesJ2 ?></div>
        <div class="sub">manche(s) gagnée(s)</div>
        <?php if ($manchesJ2 > $manchesJ1): ?><span class="winner-label">Vainqueur</span><?php endif; ?>
      </div>
    </div>

    <div class="btn-group">
      <a href="rejouer.php" class="btn-rejouer">🔄 Rejouer</a>
      <a href="quitter.php" class="btn-accueil">🏠 Accueil</a>
    </div>

  </div>
</div>
<script src="assets/js/music.js"></script>
</body>
</html>
