// Meetings Tab JS (updated endpoint to use project_view.php proxy)
(function () {
  const root = document.getElementById('meetings-root');
  if (!root) return;
  const projectId = root.getAttribute('data-project');
  const canControl = root.getAttribute('data-can-control') === '1';

  const tbody = document.getElementById('mt-tbody');
  const btnSearch = document.getElementById('mt-btn-search');
  const btnReset = document.getElementById('mt-btn-reset');
  const inputQ = document.getElementById('mt-q');
  const inputFrom = document.getElementById('mt-date-from');
  const inputTo = document.getElementById('mt-date-to');

  const modal = document.getElementById('mt-modal');
  const modalTitle = document.getElementById('mt-modal-title');
  const btnCreate = document.getElementById('mt-btn-create');
  const btnClose = modal?.querySelector('.mt-modal-close');
  const btnCancel = modal?.querySelector('.mt-cancel');
  const btnSave = modal?.querySelector('.mt-save');

  const fId = document.getElementById('mt-f-id');
  const fTitle = document.getElementById('mt-f-title');
  const fShort = document.getElementById('mt-f-short');
  const fLink = document.getElementById('mt-f-link');
  const fLocation = document.getElementById('mt-f-location');
  const fStart = document.getElementById('mt-f-start');

  // IMPORTANT: Use project_view.php proxy instead of calling partial directly,
  // so $pdo, $projectId, $userId are always available.
  const endpoint = `?ajax_meetings=1&project_id=${encodeURIComponent(projectId)}`;

  function showModal(editData) {
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    if (editData) {
      modalTitle.textContent = 'Chỉnh sửa cuộc họp';
      fId.value = editData.id;
      fTitle.value = editData.title || '';
      fShort.value = editData.short_desc || '';
      fLink.value = editData.online_link || '';
      fLocation.value = editData.location || '';
      fStart.value = toDatetimeLocal(editData.start_time);
    } else {
      modalTitle.textContent = 'Tạo cuộc họp';
      fId.value = '';
      fTitle.value = '';
      fShort.value = '';
      fLink.value = '';
      fLocation.value = '';
      fStart.value = '';
    }
    fTitle.focus();
  }

  function hideModal() {
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
  }

  function toDatetimeLocal(sqlDateTime) {
    if (!sqlDateTime) return '';
    const [d, t] = String(sqlDateTime).split(' ');
    if (!t) return d + 'T00:00';
    return d + 'T' + t.slice(0,5);
  }

  async function fetchList() {
    tbody.innerHTML = `<tr><td colspan="5" class="muted">Loading...</td></tr>`;
    const fd = new FormData();
    fd.append('action', 'list');
    if (inputQ.value.trim()) fd.append('q', inputQ.value.trim());
    if (inputFrom.value) fd.append('date_from', inputFrom.value);
    if (inputTo.value) fd.append('date_to', inputTo.value);

    const res = await fetch(endpoint, { method: 'POST', body: fd, credentials: 'same-origin' });
    let json = {};
    try { json = await res.json(); } catch(e) {}
    if (!json.ok) {
      const msg = json.message || 'Không tải được danh sách cuộc họp.';
      tbody.innerHTML = `<tr><td colspan="5" class="error">${escapeHtml(msg)}</td></tr>`;
      return;
    }
    const rows = json.data || [];
    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="5" class="muted">Không có cuộc họp nào.</td></tr>`;
      return;
    }
    tbody.innerHTML = rows.map(r => {
      const titleLink = `<a href="./project_meeting_detail.php?project_id=${encodeURIComponent(projectId)}&id=${encodeURIComponent(r.id)}" class="mt-title">${escapeHtml(r.title || '(no title)')}</a>`;
      const createdAt = (r.created_at || '').replace('T',' ').slice(0,16);
      const actions = r.can_edit
        ? `<button class="link mt-edit" data-id="${r.id}">Edit</button>
           <button class="link danger mt-del" data-id="${r.id}">Delete</button>`
        : `<span class="muted">—</span>`;
      return `<tr data-row-id="${r.id}">
        <td>${titleLink}</td>
        <td>${escapeHtml(r.creator_name || '')}</td>
        <td>${escapeHtml(createdAt)}</td>
        <td>${escapeHtml(r.location || '')}</td>
        <td class="mt-actions">${actions}</td>
      </tr>`;
    }).join('');
  }

  function escapeHtml(s) {
    return (s ?? '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }

  async function createOrUpdate() {
    const title = fTitle.value.trim();
    const start = fStart.value;
    if (!title || !start) { alert('Title và Start time là bắt buộc.'); return; }
    const isEdit = !!fId.value;
    const fd = new FormData();
    fd.append('action', isEdit ? 'update' : 'create');
    if (isEdit) fd.append('id', fId.value);
    fd.append('title', title);
    fd.append('short_desc', fShort.value.trim());
    fd.append('online_link', fLink.value.trim());
    fd.append('location', fLocation.value.trim());
    fd.append('start_time', start.replace('T', ' ') + ':00');

    const res = await fetch(endpoint, { method: 'POST', body: fd, credentials: 'same-origin' });
    let json = {};
    try { json = await res.json(); } catch(e) {}
    if (!json.ok) {
      const err = json.error || '';
      if (err === 'ACCESS_DENIED') alert('⚠️ Bạn không có quyền truy cập Tab Meetings của dự án này.');
      else if (err === 'NO_PRIVILEGE') alert('⚠️ Chỉ vai trò control mới được tạo/chỉnh sửa.');
      else if (err === 'NOT_OWNER') alert('⚠️ Bạn chỉ có thể chỉnh sửa/xóa cuộc họp do bạn tạo.');
      else alert('Có lỗi xảy ra khi lưu.');
      return;
    }
    hideModal();
    fetchList();
  }

  async function doDelete(id) {
    if (!confirm('Xóa cuộc họp này?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    const res = await fetch(endpoint, { method: 'POST', body: fd, credentials: 'same-origin' });
    let json = {};
    try { json = await res.json(); } catch(e) {}
    if (!json.ok) {
      if (json.error === 'NOT_OWNER') alert('⚠️ Bạn chỉ có thể xóa cuộc họp do bạn tạo.');
      else alert('Không thể xóa.');
      return;
    }
    fetchList();
  }

  // Events
  btnSearch?.addEventListener('click', fetchList);
  btnReset?.addEventListener('click', () => { inputQ.value = ''; inputFrom.value = ''; inputTo.value=''; fetchList(); });
  btnCreate?.addEventListener('click', () => showModal(null));
  btnClose?.addEventListener('click', hideModal);
  btnCancel?.addEventListener('click', hideModal);
  btnSave?.addEventListener('click', createOrUpdate);

  tbody?.addEventListener('click', e => {
    const t = e.target;
    if (t.classList.contains('mt-edit')) {
      const id = t.getAttribute('data-id');
      fetch(`?ajax_meetings=1&project_id=${encodeURIComponent(projectId)}`, {
        method: 'POST', body: new URLSearchParams({action:'list'}), credentials:'same-origin'
      })
      .then(r=>r.json()).then(j=>{
        if (j.ok && Array.isArray(j.data)) {
          const row = j.data.find(x => String(x.id) === String(id));
          if (row) showModal(row);
        }
      });
    }
    if (t.classList.contains('mt-del')) {
      const id = t.getAttribute('data-id');
      doDelete(id);
    }
  });

  // Initial load
  fetchList();
})();