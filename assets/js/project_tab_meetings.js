// Meetings Tab JS (robust loader + unified search + header sort)
// Keeps everything else intact. Works with both old and new endpoints.
(function () {
  const root = document.getElementById('meetings-root');
  if (!root) return;

  const projectId = root.getAttribute('data-project');
  const canControl = root.getAttribute('data-can-control') === '1';
  const tbody = document.getElementById('mt-tbody');
  const inputQ = document.getElementById('mt-q');

  // Determine COLSPAN from current thead (fallback 6)
  const thead = (tbody && tbody.closest('table')) ? tbody.closest('table').querySelector('thead') : null;
  const headerRow = thead ? thead.querySelector('tr') : null;
  const COLSPAN = headerRow ? Math.max(1, headerRow.children.length) : 6;

  // Find the Start Time header cell to integrate sort (id preferred, otherwise by label)
  let thStart = document.getElementById('mt-th-start');
  if (!thStart && headerRow) {
    // Find th that has text "Start Time" (case-insensitive, trimming spaces)
    thStart = Array.from(headerRow.children).find(th => {
      const txt = (th.textContent || '').trim().toLowerCase();
      return txt === 'start time' || txt.includes('start time');
    }) || null;
  }

  // Local state
  let sortDir = 'asc'; // default
  let abortController = null;
  let debounceTimer = null;

  // Build endpoint candidates (try in order until one returns valid JSON with ok=true)
  function buildEndpointCandidates() {
    const baseQs = `project_id=${encodeURIComponent(projectId)}`;
    const href = window.location.href;
    const url = new URL(href);

    // 1) Current page proxy handling (preferred in your project)
    const e1 = `${url.pathname}?ajax_meetings=1&${baseQs}`;

    // 2) Same page with older pattern (?ajax=1&tab=meetings)
    const e2 = `${url.pathname}?ajax=1&tab=meetings&${baseQs}`;

    // 3) Partial path variants (relative)
    const e3 = `pages/partials/project_tab_meetings.php?ajax=1&tab=meetings&${baseQs}`;
    const e4 = `/pages/partials/project_tab_meetings.php?ajax=1&tab=meetings&${baseQs}`;
    const e5 = `./pages/partials/project_tab_meetings.php?ajax=1&tab=meetings&${baseQs}`;

    // 6) Bare partial (no tab)
    const e6 = `pages/partials/project_tab_meetings.php?ajax=1&${baseQs}`;
    const e7 = `/pages/partials/project_tab_meetings.php?ajax=1&${baseQs}`;

    // Ensure uniqueness while preserving order
    const uniq = [];
    [e1, e2, e3, e4, e5, e6, e7].forEach(u => { if (!uniq.includes(u)) uniq.push(u); });
    return uniq;
  }

  async function postJson(url, formData) {
    const res = await fetch(url, { method: 'POST', body: formData, credentials: 'same-origin' });
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch (e) {
      const first = text.indexOf('{');
      const last = text.lastIndexOf('}');
      if (first !== -1 && last !== -1 && last > first) {
        try { return JSON.parse(text.slice(first, last + 1)); } catch(_) {}
      }
      return { ok: false, message: 'Server returned non-JSON response', _raw: text };
    }
  }

  async function callList(fd) {
    const endpoints = buildEndpointCandidates();
    for (let i = 0; i < endpoints.length; i++) {
      try {
        const json = await postJson(endpoints[i], fd);
        if (json && json.ok) return json;
        if (json && json.error && json.error !== 'NOT_FOUND') return json;
      } catch (e) {}
    }
    return { ok: false, message: 'All endpoints failed' };
  }

  function formatDateTime(dt) {
    if (!dt) return '';
    const s = String(dt).replace('T', ' ');
    return s.slice(0, 16);
  }

  function escapeHtml(s) {
    return (s ?? '').toString().replace(/[&<>"']/g, m => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[m]);
  }

  function hostFromUrl(href) {
    try { return new URL(href).host || 'Join'; } catch(e) {
      const m = (href || '').split('/')[2]; return m || 'Join';
    }
  }

  function setLoading() {
    if (tbody) tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="muted">Loading...</td></tr>`;
  }
  function setError(msg) {
    if (tbody) tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="error">${escapeHtml(msg)}</td></tr>`;
  }
  function setEmpty() {
    if (tbody) tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="muted">Không có cuộc họp nào.</td></tr>`;
  }

  async function fetchList() {
    if (!tbody) return;
    try { abortController?.abort(); } catch(_) {}
    abortController = new AbortController();

    setLoading();

    const fd = new FormData();
    fd.append('action', 'list');
    const q = (inputQ && inputQ.value) ? inputQ.value.trim() : '';
    if (q) fd.append('q', q);
    if (sortDir) fd.append('sort', sortDir);

    let json;
    try {
      json = await callList(fd);
    } catch (e) {
      setError('Không tải được danh sách cuộc họp.');
      return;
    }

    if (!json || !json.ok) {
      const msg = (json && (json.message || json.error)) ? (json.message || json.error) : 'Không tải được danh sách cuộc họp.';
      setError(msg);
      return;
    }

    const rows = Array.isArray(json.data) ? json.data : [];
    if (!rows.length) { setEmpty(); return; }

    const html = rows.map(r => {
      const titleLink = `<a href="./project_meeting_detail.php?project_id=${encodeURIComponent(projectId)}&id=${encodeURIComponent(r.id)}" class="mt-title">${escapeHtml(r.title || '(no title)')}</a>`;
      const startTime = formatDateTime(r.start_time);
      const onlineCol = r.online_link
        ? `<a href="${escapeHtml(r.online_link)}" class="chip" target="_blank" rel="noopener">🔗 ${escapeHtml(hostFromUrl(r.online_link))}</a>`
        : `<span class="badge">Offline</span>`;
      const creator = r.creator_name || '';
      const actions = r.can_edit
        ? `<button class="link mt-edit" data-id="${r.id}">Edit</button>
           <button class="link danger mt-del" data-id="${r.id}">Delete</button>`
        : `<span class="muted">—</span>`;
      return `<tr data-row-id="${r.id}">
        <td>${titleLink}</td>
        <td>${escapeHtml(startTime)}</td>
        <td>${escapeHtml(r.location || '')}</td>
        <td>${onlineCol}</td>
        <td>${escapeHtml(creator)}</td>
        <td class="mt-actions">${actions}</td>
      </tr>`;
    }).join('');

    tbody.innerHTML = html;
  }

  // Header sort integration
  function updateSortVisual() {
    // fallback to header by text if id not provided
    const labelBase = 'Start Time';
    if (!thStart && headerRow) {
      thStart = Array.from(headerRow.children).find(th => {
        const txt = (th.textContent || '').trim().toLowerCase();
        return txt === 'start time' || txt.includes('start time');
      }) || null;
    }
    if (!thStart) return;
    const base = (thStart.getAttribute('data-label') || thStart.textContent || '').replace(/[↑↓]\s*$/, '').trim() || labelBase;
    thStart.setAttribute('data-label', base);
    thStart.textContent = `${base} ${sortDir === 'asc' ? '↑' : '↓'}`;
    thStart.style.cursor = 'pointer';
    thStart.title = 'Sort by Start Time';
  }
  if (headerRow) {
    updateSortVisual();
    (thStart || headerRow.children[1])?.addEventListener('click', () => {
      sortDir = (sortDir === 'asc') ? 'desc' : 'asc';
      updateSortVisual();
      fetchList();
    });
  }

  // Unified live search (debounce)
  if (inputQ) {
    inputQ.addEventListener('input', () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(fetchList, 300);
    });
  }

  // Row actions (edit/delete)
  tbody?.addEventListener('click', e => {
    const t = e.target;
    if (t.classList.contains('mt-edit')) {
      const id = t.getAttribute('data-id');
      const fd = new FormData();
      fd.append('action', 'list');
      callList(fd).then(j => {
        if (j && j.ok && Array.isArray(j.data)) {
          const row = j.data.find(x => String(x.id) === String(id));
          if (!row) return;
          const modal = document.getElementById('mt-modal');
          const modalTitle = document.getElementById('mt-modal-title');
          const fId = document.getElementById('mt-f-id');
          const fTitle = document.getElementById('mt-f-title');
          const fShort = document.getElementById('mt-f-short');
          const fLink = document.getElementById('mt-f-link');
          const fLocation = document.getElementById('mt-f-location');
          const fStart = document.getElementById('mt-f-start');
          if (modal && modalTitle && fId && fTitle && fStart) {
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
            modalTitle.textContent = 'Chỉnh sửa cuộc họp';
            fId.value = row.id;
            fTitle.value = row.title || '';
            if (fShort) fShort.value = row.short_desc || '';
            if (fLink) fLink.value = row.online_link || '';
            if (fLocation) fLocation.value = row.location || '';
            if (fStart) {
              const s = formatDateTime(row.start_time).replace(' ', 'T');
              fStart.value = s.length === 16 ? s : (s ? s + ':00' : '');
            }
            fTitle.focus();
          }
        }
      });
    }
    if (t.classList.contains('mt-del')) {
      const id = t.getAttribute('data-id');
      if (!confirm('Xóa cuộc họp này?')) return;
      const fd = new FormData();
      fd.append('action', 'delete');
      fd.append('id', id);
      callList(fd).then(j => fetchList());
    }
  });

  // Initial load
  fetchList();
})();
