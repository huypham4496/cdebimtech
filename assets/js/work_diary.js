// Toggle Break/Late buttons
document.querySelectorAll('.period').forEach(panel => {
  const pr    = panel.dataset.period;
  const ta    = panel.querySelector('textarea');
  const hb    = panel.querySelector(`[name="${pr}_break"]`);
  const hl    = panel.querySelector(`[name="${pr}_late"]`);
  const times = panel.querySelectorAll('input[type="time"]');

  panel.querySelectorAll('.btn-toggle.break').forEach(btn => {
    btn.addEventListener('click', () => {
      const on = btn.classList.toggle('active');
      hb.value = on ? 1 : 0;
      ta.disabled = on;
      times.forEach(i => i.disabled = on);
      if (on) ta.value = '';
    });
  });

  panel.querySelectorAll('.btn-toggle.late').forEach(btn => {
    btn.addEventListener('click', () => {
      const on = btn.classList.toggle('active');
      hl.value = on ? 1 : 0;
      if (on) {
        panel.querySelector('.btn-toggle.break.active')?.classList.remove('active');
        hb.value = 0;
        times.forEach(i => i.disabled = false);
      }
    });
  });
});

// Auto-expand textareas
document.querySelectorAll('textarea.autoexpand').forEach(t => {
  t.style.overflow = 'hidden';
  const resize = () => {
    t.style.height = 'auto';
    t.style.height = t.scrollHeight + 'px';
  };
  t.addEventListener('input', resize);
  resize();
});

// Match calendar height to entry panel
function matchHeight() {
  const cal = document.getElementById('calendar'),
        ep  = document.getElementById('entryPanel');
  if (cal && ep) cal.style.height = ep.offsetHeight + 'px';
}
window.addEventListener('load', matchHeight);
window.addEventListener('resize', matchHeight);
