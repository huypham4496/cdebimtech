(() => {
  const root = document.getElementById('daily-logs-root');
  if (!root) return;

  const API = '/pages/partials/project_tab_daily.php';
  const projectId = +root.dataset.projectId;
  const currentUser = +(root.dataset.currentUser || 0);

  const tBody = document.querySelector('#daily-logs-table tbody');
  const emptyHint = document.getElementById('daily-empty');
  const btnCreate = document.getElementById('btn-create-daily');
  const btnExport = document.getElementById('btn-export');
  const inputSearch = document.getElementById('daily-search');

  const modal = document.getElementById('daily-modal');
  const form = document.getElementById('daily-form');
  const modalTitle = document.getElementById('modal-title');
  const btnSubmit = document.getElementById('btn-submit');
  const btnCancel = document.getElementById('btn-cancel');
  const btnClose = document.getElementById('modal-close');
  const btnApprove = document.getElementById('btn-approve');

  const equipRows = document.getElementById('equip-rows');
  const laborRows = document.getElementById('labor-rows');
  const btnAddEq = document.getElementById('btn-add-eq');
  const btnAddLb = document.getElementById('btn-add-lb');
  const imagesInput = document.getElementById('f-images');
  const fileChosen = document.getElementById('file-chosen');
  const imagePreview = document.getElementById('image-preview');

  const fId = document.getElementById('f-id');
  const fCode = document.getElementById('f-code');
  const fDate = document.getElementById('f-entry-date');
  const fName = document.getElementById('f-name');
  const fWM = document.getElementById('f-wm');
  const fWA = document.getElementById('f-wa');
  const fWE = document.getElementById('f-we');
  const fWN = document.getElementById('f-wn');
  const fDetails = document.getElementById('f-details');
  const fClean = document.getElementById('f-clean');
  const fSafety = document.getElementById('f-safety');
  const fApproval = document.getElementById('f-approval');

  const toast = document.getElementById('daily-toast');

  function showToast(msg, ok=true) {
    toast.textContent = msg;
    toast.classList.toggle('bad', !ok);
    toast.hidden = false;
    setTimeout(() => { toast.hidden = true; }, 2500);
  }

  async function fetchJSON(url, opts) {
    const res = await fetch(url, opts);
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.ok === false) {
      throw new Error(data.message || `Request failed (${res.status})`);
    }
    return data;
  }

  function escapeHtml(s){ return (s ?? '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  function rowTpl(item) {
    const creator = (item.first_name || '') + ' ' + (item.last_name || '');
    const status = +item.is_approved === 1 ? 'Approved' : 'Pending';
    const delDisabled = (+item.is_approved === 1 || +item.created_by !== currentUser);
    return `
      <tr data-id="${item.id}">
        <td>${escapeHtml(item.code)}</td>
        <td>${escapeHtml(item.entry_date || '')}</td>
        <td><button class="link row-open" title="Open">${escapeHtml(item.name)}</button></td>
        <td>${escapeHtml(creator.trim())}</td>
        <td>${escapeHtml(item.group_name || '')}</td>
        <td><span class="st ${status==='Approved'?'ok':'pending'}">${status}</span></td>
        <td class="center">
          <button class="icon-btn row-del" title="Delete" ${delDisabled?'disabled':''}>
            <i class="fa-solid fa-trash-can"></i>
          </button>
        </td>
      </tr>
    `;
  }

  async function loadList() {
    const q = inputSearch.value.trim();
    const url = `${API}?action=list&project_id=${projectId}&q=${encodeURIComponent(q)}`;
    try {
      const data = await fetchJSON(url);
      tBody.innerHTML = '';
      if (!data.items || data.items.length === 0) {
        emptyHint.style.display = 'block';
      } else {
        emptyHint.style.display = 'none';
        tBody.innerHTML = data.items.map(rowTpl).join('');
      }
    } catch (e) {
      showToast(e.message, false);
    }
  }

  // Debounce search
  let tSearch;
  inputSearch?.addEventListener('input', () => {
    clearTimeout(tSearch);
    tSearch = setTimeout(loadList, 350);
  });

  btnExport?.addEventListener('click', () => {
    const q = inputSearch.value.trim();
    window.location.href = `${API}?action=export&project_id=${projectId}&q=${encodeURIComponent(q)}`;
  });

  // Modal helpers
  function openModal(mode='create') {
    modal.hidden = false;
    document.body.classList.add('daily-modal-open');
    modalTitle.innerHTML = mode==='create'
      ? `<i class="fa-regular fa-calendar-check"></i> New Daily Log`
      : `<i class="fa-regular fa-pen-to-square"></i> Edit Daily Log`;
    btnSubmit.textContent = mode==='create' ? 'Create' : 'Save changes';
    btnApprove.hidden = true;
  }
  function closeModal() {
    modal.hidden = true;
    document.body.classList.remove('daily-modal-open');
    form.reset();
    fId.value = '';
    equipRows.innerHTML = '';
    laborRows.innerHTML = '';
    imagePreview.innerHTML = '';
    if (fileChosen) fileChosen.textContent = 'No files selected';
  }

  btnCreate?.addEventListener('click', () => {
    openModal('create');
    addEqRow(); addLbRow();
  });
  btnCancel?.addEventListener('click', closeModal);
  btnClose?.addEventListener('click', closeModal);

  // Dynamic rows
  function addEqRow(valName='', valQty='') {
    const row = document.createElement('div');
    row.className = 'row';
    row.innerHTML = `
      <i class="fa-solid fa-screwdriver-wrench"></i>
      <input type="text" placeholder="Name..." value="${escapeHtml(valName)}" data-k="name">
      <input type="number" min="0" step="1" placeholder="Qty" value="${escapeHtml(valQty)}" data-k="qty">
      <button class="icon-btn danger" title="Remove"><i class="fa-solid fa-xmark"></i></button>
    `;
    row.querySelector('button').addEventListener('click', () => row.remove());
    equipRows.appendChild(row);
  }
  function addLbRow(valName='', valQty='') {
    const row = document.createElement('div');
    row.className = 'row';
    row.innerHTML = `
      <i class="fa-solid fa-people-group"></i>
      <input type="text" placeholder="Name..." value="${escapeHtml(valName)}" data-k="name">
      <input type="number" min="0" step="1" placeholder="Qty" value="${escapeHtml(valQty)}" data-k="qty">
      <button class="icon-btn danger" title="Remove"><i class="fa-solid fa-xmark"></i></button>
    `;
    row.querySelector('button').addEventListener('click', () => row.remove());
    laborRows.appendChild(row);
  }
  btnAddEq?.addEventListener('click', () => addEqRow());
  btnAddLb?.addEventListener('click', () => addLbRow());

  // File input preview + label text
  imagesInput?.addEventListener('change', () => {
    imagePreview.innerHTML = '';
    const files = Array.from(imagesInput.files || []).slice(0, 4);
    if (fileChosen) {
      if (files.length === 0) fileChosen.textContent = 'No files selected';
      else if (files.length === 1) fileChosen.textContent = files[0].name;
      else fileChosen.textContent = `${files.length} files selected`;
    }
    files.forEach(f => {
      const url = URL.createObjectURL(f);
      const img = document.createElement('img');
      img.src = url;
      img.onload = () => URL.revokeObjectURL(url);
      imagePreview.appendChild(img);
    });
  });

  // Table actions: open & delete
  tBody?.addEventListener('click', async (e) => {
    const tr = e.target.closest('tr');
    if (!tr) return;
    const id = +tr.dataset.id;

    if (e.target.closest('.row-open')) {
      try {
        const res = await fetchJSON(`${API}?action=read&project_id=${projectId}&id=${id}`);
        openModal('edit');
        fillForm(res);
      } catch (err) { showToast(err.message, false); }
    }

    if (e.target.closest('.row-del')) {
      if (!confirm('Delete this daily log? This action cannot be undone.')) return;
      try {
        const fd = new FormData();
        fd.append('id', id);
        const res = await fetchJSON(`${API}?action=delete&project_id=${projectId}`, { method: 'POST', body: fd });
        showToast(res.message);
        loadList();
      } catch (err) { showToast(err.message, false); }
    }
  });

  function getRowsData(container) {
    const rows = container.querySelectorAll('.row');
    const arr = [];
    rows.forEach(r => {
      const name = r.querySelector('input[data-k="name"]')?.value.trim() || '';
      const qty = r.querySelector('input[data-k="qty"]')?.value || '';
      if (name !== '') arr.push({ name, qty: +qty || 0 });
    });
    return arr;
  }

  function setDisabledAll(disabled) {
    form.querySelectorAll('input,select,textarea,button').forEach(el => {
      if (el === btnCancel || el === btnClose) return;
      if (el === btnApprove) return;
      el.disabled = disabled;
    });
  }

  function fillForm(res) {
    const { log, equipment, labor, images, canEdit, canApprove } = res;

    fId.value = log.id;
    fCode.value = log.code || '';
    fDate.value = log.entry_date || '';
    fName.value = log.name || '';
    fWM.value = log.weather_morning || '';
    fWA.value = log.weather_afternoon || '';
    fWE.value = log.weather_evening || '';
    fWN.value = log.weather_night || '';
    fDetails.value = log.work_details || '';
    fClean.value = log.cleanliness || 'normal';
    fSafety.value = log.safety || 'normal';
    fApproval.value = log.approval_group_id || '0';

    equipRows.innerHTML = '';
    (equipment || []).forEach(e => addEqRow(e.item_name, e.qty));
    if ((equipment || []).length === 0) addEqRow();

    laborRows.innerHTML = '';
    (labor || []).forEach(l => addLbRow(l.labor_name, l.qty));
    if ((labor || []).length === 0) addLbRow();

    imagePreview.innerHTML = '';
    (images || []).forEach(img => {
      const im = document.createElement('img');
      im.src = `/${img.file_path}`;
      imagePreview.appendChild(im);
    });
    if (fileChosen) fileChosen.textContent = (images || []).length ? `${(images || []).length} uploaded` : 'No files selected';

    setDisabledAll(!canEdit);
    btnSubmit.hidden = !canEdit;
    btnApprove.hidden = !canApprove;
    if (!canEdit && imagesInput) imagesInput.value = '';
  }

  // Approve (disable while waiting)
  btnApprove?.addEventListener('click', async () => {
    const id = +fId.value || 0;
    if (!id) return;
    if (!confirm('Approve this daily log?')) return;

    btnApprove.disabled = true;
    btnApprove.classList.add('loading');
    try {
      const fd = new FormData();
      fd.append('id', id);
      const res = await fetchJSON(`${API}?action=approve&project_id=${projectId}`, { method: 'POST', body: fd });
      showToast(res.message);
      closeModal();
      loadList();
    } catch (e) {
      showToast(e.message, false);
    } finally {
      btnApprove.disabled = false;
      btnApprove.classList.remove('loading');
    }
  });

  // Submit (create/update)
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();

    const isEdit = !!fId.value;
    const action = isEdit ? 'update' : 'create';

    const equipment = getRowsData(equipRows);
    const labor = getRowsData(laborRows);

    const fd = new FormData(form);
    fd.set('equipment', JSON.stringify(equipment));
    fd.set('labor', JSON.stringify(labor));

    try {
      const res = await fetchJSON(`${API}?action=${action}&project_id=${projectId}`, {
        method: 'POST',
        body: fd
      });
      showToast(res.message);
      closeModal();
      loadList();
    } catch (err) { showToast(err.message, false); }
  });

  // init
  loadList();
})();
