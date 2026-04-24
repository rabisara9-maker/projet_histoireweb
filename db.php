<?php

function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $host = '127.0.0.1';
        $dbname = 'histoire_quiz';
        $user = 'root';
        $password = '';

        $pdo = new PDO(
            "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    return $pdo;
}

function jsonField($value, $default) {
    if ($value === null || $value === '') {
        return $default;
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : $default;
}

function encodeJson($value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE);
}

function defaultGameState(): array {
    return [
        'manche' => 1,
        'score_joueur1' => 0,
        'score_joueur2' => 0,
        'manches_gagnees_j1' => 0,
        'manches_gagnees_j2' => 0,
        'manches_resultats' => [],
        'questions_manches' => [],
        'theme_manche' => null,
        'themes_utilises' => [],
        'question_actuelle' => 0,
        'reponses' => [],
        'score_calcule' => [],
        'manche_terminee' => false,
        'question_result_until' => null,
        'start_time' => time() + 3,
        'question_start_time' => null,
    ];
}

function normalizeGameState(array $row = null): array {
    $default = defaultGameState();

    if ($row === null) {
        return $default;
    }

    $etat = [
        'manche' => (int)($row['manche'] ?? 1),
        'score_joueur1' => (int)($row['score_joueur1'] ?? 0),
        'score_joueur2' => (int)($row['score_joueur2'] ?? 0),
        'manches_gagnees_j1' => (int)($row['manches_gagnees_j1'] ?? 0),
        'manches_gagnees_j2' => (int)($row['manches_gagnees_j2'] ?? 0),
        'manches_resultats' => jsonField($row['manches_resultats'] ?? null, []),
        'questions_manches' => jsonField($row['questions_manches'] ?? null, []),
        'theme_manche' => $row['theme_manche'] ?? null,
        'themes_utilises' => jsonField($row['themes_utilises'] ?? null, []),
        'question_actuelle' => (int)($row['question_actuelle'] ?? 0),
        'reponses' => jsonField($row['reponses'] ?? null, []),
        'score_calcule' => jsonField($row['score_calcule'] ?? null, []),
        'manche_terminee' => !empty($row['manche_terminee']),
        'question_result_until' => isset($row['question_result_until']) ? (int)$row['question_result_until'] : null,
        'start_time' => isset($row['start_time']) ? (int)$row['start_time'] : time() + 3,
        'question_start_time' => isset($row['question_start_time']) ? (int)$row['question_start_time'] : null,
    ];

    foreach ($default as $key => $value) {
        if (!array_key_exists($key, $etat)) {
            $etat[$key] = $value;
        }
    }

    return $etat;
}

function findAvailableRoomId(): int {
    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->query(
        "SELECT id FROM rooms
         WHERE partie_lancee = 0 AND (joueur1 IS NULL OR joueur2 IS NULL)
         ORDER BY id ASC
         LIMIT 1
         FOR UPDATE"
    );
    $room = $stmt->fetch();

    if ($room) {
        $pdo->commit();
        return (int)$room['id'];
    }

    $pdo->exec("INSERT INTO rooms (partie_lancee) VALUES (0)");
    $roomId = (int)$pdo->lastInsertId();
    $pdo->commit();

    return $roomId;
}

function getRoom(int $roomId): ?array {
    $stmt = db()->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$roomId]);
    $room = $stmt->fetch();
    return $room ?: null;
}

function createRoomWithId(int $roomId): void {
    $stmt = db()->prepare("INSERT IGNORE INTO rooms (id, partie_lancee) VALUES (?, 0)");
    $stmt->execute([$roomId]);
}

function joinRoom(int $roomId, string $username, string $avatar): array {
    $pdo = db();
    $pdo->beginTransaction();

    createRoomWithId($roomId);

    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ? FOR UPDATE");
    $stmt->execute([$roomId]);
    $room = $stmt->fetch();

    if (!$room['joueur1']) {
        $stmt = $pdo->prepare("UPDATE rooms SET joueur1 = ?, avatar1 = ?, partie_lancee = 0 WHERE id = ?");
        $stmt->execute([$username, $avatar, $roomId]);
    } elseif ($room['joueur1'] !== $username && !$room['joueur2']) {
        $stmt = $pdo->prepare("UPDATE rooms SET joueur2 = ?, avatar2 = ? WHERE id = ?");
        $stmt->execute([$username, $avatar, $roomId]);
    }

    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$roomId]);
    $room = $stmt->fetch();

    $pdo->commit();
    return $room;
}

function startRoomIfReady(int $roomId): bool {
    $stmt = db()->prepare(
        "UPDATE rooms
         SET partie_lancee = 1
         WHERE id = ? AND joueur1 IS NOT NULL AND joueur2 IS NOT NULL"
    );
    $stmt->execute([$roomId]);
    return $stmt->rowCount() > 0;
}

function getGameState(int $roomId): array {
    $stmt = db()->prepare("SELECT * FROM games WHERE room_id = ?");
    $stmt->execute([$roomId]);
    $row = $stmt->fetch();

    if (!$row) {
        $etat = defaultGameState();
        saveGameState($roomId, $etat);
        return $etat;
    }

    return normalizeGameState($row);
}

function saveGameState(int $roomId, array $etat): void {
    $stmt = db()->prepare(
        "INSERT INTO games (
            room_id, manche, question_actuelle, score_joueur1, score_joueur2,
            manches_gagnees_j1, manches_gagnees_j2, manches_resultats, theme_manche,
            themes_utilises, questions_manches, reponses, score_calcule,
            manche_terminee, question_result_until, question_start_time, start_time
        ) VALUES (
            :room_id, :manche, :question_actuelle, :score_joueur1, :score_joueur2,
            :manches_gagnees_j1, :manches_gagnees_j2, :manches_resultats, :theme_manche,
            :themes_utilises, :questions_manches, :reponses, :score_calcule,
            :manche_terminee, :question_result_until, :question_start_time, :start_time
        )
        ON DUPLICATE KEY UPDATE
            manche = VALUES(manche),
            question_actuelle = VALUES(question_actuelle),
            score_joueur1 = VALUES(score_joueur1),
            score_joueur2 = VALUES(score_joueur2),
            manches_gagnees_j1 = VALUES(manches_gagnees_j1),
            manches_gagnees_j2 = VALUES(manches_gagnees_j2),
            manches_resultats = VALUES(manches_resultats),
            theme_manche = VALUES(theme_manche),
            themes_utilises = VALUES(themes_utilises),
            questions_manches = VALUES(questions_manches),
            reponses = VALUES(reponses),
            score_calcule = VALUES(score_calcule),
            manche_terminee = VALUES(manche_terminee),
            question_result_until = VALUES(question_result_until),
            question_start_time = VALUES(question_start_time),
            start_time = VALUES(start_time)"
    );

    $stmt->execute([
        ':room_id' => $roomId,
        ':manche' => (int)($etat['manche'] ?? 1),
        ':question_actuelle' => (int)($etat['question_actuelle'] ?? 0),
        ':score_joueur1' => (int)($etat['score_joueur1'] ?? 0),
        ':score_joueur2' => (int)($etat['score_joueur2'] ?? 0),
        ':manches_gagnees_j1' => (int)($etat['manches_gagnees_j1'] ?? 0),
        ':manches_gagnees_j2' => (int)($etat['manches_gagnees_j2'] ?? 0),
        ':manches_resultats' => encodeJson($etat['manches_resultats'] ?? []),
        ':theme_manche' => $etat['theme_manche'] ?? null,
        ':themes_utilises' => encodeJson($etat['themes_utilises'] ?? []),
        ':questions_manches' => encodeJson($etat['questions_manches'] ?? []),
        ':reponses' => encodeJson($etat['reponses'] ?? []),
        ':score_calcule' => encodeJson($etat['score_calcule'] ?? []),
        ':manche_terminee' => !empty($etat['manche_terminee']) ? 1 : 0,
        ':question_result_until' => $etat['question_result_until'] ?? null,
        ':question_start_time' => $etat['question_start_time'] ?? null,
        ':start_time' => $etat['start_time'] ?? time() + 3,
    ]);
}

function advanceExpiredQuestionResult(int $roomId): bool {
    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM games WHERE room_id = ? FOR UPDATE");
    $stmt->execute([$roomId]);
    $row = $stmt->fetch();

    if (!$row) {
        $pdo->commit();
        return false;
    }

    $etat = normalizeGameState($row);
    $questionIndex = (int)($etat['question_actuelle'] ?? 0);
    $result = $etat['score_calcule'][$questionIndex] ?? null;
    $resultUntil = (int)($etat['question_result_until'] ?? 0);

    if (!$result || !$resultUntil || $resultUntil > time()) {
        $pdo->commit();
        return false;
    }

    $etat['question_actuelle'] = $questionIndex + 1;
    $etat['question_result_until'] = null;
    $etat['question_start_time'] = time();
    saveGameState($roomId, $etat);
    $pdo->commit();

    return true;
}

function deleteRoomAndGame(int $roomId): void {
    $stmt = db()->prepare("DELETE FROM rooms WHERE id = ?");
    $stmt->execute([$roomId]);
}
