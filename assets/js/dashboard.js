// Dashboard JavaScript (assets/js/dashboard.js)
// Placeholder for dynamic interactions and chart rendering

document.addEventListener('DOMContentLoaded', () => {
  const chart = document.querySelector('.donut-chart');
  if (!chart) return;
  const used = parseFloat(chart.getAttribute('data-used')) || 0;
  const center = document.createElement('div');
  center.className = 'chart-center-text';
  chart.appendChild(center);

  let current = 0;
  function animate() {
    current += 1;
    if (current > used) current = used;
    chart.style.background = `conic-gradient(#2196f3 0% ${current}%, #00e676 ${current}% 100%)`;
    center.textContent = `${current}%`;
    if (current < used) {
      requestAnimationFrame(animate);
    }
  }
  requestAnimationFrame(animate);
});