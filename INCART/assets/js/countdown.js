// Daily Deals Countdown Timer
document.addEventListener('DOMContentLoaded', () => {
  const hoursEl = document.getElementById('dealHours');
  const minsEl = document.getElementById('dealMins');
  const secsEl = document.getElementById('dealSecs');

  if (hoursEl && minsEl && secsEl) {
    // Set 12-hour countdown reset
    let totalSeconds = (12 * 3600) - (Math.floor(Date.now() / 1000) % (12 * 3600));

    function updateTimer() {
      const hours = Math.floor(totalSeconds / 3600);
      const minutes = Math.floor((totalSeconds % 3600) / 60);
      const seconds = totalSeconds % 60;

      hoursEl.innerText = String(hours).padStart(2, '0');
      minsEl.innerText = String(minutes).padStart(2, '0');
      secsEl.innerText = String(seconds).padStart(2, '0');

      if (totalSeconds > 0) {
        totalSeconds--;
      } else {
        totalSeconds = 12 * 3600;
      }
    }

    updateTimer();
    setInterval(updateTimer, 1000);
  }
});
