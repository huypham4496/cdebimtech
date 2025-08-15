// assets/js/project_tab_meetings.js
(function(){
  const root = document.getElementById('cde-meetings');
  if (!root) return;

  const projectId = root.dataset.projectId;
  const userId = root.dataset.userId;
  const canControl = root.dataset.canControl === '1';

  // Your structure: /pages/project_view.php and /pages/partials/...
  // Call the gateway on project_view.php (must be added there) and exit before page rendering.
  const endpoint = '/pages/project_view.php?ajax_meetings=1';

  // Elements
  const tbody = document.getElementById('mt-tbody');
  const btnCreate = document.getElementById('mt-btn-create');
  const modal = document.getElementById('mt-modal');    // hidden by default
  const form = document.getElementById('mt-form');
  const modalTitle = document.getElementById('mt-modal-title');
  const btnSave = document.getElementById('mt-btn-save');

  const btnSearch = document.getElementById('mt-btn-search');
  const btnClear = document.getElementById('mt-btn-clear');
  const inpKw = document.getElementById('mt-search-text');
  const inpDate = document.getElementById('mt-search-date');

  // Detail drawer
  const drawer = document.getElementById('mt-detail');  // hidden by default
  const dtTitle = document.getElementById('dt-title');
  const dtMeta = document.getElementById('dt-meta');
  const dtBtnClose = document.getElementById('dt-btn-close');
  const dtBtnExport = document.getElementById('dt-btn-export');

  const dtStartAt = document.getElementById('dt-start-at');
  const dtLocation = document.getElementById('dt-location');
  const dtOnline = document.getElementById('dt-online-link');
  const dtShort = document.getElementById('dt-short-desc');
  const dtEditor = document.getElementById('dt-editor');
  const dtBtnSaveContent = document.getElementById('dt-btn-save-content');
  const dtMembers = document.getElementById('dt-project-members');
  const dtExternal = document.getElementById('dt-external');
  const dtBtnNotify = document.getElementById('dt-btn-notify');

  let currentId = null;
  let creatorId = null;

  // Hide modal/drawer hard just in case
  if (modal) modal.setAttribute('hidden','');
  if (drawer) drawer.setAttribute('hidden','');

  // Utilities
  const ajax = async (params, method='POST') => {
    const data = new URLSearchParams(params);
    data.append('ajax', '1'); // signal ajax mode for the partial
    let url = endpoint;
    let fetchInit = {
      method: method || 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: data.toString(),
      credentials:'same-origin'
    };
    if (method === 'GET') {
      url = endpoint + '&' + data.toString();
      fetchInit = { method: 'GET', credentials:'same-origin' };
    }
    const res = await fetch(url, fetchInit);
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch (e) {
      console.error('Meetings AJAX non-JSON response:', text);
      return { ok:false, error:'NON_JSON', message:text, status: res.status };
    }
  };

  const fmtDate = (s) => {
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d)) return s;
    return d.toLocaleString();
  };

  const toast = (msg, type='info') => {
    const t = document.createElement('div');
    t.className = `cde-toast ${type}`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(()=> t.classList.add('show'), 10);
    setTimeout(()=> { t.classList.remove('show'); setTimeout(()=> t.remove(), 300); }, 3000);
  };

  const openModal = (isEdit=false, row=null) => {
    if (!modal) return;
    modal.removeAttribute('hidden'); // open only when user clicks "Create meeting" or "Edit"
    if (!isEdit) {
      modalTitle.textContent = 'Create meeting';
      form.reset();
      document.getElementById('mt-id').value = '';
    } else {
      modalTitle.textContent = 'Update meeting';
      document.getElementById('mt-id').value = row.id;
      document.getElementById('mt-title').value = row.title || '';
      document.getElementById('mt-start-at').value = row.start_at ? row.start_at.replace(' ', 'T').slice(0,16) : '';
      document.getElementById('mt-location').value = row.location || '';
      document.getElementById('mt-online-link').value = row.online_link || '';
      document.getElementById('mt-short-desc').value = row.short_desc || '';
    }
  };
  const closeModal = () => { if (modal) modal.setAttribute('hidden',''); }

  // Close modal buttons
  Array.prototype.forEach.call(document.querySelectorAll('[data-close="mt-modal"]'), function(btn){
    btn.addEventListener('click', closeModal);
  });

  // Create button (open modal on click only)
  if (btnCreate) btnCreate.addEventListener('click', ()=> {
    if (!canControl) { toast('You do not have permission to create a meeting.', 'error'); return; }
    openModal(false);
  });

  // Save (create/update)
  if (btnSave) btnSave.addEventListener('click', async ()=> {
    const fd = new FormData(form);
    const id = fd.get('id');
    const action = id ? 'update' : 'create';
    const payload = {
      action,
      project_id: projectId, user_id: userId,
      id: id || '',
      title: fd.get('title'),
      start_at: fd.get('start_at'),
      location: fd.get('location'),
      online_link: fd.get('online_link'),
      short_desc: fd.get('short_desc')
    };
    const json = await ajax(payload, 'POST');
    if (!json.ok) { toast(json.message || json.error || 'Unknown error', 'error'); return; }
    toast('Saved', 'success');
    closeModal();
    loadList();
  });

  // Search
  const doSearch = ()=> {
    loadList(inpKw ? inpKw.value.trim() : '', inpDate ? inpDate.value : '');
  };
  if (btnSearch) btnSearch.addEventListener('click', doSearch);
  if (btnClear) btnClear.addEventListener('click', ()=> { if (inpKw) inpKw.value=''; if (inpDate) inpDate.value=''; doSearch(); });

  // Load list
  async function loadList(q='', date='') {
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="5" class="txt-center muted">Loading...</td></tr>`;
    const json = await ajax({ action:'list', project_id:projectId, user_id:userId, q, date }, 'GET');
    if (!json.ok) {
      const msg = json.message || json.error || 'Error';
      tbody.innerHTML = `<tr><td colspan="5" class="txt-center text-error">${escapeHtml(msg)}</td></tr>`;
      return;
    }
    const items = json.items || [];
    if (!items.length) {
      tbody.innerHTML = `<tr><td colspan="5" class="txt-center muted">No meetings</td></tr>`;
      return;
    }
    tbody.innerHTML = items.map(function(r){ return rowHtml(r, json); }).join('');
    bindRowEvents(items, json);
  }

  function rowHtml(r, meta) {
    const isOwner = String(meta.user_id) === String(r.created_by);
    const canEdit = meta.can_control && isOwner;
    return `<tr data-id="${r.id}">
      <td><button class="link-btn mt-row-title">${escapeHtml(r.title||'No title')}</button></td>
      <td>${escapeHtml(r.creator_name||('User#'+r.created_by))}</td>
      <td>${fmtDate(r.created_at)}</td>
      <td>${escapeHtml(r.location||'')}</td>
      <td class="row-actions">
        ${canEdit ? `<button class="btn btn-ghost sm mt-row-edit">Edit</button>`:''}
        ${canEdit ? `<button class="btn btn-danger sm mt-row-del">Delete</button>`:''}
      </td>
    </tr>`;
  }

  function bindRowEvents(items, meta) {
    const titleBtns = tbody.querySelectorAll('.mt-row-title');
    Array.prototype.forEach.call(titleBtns, function(btn, idx){
      const r = items[idx];
      btn.addEventListener('click', function(){ openDetail(r.id); });
    });

    const editBtns = tbody.querySelectorAll('.mt-row-edit');
    Array.prototype.forEach.call(editBtns, function(btn){
      btn.addEventListener('click', function(){
        const row = items.find(function(x){ return String(x.created_by) === String(meta.user_id); });
        if (row) openModal(true, row);
      });
    });

    const delBtns = tbody.querySelectorAll('.mt-row-del');
    Array.prototype.forEach.call(delBtns, function(btn){
      btn.addEventListener('click', async function(){
        const tr = btn.closest('tr');
        const id = tr ? tr.getAttribute('data-id') : null;
        if (!id) return;
        if (!confirm('Delete this meeting?')) return;
        const json = await ajax({ action:'delete', project_id:projectId, user_id:userId, id: id }, 'POST');
        if (!json.ok) { toast(json.message || json.error || 'Delete failed', 'error'); return; }
        toast('Deleted', 'success');
        doSearch();
      });
    });
  }

  async function openDetail(id) {
    const json = await ajax({ action:'get', project_id:projectId, user_id:userId, id: id }, 'GET');
    if (!json.ok) { toast(json.message || json.error || 'Failed to load details', 'error'); return; }
    const m = json.meeting;
    currentId = m.id;
    creatorId = json.creator_id;
    if (drawer) drawer.removeAttribute('hidden');
    dtTitle.textContent = m.title || 'Meeting';
    dtMeta.textContent = `Created by ${m.creator_name||('User#'+m.created_by)} • ${fmtDate(m.created_at)}`;
    dtStartAt.value = m.start_at ? m.start_at.replace(' ', 'T').slice(0,16) : '';
    dtLocation.value = m.location || '';
    dtOnline.value = m.online_link || '';
    dtShort.value = m.short_desc || '';
    dtEditor.innerHTML = json.content || '';

    // permissions
    const editable = (canControl && String(creatorId) === String(userId));
    [dtStartAt, dtLocation, dtOnline, dtShort, dtEditor, dtBtnSaveContent, dtBtnNotify].forEach(function(el){
      if (!el) return;
      if (editable) { el.removeAttribute('disabled'); dtEditor.setAttribute('contenteditable','true'); }
      else { el.setAttribute('disabled',''); dtEditor.setAttribute('contenteditable','false'); }
    });

    // load members
    loadMembers(json.participants || []);
  }

  function closeDetail() { if (drawer) drawer.setAttribute('hidden',''); currentId = null; }
  if (dtBtnClose) dtBtnClose.addEventListener('click', closeDetail);

  // Inline update of summary fields
  [dtStartAt, dtLocation, dtOnline, dtShort].forEach(function(el){
    if (!el) return;
    el.addEventListener('change', async function(){
      if (!currentId) return;
      const json = await ajax({
        action:'update', project_id:projectId, user_id:userId, id: currentId,
        title: dtTitle.textContent,
        start_at: dtStartAt.value,
        location: dtLocation.value,
        online_link: dtOnline.value,
        short_desc: dtShort.value
      }, 'POST');
      if (!json.ok) { toast(json.message||json.error||'Update failed', 'error'); return; }
      toast('Updated', 'success');
      loadList(inpKw ? inpKw.value.trim() : '', inpDate ? inpDate.value : '');
    });
  });

  // Save content
  if (dtBtnSaveContent) dtBtnSaveContent.addEventListener('click', async function(){
    if (!currentId) return;
    const json = await ajax({ action:'save_content', project_id:projectId, user_id:userId, id: currentId, content: dtEditor.innerHTML }, 'POST');
    if (!json.ok) { toast(json.message||json.error||'Save failed', 'error'); return; }
    toast('Saved', 'success');
  });

  // Members loading and save
  async function loadMembers(selected) {
    dtMembers.innerHTML = 'Loading...';
    const json = await ajax({ action:'members', project_id:projectId, user_id:userId }, 'GET');
    if (!json.ok) { dtMembers.textContent = json.message || json.error || 'Error'; return; }
    const set = {};
    (selected || []).forEach(function(x){
      if (x.user_id) set[String(x.user_id)] = true;
    });
    dtMembers.innerHTML = (json.items || []).map(function(u){
      var checked = set[String(u.id)] ? 'checked' : '';
      return '<label class="person"><input type="checkbox" data-user-id="'+u.id+'" '+checked+'/> <span>'+escapeHtml(u.name||('User#'+u.id))+'</span></label>';
    }).join('');
    // external prefill
    const externals = (selected || []).filter(function(x){ return !x.is_internal; }).map(function(x){ return x.external_name || x.external_contact || ''; }).filter(Boolean);
    dtExternal.value = externals.join('\n');
  }

  if (dtBtnNotify) dtBtnNotify.addEventListener('click', async function(){
    if (!currentId) return;
    const internal = Array.prototype.map.call(dtMembers.querySelectorAll('input[type="checkbox"]:checked'), function(i){ return i.dataset.userId; });
    const json1 = await ajax({ action:'save_participants', project_id:projectId, user_id:userId, id: currentId, internal: JSON.stringify(internal), external: dtExternal.value }, 'POST');
    if (!json1.ok) { toast(json1.message||json1.error||'Failed to save participants', 'error'); return; }
    const json2 = await ajax({ action:'notify', project_id:projectId, user_id:userId, id: currentId }, 'POST');
    if (!json2.ok) { toast(json2.message||json2.error||'Failed to notify', 'error'); return; }
    toast('Saved & notifications created', 'success');
  });

  // Export placeholder
  if (dtBtnExport) dtBtnExport.addEventListener('click', function(){
    toast('Export to Word will be implemented later.', 'info');
  });

  function escapeHtml(s){
    return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  // Initial load
  loadList();

})();