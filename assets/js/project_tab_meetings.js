// Meetings Tab JS — minimal, robust: unified search + head sort + create/edit/delete modal wiring
(function () {
  const root = document.getElementById('meetings-root') || document;
  const tbody = document.getElementById('mt-tbody') || root.querySelector('tbody');
  const inputQ = document.getElementById('mt-q'); // 1 ô search duy nhất
  const table = tbody ? tbody.closest('table') : null;
  const thead = table ? table.querySelector('thead') : null;
  const headerRow = thead ? thead.querySelector('tr') : null;
  const projectId = (root.getAttribute && root.getAttribute('data-project')) || (new URLSearchParams(location.search)).get('project_id') || '';
  const COLSPAN = headerRow ? Math.max(1, headerRow.children.length) : 6;

  // Tìm ô head Start Time (ưu tiên id, fallback theo nhãn VI/EN)
  let thStart = document.getElementById('mt-th-start');
  if (!thStart && headerRow) {
    thStart = Array.from(headerRow.children).find(th => {
      const txt = (th.textContent || '').trim().toLowerCase();
      return txt.includes('start time') || txt.includes('ngày giờ bắt đầu') || txt.includes('ngày giờ họp');
    }) || null;
  }

  // ==== Helpers ====
  function esc(s) {
    return (s ?? '').toString().replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
  }
  function fmt(dt) {
    if (!dt) return '';
    const s = String(dt).replace('T', ' ');
    return s.slice(0, 16);
  }
  function host(href) {
    try { return new URL(href).host || 'Join'; } catch { return (href || '').split('/')[2] || 'Join'; }
  }
  function setLoading() { if (tbody) tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="muted">Loading...</td></tr>`; }
  function setEmpty() { if (tbody) tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="muted">Không có cuộc họp nào.</td></tr>`; }
  function setError(msg) { if (tbody) tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="error">${esc(msg)}</td></tr>`; }

  // Endpoint cũ: ưu tiên ajax_meetings=1 (POST), sau đó ajax=1&tab=meetings (GET list)
  function buildEndpointCandidates() {
    const url = new URL(window.location.href);
    const pidQs = projectId ? `project_id=${encodeURIComponent(projectId)}` : '';
    const e1 = `${url.pathname}?ajax_meetings=1${pidQs ? '&' + pidQs : ''}`;                       // legacy: POST
    const e2 = `${url.pathname}?ajax=1&tab=meetings${pidQs ? '&' + pidQs : ''}`;                   // tab-based: GET list
    const e3 = `pages/partials/project_tab_meetings.php?ajax=1&tab=meetings${pidQs ? '&' + pidQs : ''}`; // fallback
    const uniq = [];
    [e1, e2, e3].forEach(u => { if (!uniq.includes(u)) uniq.push(u); });
    return uniq;
  }

  async function postJson(url, formData) {
    const res = await fetch(url, { method: 'POST', body: formData, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    const text = await res.text();
    try { return JSON.parse(text); }
    catch {
      const first = text.indexOf('{'), last = text.lastIndexOf('}');
      if (first !== -1 && last !== -1 && last > first) {
        try { return JSON.parse(text.slice(first, last + 1)); } catch {}
      }
      return { ok: false, message: 'Non-JSON response', _raw: text };
    }
  }
  async function getJson(url, paramsObj) {
    const u = new URL(url, window.location.origin);
    if (paramsObj) {
      Object.keys(paramsObj).forEach(k => {
        const v = paramsObj[k];
        if (v !== undefined && v !== null && String(v).length) u.searchParams.set(k, v);
      });
    }
    const res = await fetch(u.toString(), { method: 'GET', credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    const text = await res.text();
    try { return JSON.parse(text); }
    catch {
      const first = text.indexOf('{'), last = text.lastIndexOf('}');
      if (first !== -1 && last !== -1 && last > first) {
        try { return JSON.parse(text.slice(first, last + 1)); } catch {}
      }
      return { ok: false, message: 'Non-JSON response', _raw: text };
    }
  }

  // Chọn phương thức theo endpoint + action
  async function callList(fd) {
    const eps = buildEndpointCandidates();
    let action = '';
    try { action = fd.get('action') || ''; } catch (e) {}
    const params = {};
    if (fd) for (const [k, v] of fd.entries()) params[k] = v;

    for (let i = 0; i < eps.length; i++) {
      const url = eps[i];
      try {
        let j;
        if (url.includes('ajax_meetings=1')) {
          j = await postJson(url, fd);
        } else {
          if (action && action !== 'list') continue; // create/update/delete chỉ đi qua legacy
          j = await getJson(url, params);
        }
        if (j && j.ok) return j;
        if (j && j.error && j.error !== 'NOT_FOUND') return j;
      } catch { /* thử endpoint kế */ }
    }
    return { ok: false, message: 'All endpoints failed' };
  }

  // ===== Render + cache để Edit không cần gọi detail =====
  const rowCache = new Map();
  function render(rows) {
    rowCache.clear();
    rows.forEach(r => rowCache.set(String(r.id), r));

    if (!rows.length) return setEmpty();
    const html = rows.map(r => {
      const start = fmt(r.start_time);
      const online = r.online_link
        ? `<a href="${esc(r.online_link)}" class="mt-link-plain" target="_blank" rel="noopener">Link</a>`
        : `<span class="mt-muted">—</span>`;
      const title = `<a href="./project_meeting_detail.php?project_id=${encodeURIComponent(projectId)}&id=${encodeURIComponent(r.id)}" class="mt-title">${esc(r.title || '(no title)')}</a>`;
      return `<tr data-row-id="${r.id}">
        <td>${title}${r.short_desc ? `<div class="mt-muted">${esc(r.short_desc)}</div>` : ''}</td>
        <td>${esc(start)}</td>
        <td>${esc(r.location || '')}</td>
        <td>${online}</td>
        <td>${esc(r.creator_name || '')}</td>
        <td class="mt-actions">
          ${r.can_edit
            ? `<button class="link mt-edit" data-id="${r.id}">Edit</button>
               <button class="link danger mt-del" data-id="${r.id}">Delete</button>`
            : `<span class="muted">—</span>`}
        </td>
      </tr>`;
    }).join('');
    tbody.innerHTML = html;
  }

  // ===== List load =====
  let sortDir = 'asc';
  let debounceTimer = null;

  function updateSortHead() {
    if (!thStart && headerRow) {
      thStart = Array.from(headerRow.children).find(th => {
        const txt = (th.textContent || '').trim().toLowerCase();
        return txt.includes('start time') || txt.includes('ngày giờ bắt đầu') || txt.includes('ngày giờ họp');
      }) || null;
    }
    if (!thStart) return;
    const base = (thStart.getAttribute('data-label') || thStart.textContent || 'Start Time')
      .replace(/[↑↓]\s*$/, '').trim();
    thStart.setAttribute('data-label', base);
    thStart.textContent = `${base} ${sortDir === 'asc' ? '↑' : '↓'}`;
    thStart.style.cursor = 'pointer';
    thStart.title = 'Sort by Start Time';
  }

  async function fetchList() {
    if (!tbody) return;
    setLoading();
    const fd = new FormData();
    fd.append('action', 'list');
    if (projectId) fd.append('project_id', projectId);
    const q = (inputQ && inputQ.value) ? inputQ.value.trim() : '';
    if (q) fd.append('q', q);
    fd.append('sort', sortDir); // backend xử lý asc/desc/ASC/DESC

    const json = await callList(fd);
    if (!json || !json.ok) {
      setError((json && (json.message || json.error)) || 'Không tải được danh sách cuộc họp.');
      return;
    }
    const rows = Array.isArray(json.data) ? json.data : [];
    render(rows);
  }

  // ===== Modal & các nút (Create/Edit/Delete) =====
  function showModal(m) {
    if (!m) return;
    m.classList.remove('hidden');
    m.classList.add('open');
    m.setAttribute('aria-hidden', 'false');
    m.style.display = 'block';
  }
  function hideModal(m) {
    if (!m) return;
    m.classList.add('hidden');
    m.classList.remove('open');
    m.setAttribute('aria-hidden', 'true');
    m.style.display = 'none';
  }
  function toLocalInput(str) {
    if (!str) return '';
    const s = String(str).trim().replace(' ', 'T');
    return s.slice(0, 16);
  }

  const modal = document.getElementById('mt-modal') || document.querySelector('.mt-modal') || document.querySelector('[data-modal="meeting"]');
  const btnClose = modal ? (modal.querySelector('.mt-modal-close,[data-dismiss="modal"]')) : null;
  const btnCancel = modal ? (modal.querySelector('.mt-cancel')) : null;
  const btnSave = modal ? (modal.querySelector('.mt-save,#mt-btn-save')) : null;

  const fId = modal ? (modal.querySelector('#mt-f-id,[name="id"]')) : null;
  const fTitle = modal ? (modal.querySelector('#mt-f-title,[name="title"]')) : null;
  const fShort = modal ? (modal.querySelector('#mt-f-short,[name="short_desc"],textarea')) : null;
  const fLink = modal ? (modal.querySelector('#mt-f-link,#mt-f-online,[name="online_link"]')) : null;
  const fLocation = modal ? (modal.querySelector('#mt-f-location,[name="location"]')) : null;
  const fStart = modal ? (modal.querySelector('#mt-f-start,[name="start_time"],input[type="datetime-local"]')) : null;

  function resetForm() {
    if (fId) fId.value = '';
    if (fTitle) fTitle.value = '';
    if (fShort) fShort.value = '';
    if (fLink) fLink.value = '';
    if (fLocation) fLocation.value = '';
    if (fStart) fStart.value = '';
  }

  // Nút "Tạo cuộc họp" (rộng selector để khớp markup khác nhau)
  const btnCreate =
    document.getElementById('mt-btn-create') ||
    document.querySelector('.mt-btn-create,[data-action="create"]') ||
    Array.from(document.querySelectorAll('button, a, .btn')).find(n => {
      const t = (n.textContent || '').trim().toLowerCase();
      return ['tạo cuộc họp', 'create meeting', 'thêm cuộc họp', 'new meeting'].includes(t);
    });

  function openCreate() {
    if (!modal) return;
    resetForm();
    showModal(modal);
    if (fTitle) fTitle.focus();
  }

  function openEditById(id) {
    if (!modal) return;
    const r = rowCache.get(String(id));
    if (!r) return;
    if (fId) fId.value = String(r.id || '');
    if (fTitle) fTitle.value = r.title || '';
    if (fShort) fShort.value = r.short_desc || '';
    if (fLink) fLink.value = r.online_link || '';
    if (fLocation) fLocation.value = r.location || '';
    if (fStart) fStart.value = toLocalInput(r.start_time || '');
    showModal(modal);
    if (fTitle) fTitle.focus();
  }

  async function createOrUpdate() {
    if (!fTitle || !fStart) return alert('Please fill Title and Start Time.');
    const title = (fTitle.value || '').trim();
    const start = (fStart.value || '').trim();
    if (!title || !start) return alert('Please fill Title and Start Time.');

    const isUpdate = !!(fId && fId.value);
    const fd = new FormData();
    fd.append('action', isUpdate ? 'update' : 'create');
    if (isUpdate) fd.append('id', String(fId.value));
    fd.append('title', title);
    if (fShort) fd.append('short_desc', fShort.value || '');
    if (fLink) fd.append('online_link', fLink.value || '');
    if (fLocation) fd.append('location', fLocation.value || '');
    fd.append('start_time', start);
    if (projectId) fd.append('project_id', projectId);

    const res = await callList(fd);
    if (!res || !res.ok) {
      alert(res && (res.message || res.error) ? res.message || res.error : 'Request failed');
      return;
    }
    hideModal(modal);
    fetchList();
  }

  async function deleteMeeting(id) {
    if (!id) return;
    if (!confirm('Xóa cuộc họp này?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', String(id));
    if (projectId) fd.append('project_id', projectId);
    const res = await callList(fd);
    if (!res || !res.ok) {
      alert(res && (res.message || res.error) ? res.message || res.error : 'Delete failed');
      return;
    }
    fetchList();
  }

  // Gắn sự kiện các nút
  if (btnCreate) btnCreate.addEventListener('click', function (ev) { ev.preventDefault(); openCreate(); });
  if (btnSave) btnSave.addEventListener('click', function (ev) { ev.preventDefault(); createOrUpdate(); });
  if (btnCancel) btnCancel.addEventListener('click', function (ev) { ev.preventDefault(); hideModal(modal); });
  if (btnClose) btnClose.addEventListener('click', function (ev) { ev.preventDefault(); hideModal(modal); });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') hideModal(modal); });

  // Delegation cho Edit/Delete ở từng hàng
  if (tbody) {
    tbody.addEventListener('click', function (ev) {
      const editBtn = ev.target.closest('.mt-edit,[data-action="edit"]');
      const delBtn = ev.target.closest('.mt-del,[data-action="delete"]');
      if (editBtn) {
        ev.preventDefault();
        const id = editBtn.getAttribute('data-id') || (editBtn.closest('tr') && editBtn.closest('tr').getAttribute('data-row-id'));
        if (id) openEditById(id);
        return;
      }
      if (delBtn) {
        ev.preventDefault();
        const id = delBtn.getAttribute('data-id') || (delBtn.closest('tr') && delBtn.closest('tr').getAttribute('data-row-id'));
        if (id) deleteMeeting(id);
      }
    });
  }

  // Unified live search + head sort
  function onSearchInput() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fetchList, 300);
  }
  if (inputQ) inputQ.addEventListener('input', onSearchInput);

  function toggleSort() {
    sortDir = (sortDir === 'asc') ? 'desc' : 'asc';
    updateSortHead();
    fetchList();
  }
  if (thStart) {
    updateSortHead();
    thStart.addEventListener('click', toggleSort);
  }

  // Initial load
  fetchList();
})();
