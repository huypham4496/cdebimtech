// /assets/js/project_tab_daily.js
(function () {
  const $  = (s, c) => (c || document).querySelector(s);
  const $$ = (s, c) => Array.from((c || document).querySelectorAll(s));

  const root = $('#daily-tab');
  if (!root) return;

  const projectId   = parseInt(root.getAttribute('data-project-id') || '0', 10);
  const canEdit     = root.getAttribute('data-can-edit') === '1';
  const ajaxBase    = root.getAttribute('data-ajax-base') || window.location.pathname;
  const uploadPrefx = root.getAttribute('data-upload-prefix') || '';

  const tbody     = $('#dl-tbody');
  const btnCreate = $('#dl-btn-create');
  const searchBox = $('#dl-search');

  const modal     = $('#dl-modal');
  const form      = $('#dl-form');
  const btnClose  = modal ? modal.querySelector('.dl-close') : null;
  const btnCancel = modal ? modal.querySelector('.dl-cancel') : null;

  const eqList    = $('#dl-eq-list');
  const lbList    = $('#dl-lb-list');
  const eqAdd     = $('#dl-eq-add');
  const lbAdd     = $('#dl-lb-add');
  const imgsWrap  = $('#dl-images-view');
  const fileInput = $('#dl-images-input');

  const lightbox  = $('#dl-lightbox');
  const lightImg  = $('#dl-lightbox-img');

  function apiUrl(qs) { return `${ajaxBase}?ajax=daily&${qs}&_=${Date.now()}`; }

  function addEqRow(name = '', qty = '') {
    const row = document.createElement('div');
    row.className = 'dl-line';
    row.innerHTML = `
      <input name="eq_name[]" type="text" placeholder="Equipment name" value="${name ? String(name).replace(/"/g, '&quot;') : ''}">
      <input name="eq_qty[]" type="number" step="0.001" placeholder="Qty" value="${qty !== '' ? qty : ''}">
      <button type="button" class="dl-btn dl-btn-link dl-line-del" title="Remove"><i class="fas fa-times-circle"></i></button>
    `;
    row.querySelector('.dl-line-del').addEventListener('click', () => row.remove());
    if (eqList) eqList.appendChild(row);
  }
  function addLbRow(name = '', qty = '') {
    const row = document.createElement('div');
    row.className = 'dl-line';
    row.innerHTML = `
      <input name="lb_name[]" type="text" placeholder="Labor name" value="${name ? String(name).replace(/"/g, '&quot;') : ''}">
      <input name="lb_qty[]" type="number" step="0.001" placeholder="Qty" value="${qty !== '' ? qty : ''}">
      <button type="button" class="dl-btn dl-btn-link dl-line-del" title="Remove"><i class="fas fa-times-circle"></i></button>
    `;
    row.querySelector('.dl-line-del').addEventListener('click', () => row.remove());
    if (lbList) lbList.appendChild(row);
  }

  function setFormDisabled(disabled) {
    if (!form) return;
    form.querySelectorAll('input, select, textarea, button').forEach(el => {
      if (el.classList.contains('dl-close') || el.classList.contains('dl-cancel')) return;
      if (el === fileInput) { el.disabled = disabled; return; }
      el.disabled = disabled;
    });
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.style.display = disabled ? 'none' : '';
  }

  function clearForm() {
    if (!form) return;
    form.reset();
    form.querySelector('input[name="dl_action"]').value = 'create';
    form.querySelector('input[name="id"]').value = '';
    const sb = form.querySelector('button[type="submit"]');
    if (sb) sb.innerHTML = '<i class="fas fa-save"></i> Save';
    if (eqList) { eqList.innerHTML = ''; addEqRow(); }
    if (lbList) { lbList.innerHTML = ''; addLbRow(); }
    if (imgsWrap) imgsWrap.innerHTML = '';
    setFormDisabled(!canEdit);
  }

  function renderThumbs(images) {
    if (!imgsWrap) return;
    imgsWrap.innerHTML = '';
    if (!images || !images.length) return;
    const grid = document.createElement('div');
    grid.className = 'dl-images';
    images.forEach(im => {
      const rel = im.path || '';
      const url = rel.startsWith('/') ? rel : '/' + rel; // ensure absolute '/uploads/...'
      const img = document.createElement('img');
      img.className = 'dl-thumb';
      img.src = url;
      img.alt = im.file_name || 'image';
      img.addEventListener('click', () => {
        if (lightImg && lightbox) { lightImg.src = url; lightbox.classList.add('open'); }
      });
      grid.appendChild(img);
    });
    imgsWrap.appendChild(grid);
  }

  function fillFormFromData(j) {
    if (!form) return;
    const d = j.data || {};
    form.querySelector('input[name="dl_action"]').value = 'update';
    form.querySelector('input[name="id"]').value = d.id;
    form.querySelector('input[name="code"]').value = d.code || '';
    form.querySelector('input[name="entry_date"]').value = d.entry_date || '';
    form.querySelector('input[name="name"]').value = d.name || '';
    form.querySelector('select[name="approval_group_id"]').value = d.approval_group_id || '';
    form.querySelector('select[name="weather_morning"]').value = d.weather_morning || '';
    form.querySelector('select[name="weather_afternoon"]').value = d.weather_afternoon || '';
    form.querySelector('select[name="weather_evening"]').value = d.weather_evening || '';
    form.querySelector('select[name="weather_night"]').value = d.weather_night || '';
    form.querySelector('select[name="site_cleanliness"]').value = d.site_cleanliness || 'normal';
    form.querySelector('select[name="labor_safety"]').value = d.labor_safety || 'normal';
    form.querySelector('textarea[name="work_detail"]').value = d.work_detail || '';

    if (eqList) {
      eqList.innerHTML = '';
      (j.equipment || []).forEach(r => addEqRow(r.item_name, r.qty));
      if (eqList.children.length === 0) addEqRow();
    }
    if (lbList) {
      lbList.innerHTML = '';
      (j.labor || []).forEach(r => addLbRow(r.person_name, r.qty));
      if (lbList.children.length === 0) addLbRow();
    }

    renderThumbs(j.images || []);
    setFormDisabled(!j.editable);
  }

  function parseJsonSafe(response) {
    return response.text().then(text => {
      if (!text || !text.trim()) throw new Error(`Empty response (HTTP ${response.status}).`);
      try { return JSON.parse(text); }
      catch (e) { throw new Error('Bad JSON: ' + text.slice(0, 400)); }
    });
  }

  function postForm(fd) {
    return fetch(apiUrl(`project_id=${projectId}`), { method:'POST', body:fd, cache:'no-store', credentials:'same-origin' })
      .then(parseJsonSafe)
      .then(j => { if (!j.ok) throw new Error(j.message || 'Server error'); return j; });
  }

  // ---- Search by name, code, date ----
  function normalize(s){ return (s||'').toString().toLowerCase(); }
  function applySearch() {
    if (!tbody) return;
    const q = normalize(searchBox ? searchBox.value : '');
    if (!q) { $$('.dl-row', tbody).forEach(tr => tr.style.display = ''); return; }
    $$('.dl-row', tbody).forEach(tr => {
      const name = normalize(tr.getAttribute('data-name'));
      const code = normalize(tr.getAttribute('data-code'));
      const date = normalize(tr.getAttribute('data-date'));
      tr.style.display = (name.includes(q) || code.includes(q) || date.includes(q)) ? '' : 'none';
    });
  }
  if (searchBox) searchBox.addEventListener('input', applySearch);

  function bindRowHandlers() {
    if (!tbody) return;
    $$('.dl-open', tbody).forEach(a => {
      a.addEventListener('click', (e) => {
        e.preventDefault();
        const id = parseInt(a.getAttribute('data-id') || '0', 10);
        if (!id) return;
        fetch(apiUrl(`action=get_log&project_id=${projectId}&id=${id}`), { cache:'no-store', credentials:'same-origin' })
          .then(parseJsonSafe)
          .then(j => { clearForm(); fillFormFromData(j); if (modal) { modal.style.display='flex'; document.body.classList.add('dl-modal-open'); } })
          .catch(err => alert('Failed to open: ' + err.message));
      });
    });

    $$('.dl-delete', tbody).forEach(btn => {
      btn.addEventListener('click', () => {
        const id = parseInt(btn.getAttribute('data-id') || '0', 10);
        if (!id) return;
        if (!confirm('Delete this log?')) return;
        const fd = new FormData(); fd.append('dl_action','delete'); fd.append('id', String(id));
        postForm(fd).then(()=>{ alert('Deleted.'); location.reload(); })
                    .catch(err=> alert('Delete failed: ' + err.message));
      });
    });
  }

  // Close modal and lightbox
  if (modal){
    const closeModal = () => { modal.style.display='none'; document.body.classList.remove('dl-modal-open'); };
    modal.querySelector('.dl-close')?.addEventListener('click', closeModal);
    modal.querySelector('.dl-cancel')?.addEventListener('click', closeModal);
    window.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
  }
  const lightboxEl = lightbox;
  if (lightboxEl){
    lightboxEl.addEventListener('click', (e)=>{ if (e.target === lightboxEl || e.target.classList.contains('close')) lightboxEl.classList.remove('open'); });
  }

  if (btnCreate) btnCreate.addEventListener('click', () => { clearForm(); if (modal) { modal.style.display='flex'; document.body.classList.add('dl-modal-open'); } });
  if (eqAdd) eqAdd.addEventListener('click', () => addEqRow());
  if (lbAdd) lbAdd.addEventListener('click', () => addLbRow());

  if (form) form.addEventListener('submit', (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    if (!(form.querySelector('input[name="code"]').value || '').trim() ||
        !(form.querySelector('input[name="name"]').value || '').trim()) {
      alert('Please fill in Code and Name.'); return;
    }
    postForm(fd).then(j => { alert(j.message || 'Saved.'); location.reload(); })
                .catch(err => alert('Save failed: ' + err.message));
  });

  bindRowHandlers();
})();
