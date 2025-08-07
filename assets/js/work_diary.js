// assets/js/work_diary.js

document.addEventListener('DOMContentLoaded', function() {
  // Toggle logic for Holiday / Break / Late buttons
  document.querySelectorAll('.period').forEach(panel => {
    const pr           = panel.dataset.period;
    const textarea     = panel.querySelector('textarea');
    const inputBreak   = panel.querySelector(`[name="${pr}_break"]`);
    const inputLate    = panel.querySelector(`[name="${pr}_late"]`);
    const inputHoliday = panel.querySelector(`[name="${pr}_holiday"]`);
    const timeInputs   = panel.querySelectorAll('input[type="time"]');

    // Clear other toggles and their hidden inputs
    function clearOthers(activeClass) {
      ['holiday', 'break', 'late'].forEach(cls => {
        if (cls !== activeClass) {
          const btn = panel.querySelector(`.btn-toggle.${cls}.active`);
          if (btn) btn.classList.remove('active');
          const inp = panel.querySelector(`[name="${pr}_${cls}"]`);
          if (inp) inp.value = 0;
        }
      });
    }

    // Holiday toggle (prefix, keep textarea editable)
    panel.querySelectorAll('.btn-toggle.holiday').forEach(btn => {
      btn.addEventListener('click', () => {
        const on = btn.classList.toggle('active');
        inputHoliday.value = on ? 1 : 0;
        clearOthers('holiday');
        timeInputs.forEach(i => i.disabled = false);

        if (on) {
          const prefix = 'Nghỉ lễ ';
          if (!textarea.value.startsWith(prefix)) {
            textarea.value = prefix + textarea.value;
          }
          textarea.focus();
          textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        } else {
          textarea.value = textarea.value.replace(/^Nghỉ lễ\s*/, '');
        }
      });
    });

    // Break toggle (overwrite content, disable textarea & times)
    panel.querySelectorAll('.btn-toggle.break').forEach(btn => {
      btn.addEventListener('click', () => {
        const on = btn.classList.toggle('active');
        inputBreak.value = on ? 1 : 0;
        clearOthers('break');
        textarea.disabled = on;
        timeInputs.forEach(i => i.disabled = on);

        textarea.value = on ? 'Nghỉ' : '';
      });
    });

    // Late toggle (prefix, keep textarea editable)
    panel.querySelectorAll('.btn-toggle.late').forEach(btn => {
      btn.addEventListener('click', () => {
        const on = btn.classList.toggle('active');
        inputLate.value = on ? 1 : 0;
        clearOthers('late');
        timeInputs.forEach(i => i.disabled = false);

        if (on) {
          const prefix = 'Đi muộn: ';
          if (!textarea.value.startsWith(prefix)) {
            textarea.value = prefix + textarea.value;
          }
          textarea.focus();
          textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        } else {
          textarea.value = textarea.value.replace(/^Đi muộn:\s*/, '');
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
    if (cal && ep) {
      cal.style.height = ep.offsetHeight + 'px';
    }
  }
  window.addEventListener('load', matchHeight);
  window.addEventListener('resize', matchHeight);
}); // end DOMContentLoaded
