// assets/js/dashboard.js

document.addEventListener('DOMContentLoaded', function() {
  const ctx = document.getElementById('memoryChart').getContext('2d');
  const used = 10; // ví dụ: 10%
  const remaining = 90;

  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Used storage', 'Remaining storage'],
      datasets: [{
        data: [used, remaining],
        backgroundColor: ['#007bff', '#00c853']
      }]
    },
    options: {
      cutout: '70%',
      plugins: {
        legend: { position: 'bottom' }
      }
    }
  });
});