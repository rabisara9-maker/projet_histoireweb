(function () {
  const src = 'assets/audio/medieval-theme.mp3';
  const enabledKey = 'quiz_music_enabled';
  const timeKey = 'quiz_music_time';

  let enabled = localStorage.getItem(enabledKey) !== '0';
  const audio = new Audio(src);
  audio.loop = true;
  audio.volume = 0.16;

  const savedTime = Number(localStorage.getItem(timeKey) || 0);
  if (savedTime > 0) {
    audio.currentTime = savedTime;
  }

  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'global-music-btn';
  btn.title = 'Activer ou couper la musique';
  document.body.appendChild(btn);

  const style = document.createElement('style');
  style.textContent = `
    .global-music-btn {
      position: fixed;
      right: 18px;
      bottom: 18px;
      z-index: 9999;
      width: 52px;
      height: 52px;
      border-radius: 16px;
      border: 1px solid rgba(250, 204, 21, 0.55);
      background: rgba(41, 24, 12, 0.92);
      color: #facc15;
      font-size: 1.25rem;
      cursor: pointer;
      box-shadow: 0 12px 26px rgba(0,0,0,.45);
    }
    .global-music-btn:hover {
      background: rgba(255, 247, 237, 0.14);
      transform: translateY(-2px);
    }
  `;
  document.head.appendChild(style);

  function updateButton() {
    btn.textContent = enabled ? '🎶' : '🎵';
  }

  function playMusic() {
    if (!enabled) return;
    audio.play().catch(() => {});
  }

  function stopMusic() {
    audio.pause();
  }

  btn.addEventListener('click', function () {
    enabled = !enabled;
    localStorage.setItem(enabledKey, enabled ? '1' : '0');
    updateButton();

    if (enabled) {
      playMusic();
    } else {
      stopMusic();
    }
  });

  document.addEventListener('pointerdown', function startAfterFirstAction() {
    playMusic();
    document.removeEventListener('pointerdown', startAfterFirstAction);
  });

  setInterval(function () {
    if (!audio.paused) {
      localStorage.setItem(timeKey, String(audio.currentTime));
    }
  }, 1000);

  updateButton();
  playMusic();
})();
