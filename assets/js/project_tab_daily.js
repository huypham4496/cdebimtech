// /assets/js/project_tab_daily.js
(function () {
  const $  = (s, c) => (c || document).querySelector(s);
  const $$ = (s, c) => Array.from((c || document).querySelectorAll(s));

  const root = $('#daily-tab');
  if (!root) return;

  const projectId = parseInt(root.getAttribute('data-project-id') || '0', 10);
  const canEdit   = root.getAttribute('data-can-edit') === '1';
  const ajaxBase  = root.getAttribute('data-ajax-base') || window.location.pathname; // ví dụ: /pages/project_view.php

  // Elements
  const tbody     = $('#dl-tbody');
  const btnCreate = $('#dl-btn-create');
  const searchBox = $('#dl-search');

  const modal     = $('#dl-modal');
  const form      = $('#dl-form');
  const btnClose  = $('.dl-close', modal);
  const btnCancel = $('.dl-cancel', modal);

  const eqList    = $('#dl-eq-list');
  const lbList    = $('#dl-lb-list');
  const eqAdd     = $('#dl-eq-add');
  const lbAdd     = $('#dl-lb-add');

  // ========= Utilities =========
  function apiUrl(qs) {
    // Luôn bắn vào chính trang hiện tại (project_view.php) qua proxy ?ajax=daily
    return `${ajaxBase}?ajax=daily&${qs}`;
  }

  function addEqRow(name = '', qty = '') {
    const row = document.createElement('div');
    row.className = 'dl-line';
    row.innerHTML = `
      <input name="eq_name[]" type="text" placeholder="Equipment name" value="${name ? String(name).replace(/"/g, '&quot;') : ''}">
      <input name="eq_qty[]" type="number" step="0.001" placeholder="Qty" value="${qty !== '' ? qty : ''}">
      <button type="button" class="dl-btn dl-btn-ghost dl-line-del" title="Remove"><i class="fas fa-times-circle"></i></button>
    `;
    row.querySelector('.dl-line-del').addEventListener('click', () => row.remove());
    eqList.appendChild(row);
  }

  function addLbRow(name = '', qty = '') {
    const row = document.createElement('div');
    row.className = 'dl-line';
    row.innerHTML = `
      <input name="lb_name[]" type="text" placeholder="Labor name" value="${name ? String(name).replace(/"/g, '&quot;') : ''}">
      <input name="lb_qty[]" type="number" step="0.001" placeholder="Qty" value="${qty !== '' ? qty : ''}">
      <button type="button" class="dl-btn dl-btn-ghost dl-line-del" title="Remove"><i class="fas fa-times-circle"></i></button>
    `;
    row.querySelector('.dl-line-del').addEventListener('click', () => row.remove());
    lbList.appendChild(row);
  }

  function setFormDisabled(disabled) {
    form.querySelectorAll('input, select, textarea, button').forEach(el => {
      if (el.classList.contains('dl-close') || el.classList.contains('dl-cancel')) return;
      if (el.type === 'button' && (el.id === 'dl-eq-add' || el.id === 'dl-lb-add')) {
        el.disabled = disabled;
      } else {
        el.disabled = disabled;
      }
    });
    // giữ Close/Cancel luôn bấm được
    btnClose.disabled = false;
    btnCancel.disabled = false;
  }

  function clearForm() {
    form.reset();
    form.querySelector('input[name="dl_action"]').value = 'create';
    form.querySelector('input[name="id"]').value = '';
    form.querySelector('button[type="submit"]').innerHTML = '<i class="fas fa-save"></i> Create';
    eqList.innerHTML = '';
    lbList.innerHTML = '';
    addEqRow();
    addLbRow();
    setFormDisabled(!canEdit);
  }

  function fillFormFromData(j) {
    // j: { ok, data, equipment[], labor[], images[], editable }
    form.querySelector('input[name="dl_action"]').value = 'update';
    form.querySelector('input[name="id"]').value = j.data.id;
    form.querySelector('input[name="code"]').value = j.data.code;
    form.querySelector('input[name="entry_date"]').value = j.data.entry_date;
    form.querySelector('input[name="name"]').value = j.data.name;
    form.querySelector('select[name="approval_group_id"]').value = j.data.approval_group_id || '';
    form.querySelector('select[name="weather_morning"]').value = j.data.weather_morning || '';
    form.querySelector('select[name="weather_afternoon"]').value = j.data.weather_afternoon || '';
    form.querySelector('select[name="weather_evening"]').value = j.data.weather_evening || '';
    form.querySelector('select[name="weather_night"]').value = j.data.weather_night || '';
    form.querySelector('select[name="site_cleanliness"]').value = j.data.site_cleanliness || 'normal';
    form.querySelector('select[name="labor_safety"]').value = j.data.labor_safety || 'normal';
    form.querySelector('textarea[name="work_detail"]').value = j.data.work_detail || '';

    eqList.innerHTML = '';
    (j.equipment || []).forEach(r => addEqRow(r.item_name, r.qty));
    if (eqList.children.length === 0) addEqRow();

    lbList.innerHTML = '';
    (j.labor || []).forEach(r => addLbRow(r.person_name, r.qty));
    if (lbList.children.length === 0) addLbRow();

    form.querySelector('button[type="submit"]').innerHTML = '<i class="fas fa-save"></i> Update';
    setFormDisabled(!j.editable);
  }

  // Parse JSON an toàn, log raw khi lỗi
  async function parseJsonSafe(response) {
    const text = await response.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error('AJAX JSON parse failed. Raw response:\n', text);
      throw new Error('Bad JSON from server');
    }
    return data;
  }

  function postForm(fd) {
    return fetch(apiUrl(`project_id=${projectId}`), { method: 'POST', body: fd })
      .then(parseJsonSafe)
      .then(j => {
        if (!j.ok) throw new Error(j.message || 'Server returned ok=false');
        return j;
      });
  }

  // ========= Search client-side =========
  function applySearch() {
    const q = (searchBox.value || '').toLowerCase().trim();
    $$('.dl-row', tbody).forEach(tr => {
      const name   = (tr.getAttribute('data-name')   || '').toLowerCase();
      const person = (tr.getAttribute('data-person') || '').toLowerCase();
      const hay = `${name} ${person}`;
      tr.style.display = hay.includes(q) ? '' : 'none';
    });
  }
  searchBox.addEventListener('input', applySearch);

  // ========= Row handlers =========
  function bindRowHandlers() {
    // Open (view/edit)
    $$('.dl-open', tbody).forEach(a => {
      a.addEventListener('click', (e) => {
        e.preventDefault();
        const id = parseInt(a.getAttribute('data-id') || '0', 10);
        if (!id) return;
        fetch(apiUrl(`action=get_log&project_id=${projectId}&id=${id}`))
          .then(parseJsonSafe)
          .then(j => {
            if (!j.ok) { alert(j.message || 'Not found'); return; }
            clearForm();
            fillFormFromData(j);
            openModal();
          })
          .catch(err => {
            console.error(err);
            alert('Failed to open log: ' + err.message);
          });
      });
    });

    // Delete
    $$('.dl-delete', tbody).forEach(btn => {
      btn.addEventListener('click', () => {
        const id = parseInt(btn.getAttribute('data-id') || '0', 10);
        if (!id) return;
        if (!canEdit) { alert('Access denied.'); return; }
        if (!confirm('Delete this log?')) return;

        const fd = new FormData();
        fd.append('dl_action', 'delete');
        fd.append('id', String(id));

        postForm(fd)
          .then(() => {
            alert('Deleted.');
            location.reload(); // vì danh sách render sẵn
          })
          .catch(err => {
            console.error(err);
            alert('Delete failed: ' + err.message);
          });
      });
    });
  }

  // ========= Modal open/close =========
  function openModal() { modal.style.display = 'block'; modal.setAttribute('aria-hidden', 'false'); }
  function closeModal(){ modal.style.display = 'none';  modal.setAttribute('aria-hidden', 'true');  }
  btnClose.addEventListener('click', closeModal);
  btnCancel.addEventListener('click', closeModal);
  window.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

  // ========= Create =========
  btnCreate && btnCreate.addEventListener('click', () => {
    if (!canEdit) { alert('Access denied. Only project members can create logs.'); return; }
    clearForm();
    openModal();
  });

  // ========= Add line buttons =========
  eqAdd.addEventListener('click', () => addEqRow());
  lbAdd.addEventListener('click', () => addLbRow());

  // ========= Submit create/update =========
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const fd = new FormData(form);

    // Client-side required
    const code = (form.querySelector('input[name="code"]').value || '').trim();
    const name = (form.querySelector('input[name="name"]').value || '').trim();
    if (!code || !name) {
      alert('Please fill in required fields: Code and Name.');
      return;
    }

    postForm(fd)
      .then(j => {
        alert(j.message || 'Saved.');
        location.reload(); // render sẵn
      })
      .catch(err => {
        console.error(err);
        alert('Save failed: ' + err.message);
      });
  });

  // ========= Init =========
  bindRowHandlers();
})();
