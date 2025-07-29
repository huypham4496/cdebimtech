// Dashboard JavaScript (assets/js/dashboard.js)
// Generate multi-segment donut chart using Chart.js with center-text animation

document.addEventListener('DOMContentLoaded', () => {
  const ctx = document.getElementById('doughnutChart').getContext('2d');
  const data = {
    labels: ['Lithuania','Czechia','Ireland','Germany','Australia','Austria'],
    datasets: [{
      data: [34.9,21.0,14.0,11.5,9.7,8.9],
      backgroundColor: [
        '#42a5f5','#1e88e5','#8e24aa','#7e57c2','#ec407a','#d81b60'
      ],
      hoverOffset: 4
    }]
  };
  const options = {
    responsive: true,
    cutout: '70%',
    animation: {
      animateRotate: true,
      duration: 1500,
      easing: 'easeOutCubic'
    },
    plugins: { legend: { display: false } }
  };
  new Chart(ctx, { type: 'doughnut', data, options });

  // Animate center text from 0 to 100%
  const centerText = document.querySelector('.chart-center-text-large');
  let current = 0;
  const step = 100 / (options.animation.duration / 16);
  function update() {
    current = Math.min(current + step, 100);
    centerText.textContent = current.toFixed(0) + '%';
    if (current < 100) requestAnimationFrame(update);
  }
  requestAnimationFrame(update);
});