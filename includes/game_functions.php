<?php

function choisirThemeAleatoire($themes, $themesUtilises = []) {
    $dispo = array_diff($themes, $themesUtilises);
    if (empty($dispo)) {
        $dispo = $themes;
    }
    $dispo = array_values($dispo);
    return $dispo[rand(0, count($dispo) - 1)];
}

function filtrerQuestionsParTheme($questions, $theme) {
    return array_values(array_filter($questions, fn($q) => $q['theme'] === $theme));
}

function melangerOptions($question) {
    $options = [
        'A' => $question['a'],
        'B' => $question['b'],
        'C' => $question['c']
    ];

    $cles = array_keys($options);
    shuffle($cles);

    $optionsMelangees = array_map(fn($k) => $options[$k], $cles);
    $nouvellePositionCorrecte = array_search('A', $cles) + 1;

    return [
        'question' => $question['question'],
        'options'  => $optionsMelangees,
        'correct'  => $nouvellePositionCorrecte,
    ];
}
