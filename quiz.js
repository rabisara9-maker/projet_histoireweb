// quiz.js - JavaScript pour le quiz avec AJAX

// Fonction pour soumettre la réponse via AJAX
function submitReponse(formData) {
    return fetch('verifier_reponses.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        location.reload(); // Recharger après soumission
    })
    .catch(error => console.error('Erreur:', error));
}

// Timer (30 secondes)
function startTimer() {
    let timeLeft = 30;
    const timerElement = document.getElementById('timer');
    if (!timerElement) return; // Si pas d'élément timer

    const countdown = setInterval(() => {
        timeLeft--;
        timerElement.textContent = 'Temps restant : ' + timeLeft + ' secondes';
        if (timeLeft <= 0) {
            clearInterval(countdown);
            // Auto-soumettre une réponse vide
            submitReponse(new FormData());
        }
    }, 1000);
}

// Vérifier périodiquement l'état du quiz
function checkQuizState(currentQuestionIndex) {
    const interval = setInterval(() => {
        fetch('etat_quiz.php')
        .then(response => response.json())
        .then(data => {
            if (data.question_actuelle !== currentQuestionIndex) {
                clearInterval(interval);
                location.reload();
            }
        })
        .catch(error => console.error('Erreur:', error));
    }, 1000);
    return interval;
}

// Initialisation quand le DOM est chargé
document.addEventListener('DOMContentLoaded', function() {
    const quizForm = document.getElementById('quizForm');
    if (quizForm) {
        quizForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            submitReponse(formData);
        });
    }


    // Démarrer la vérification d'état si on attend
    const waitingElement = document.querySelector('.waiting');
    if (waitingElement) {
        const currentIndex = parseInt(waitingElement.dataset.questionIndex);
        checkQuizState(currentIndex);
    }
});