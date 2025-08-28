// ==============================
// project_tab_files.js
// ==============================
(function(){
  const $ = (sel, ctx=document)=>ctx.querySelector(sel);
  const $$ = (sel, ctx=document)=>Array.from(ctx.querySelectorAll(sel));

  const state = {
    currentFolderId: null,
    tree: [],
    selected: new Set(),
    uploads: [],
    searching: false,
  };
function pushSelect(type, id, on){
  id = Number(id);
  const i = state.selectedItems.findIndex(x=>x.type===type && x.id===id);
  if(on && i<0) state.selectedItems.push({type, id});
  if(!on && i>=0) state.selectedItems.splice(i,1);
}
  const ajaxUrl = window.CDE_FILES.ajaxUrl;
  const projectId = window.CDE_FILES.projectId;

  // ---------------- Helpers ----------------

  function verText(f){
    const n = (x)=>{ const v = Number(x); return isFinite(v) && v>0 ? v : 0; };
    const tv = n(f.total_versions) || (Array.isArray(f.versions) ? f.versions.length : n(f.max_version) || n(f.version) || 1);
    const cv = n(f.current_version) || n(f.max_version) || n(f.version) || tv || 1;
    return `${cv||1}/${tv||1}`;
  }

  function api(action, params={}, method='GET', isForm=false){
    const url = new URL(ajaxUrl, window.location.origin);
    url.searchParams.set('action', action);
    if(method === 'GET'){
      Object.entries(params||{}).forEach(([k,v])=>{
        if(v !== undefined && v !== null && v !== '') url.searchParams.set(k, v);
      });
      return fetch(url, { credentials: 'same-origin' }).then(async r=>{
        if(action==='download') return r;
        const ct = r.headers.get('content-type') || '';
        if(ct.includes('application/json')) return r.json();
        const txt = await r.text();
        return { ok:false, error:'Server returned non-JSON', html:txt };
      });
    } else {
      let body;
      if(isForm){
        body = params;
      } else if(params instanceof URLSearchParams){
        body = params;
      } else {
        body = new URLSearchParams(params);
      }
      return fetch(url, { method, body, credentials:'same-origin' }).then(async r=>{
        if(action==='download') return r;
        const ct = r.headers.get('content-type') || '';
        if(ct.includes('application/json')) return r.json();
        const txt = await r.text();
        return { ok:false, error:'Server returned non-JSON', html:txt };
      });
    }
  }

  function fmtSize(n){
    if(n==null) return '';
    const u=['B','KB','MB','GB','TB'];
    let i=0, x=Number(n);
    while(x>=1024 && i<u.length-1){ x/=1024; i++; }
    return (x<10&&i>0?x.toFixed(1):Math.round(x))+' '+u[i];
  }

  function timeago(iso){
    if(!iso) return '';
    const d = new Date(String(iso).replace(' ','T'));
    return d.toLocaleString();
  }

  function extIcon(name){
    const m = String(name).split('.');
    const ext = (m.length>1?m.pop():'').toLowerCase();
    const color = {
      pdf:'#ef4444', doc:'#2563eb', docx:'#2563eb',
      xls:'#059669', xlsx:'#059669',
      ppt:'#d97706', pptx:'#d97706',
      ifc:'#10b981', dwg:'#7c3aed', rvt:'#0ea5e9', rfa:'#0ea5e9', nwc:'#f43f5e'
    }[ext] || '#9ca3af';
    return `<span class="thumb" style="background:${color}"></span><span class="ext">${ext || 'file'}</span>`;
  }

  // ---------------- Tree ----------------
  function buildTree(rows){
    const byId = new Map(rows.map(r=>[r.id, {...r, children:[]}]));
    rows.forEach(r=>{
      const node = byId.get(r.id);
      if(r.parent_id && byId.has(r.parent_id)){
        byId.get(r.parent_id).children.push(node);
      }
    });
    return rows.filter(r=>!r.parent_id).map(r=>byId.get(r.id));
  }

  function renderTree(){
    const container = $('#ft-tree');
    if(state.searching) { container.innerHTML = ''; return; }
    container.innerHTML = '';

    function nodeHtml(node, parentEl){
      const row = document.createElement('div');
      row.className = 'node' + (state.currentFolderId===node.id ? ' active' : '');
      row.innerHTML = `<i class="fas fa-folder"></i><span>${node.name}</span>`;
      row.addEventListener('click', ()=>{
        state.currentFolderId = node.id;
        loadItems(node.id);
        renderTree();
      });
      parentEl.appendChild(row);
      if(node.children && node.children.length){
        const wrap = document.createElement('div');
        wrap.className = 'children';
        parentEl.appendChild(wrap);
        node.children.slice().sort((a,b)=>a.name.localeCompare(b.name)).forEach(ch=>nodeHtml(ch, wrap));
      }
    }

    state.tree.forEach(n=>nodeHtml(n, container));
  }

  // ---------------- Table ----------------
  function renderTable(data){
    const tb = $('#ft-table tbody'); tb.innerHTML = '';
    state.selected.clear();
    $('#ft-select-all').checked = false;

    // Folders
    (data.folders||[]).forEach(f=>{
      const tr = document.createElement('tr');
      tr.dataset.id = f.id;
      tr.dataset.type = 'folder';
      tr.innerHTML = `
  <td class="center"><input type="checkbox" class="ft-row-sel"></td>
  <td class="center">—</td>
  <td class="ft-name"><div class="filetype"><i class="fas fa-folder"></i><span>${f.name}</span></div></td>
  <td><span class="badge">—</span></td>
  <td class="center">—</td>
  <td class="right">—</td>
  <td>${timeago(f.created_at)}</td>
  <td>—</td>
  <td class="actions">
    <button class="icon-btn open-btn" title="Open"><i class="fas fa-level-down-alt"></i></button>
    <button class="icon-btn" title="Delete"><i class="fas fa-trash-alt"></i></button>
  </td>`;
// (A) Checkbox chọn folder
const cbFolder = tr.querySelector('.ft-row-sel');
cbFolder.addEventListener('change', (e)=>{
  const on = e.target.checked;
  pushSelect('folder', f.id, on);
});

// (B) Click vào row/tên folder để mở (bỏ qua khi click vào checkbox/nút)
tr.addEventListener('click', (e)=>{
  if (e.target.closest('.actions, button, input[type="checkbox"], a[href]')) return;
  state.currentFolderId = f.id;
  loadItems(f.id);
});

      // 1) Open button
      tr.querySelector('.open-btn').addEventListener('click', ()=>{
        state.currentFolderId = f.id; loadItems(f.id);
      });

      // 2) Row/name click -> open (trừ khi click vào actions/checkbox/link)
      tr.addEventListener('click', (e)=>{
        if (e.target.closest('.actions, button, input, a[href]')) return;
        state.currentFolderId = f.id; loadItems(f.id);
      });

      // 3) Delete folder
      tr.querySelectorAll('.icon-btn')[1].addEventListener('click', ()=>{
        openDeleteModal([{type:'folder', id:f.id}]);
      });

      tb.appendChild(tr);
    });

    // Files
    (data.files||[]).forEach(file=>{
      const tr = document.createElement('tr');
      tr.dataset.id = file.id;
      tr.dataset.type = 'file';
      (function(){ try { const parts = String(file.filename).split('.'); tr.dataset.ext = (parts.length>1?parts.pop():'').toLowerCase(); } catch(e){} })();
      (function(){ try { const parts = String(file.filename).split('.'); tr.dataset.ext = (parts.length>1?parts.pop():'').toLowerCase(); } catch(e){} })();
      tr.innerHTML = `
        <td class="center"><input type="checkbox" class="ft-row-sel"></td>
        <td class="center">${file.is_important ? '⭐' : ''}</td>
        <td class="ft-name">${file.name_html || ('<div class="filetype">'+extIcon(file.filename)+'<span>'+file.filename+'</span></div>')}</td>
        <td><span class="badge ${file.tag}">${file.tag}</span></td>
        <td class="center">${ verText(file) }</td>
        <td class="right">${fmtSize(file.size_bytes)}</td>
        <td>${timeago(file.updated_at)}</td>
        <td>${file.created_by || ''}</td>
        <td class="actions">
          <button class="icon-btn more"><i class="fas fa-ellipsis-v"></i></button>
        </td>`;

      const cb = tr.querySelector('.ft-row-sel');
cb.addEventListener('change', (e)=>{
  pushSelect('file', file.id, e.target.checked);
});

      tr.querySelector('.more').addEventListener('click', (e)=>showRowMenu(e.currentTarget, file));
      tb.appendChild(tr);
    });
  }

  function showRowMenu(btn, file){
    const menu = document.createElement('div');
    menu.className = 'popup-menu';
    const rect = btn.getBoundingClientRect();
    menu.style.top = (window.scrollY + rect.bottom + 6)+'px';
    menu.style.left = (window.scrollX + rect.right - 200)+'px';
    menu.innerHTML = `
      <div class="mi" data-act="download"><i class="fas fa-download"></i> Download</div>
      <div class="mi" data-act="versions"><i class="fas fa-history"></i> Versions & Restore</div>
      <div class="mi" data-act="toggle-important"><i class="fas fa-star"></i> Toggle important</div>
      <div class="mi" data-act="set-tag" data-tag="WIP">Set tag: WIP</div>
      <div class="mi" data-act="set-tag" data-tag="Shared">Set tag: Shared</div>
      <div class="mi" data-act="set-tag" data-tag="Published">Set tag: Published</div>
      <div class="mi" data-act="set-tag" data-tag="Archived">Set tag: Archived</div>
      <div class="mi danger" data-act="delete"><i class="fas fa-trash-alt"></i> Delete</div>
    `;
    document.body.appendChild(menu);
    const cleanup = ()=>{ menu.remove(); document.removeEventListener('click', off); };
    const off = (ev)=>{ if(!menu.contains(ev.target) && ev.target!==btn) cleanup(); };
    setTimeout(()=>document.addEventListener('click', off),0);

    $$('.mi', menu).forEach(mi=>{
      mi.addEventListener('click', async ()=>{
        const act = mi.dataset.act;
        if(act==='download'){
          window.open(ajaxUrl + '&action=download_one&file_id=' + file.id, '_blank');
        } else if(act==='versions'){
          openVersionsModal(file);
        } else if(act==='toggle-important'){
          const r = await api('toggle_important', {file_id:file.id}, 'POST');
          if(r && r.ok) loadItems(state.currentFolderId);
        } else if(act==='set-tag'){
          const tag = mi.dataset.tag;
          const r = await api('set_tag', {file_id:file.id, tag}, 'POST');
          if(r && r.ok) loadItems(state.currentFolderId);
        } else if(act==='delete'){
          openDeleteModal([{type:'file', id:file.id}]);
        }
        cleanup();
      });
    });
  }

  // ---------------- Loaders ----------------
  async function loadTree(){
    const r = await api('list_tree', {}, 'GET');
    if(!r || r.ok===false){ alert((r && r.error) || 'Failed to load tree'); console.debug('list_tree detail:', r&&r.detail, r&&r.html); return; }
    const rows = r.tree || [];
    state.tree = buildTree(rows);
    if(!state.currentFolderId) state.currentFolderId = r.root_id;
    renderTree();
  }

  async function loadItems(folderId){
    const r = await api('list_items', { folder_id: folderId }, 'GET');
    if(!r || r.ok===false){ alert((r && r.error) || 'Failed to load items'); console.debug('list_items detail:', r&&r.detail, r&&r.html); return; }
    renderTree();
    renderTable(r);
  }

  // ---------------- Search ----------------
  async function doSearch(){
    const q = $('#ft-search-input').value.trim();
    const tag = $('#ft-filter-tag').value || '';
    const params = { q, tag };
    if($('#ft-important-only').checked){ params.important = 1; }
    state.searching = (q.length>0 || tag.length>0 || $('#ft-important-only').checked);
    const r = await api('search', params, 'GET');
    if(!r || r.ok===false){ alert((r && r.error) || 'Search failed'); console.debug('search detail:', r&&r.detail, r&&r.html); return; }
    // hide tree during searching
    renderTree();
    const tb = $('#ft-table tbody'); tb.innerHTML = '';
    (r.results||[]).forEach(file=>{
      const tr = document.createElement('tr');
      tr.dataset.id = file.id;
      tr.dataset.type = 'file';
      (function(){ try { const parts = String(file.filename).split('.'); tr.dataset.ext = (parts.length>1?parts.pop():'').toLowerCase(); } catch(e){} })();
      (function(){ try { const parts = String(file.filename).split('.'); tr.dataset.ext = (parts.length>1?parts.pop():'').toLowerCase(); } catch(e){} })();
      tr.innerHTML = `
        <td class="center"><input type="checkbox" class="ft-row-sel"></td>
        <td class="center">${file.is_important ? '⭐' : ''}</td>
        <td class="ft-name">${file.name_html || ('<div class="filetype">'+extIcon(file.filename)+'<span>'+file.filename+'</span></div>')}</td>
        <td><span class="badge ${file.tag}">${file.tag}</span></td>
        <td class="center">${ verText(file) }</td>
        <td class="right">${fmtSize(file.size_bytes)}</td>
        <td>${timeago(file.updated_at)}</td>
        <td>${file.created_by || ''}</td>
        <td class="actions">
          <button class="icon-btn more"><i class="fas fa-ellipsis-v"></i></button>
        </td>`;
      tr.querySelector('.more').addEventListener('click', (e)=>showRowMenu(e.currentTarget, file));
      tb.appendChild(tr);
    });
  }

  // ---------------- Upload modal & drag-drop ----------------
  function openModal(id){ $(id).hidden = false; }
  function closeModal(el){ el.closest('.ft-modal').hidden = true; }
  $$('.ft-modal [data-close]').forEach(b=>b.addEventListener('click', e=>closeModal(e.currentTarget)));

  $('#ft-upload-btn').addEventListener('click', ()=>openModal('#ft-upload-modal'));
  $('#ft-create-folder-btn').addEventListener('click', ()=>openModal('#ft-create-folder-modal'));

  const drop = $('#ft-dropzone');
  drop.addEventListener('click', ()=>$('#ft-file-input').click());

  function handleFiles(files){
    state.uploads = Array.from(files).map(f=>({ file:f, progress:0, done:false }));
    renderUploadList();
  }

  $('#ft-file-input').addEventListener('change', e=>handleFiles(e.target.files));
  drop.addEventListener('dragover', e=>{ e.preventDefault(); drop.classList.add('dragover'); });
  drop.addEventListener('dragleave', ()=>drop.classList.remove('dragover'));
  drop.addEventListener('drop', e=>{
    e.preventDefault();
    drop.classList.remove('dragover');
    handleFiles(e.dataTransfer.files);
  });

  function renderUploadList(){
    const ul = $('#ft-upload-list'); ul.innerHTML = '';
    state.uploads.forEach((u)=>{
      const li = document.createElement('li');
      li.innerHTML = `<span>${u.file.name}</span>
        <div style="display:flex;align-items:center;gap:8px">
          <div class="ft-progress"><div style="width:${u.progress}%"></div></div>
          <span class="pct">${u.progress}%</span>
        </div>`;
      ul.appendChild(li);
    });
  }

  $('#ft-start-upload').addEventListener('click', async ()=>{
    if(state.uploads.length===0) return;
    for(let i=0;i<state.uploads.length;i++){
      const fd = new FormData();
      fd.append('folder_id', state.currentFolderId);
      fd.append('project_id', projectId);
      fd.append('action', 'upload');
      fd.append('files[]', state.uploads[i].file, state.uploads[i].file.name);
      const r = await fetch(ajaxUrl + '&action=upload', { method:'POST', body:fd, credentials:'same-origin' }).then(x=>x.json()).catch(()=>({ok:false}));
      state.uploads[i].progress = r && r.ok ? 100 : 0;
      renderUploadList();
    }
    $('#ft-upload-modal').hidden = true;
    loadItems(state.currentFolderId);
  });

  // ---------------- Create folder ----------------
  $('#ft-create-folder-confirm').addEventListener('click', async ()=>{
    const name = $('#ft-new-folder-name').value.trim();
    if(!name) return alert('Folder name required');
    const r = await api('create_folder', { parent_id: state.currentFolderId, name }, 'POST');
    if(!r || r.ok===false){
      alert(((r && r.error) || 'Failed to create folder') + (r && r.detail ? ('\nDetail: ' + r.detail) : ''));
      console.debug('create_folder detail:', r && r.detail, r && r.html);
      return;
    }
    $('#ft-create-folder-modal').hidden = true;
    $('#ft-new-folder-name').value = '';
    await loadTree(); await loadItems(state.currentFolderId);
  });

  // ---------------- Delete ----------------
  $('#ft-delete-btn').addEventListener('click', ()=>{
    const items = Array.from(state.selected).map(id=>({type:'file', id}));
    if(items.length===0){ alert('Chọn ít nhất 1 tệp'); return; }
    openDeleteModal(items);
  });

  function openDeleteModal(items){
    const modal = $('#ft-delete-modal');
    modal.hidden = false;
    $('#ft-delete-confirm-btn').onclick = async ()=>{
      const confirmText = $('#ft-delete-confirm').value.trim();
      const r = await api('delete', { confirm: confirmText, items: JSON.stringify(items) }, 'POST');
      if(!r || r.ok===false){ alert((r && r.error) || 'Delete failed'); return; }
      modal.hidden = true;
      $('#ft-delete-confirm').value = '';
      loadItems(state.currentFolderId);
    };
  }

  // ---------------- Download ----------------
  async function downloadFiles(ids){
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = ajaxUrl + '&action=download';
    form.style.display = 'none';
    const ta = document.createElement('input');
    ta.type = 'hidden'; ta.name = 'file_ids'; ta.value = JSON.stringify(ids);
    form.appendChild(ta);
    document.body.appendChild(form);
    form.submit();
    setTimeout(()=>form.remove(), 2000);
  }

$('#ft-download-btn').addEventListener('click', ()=>{
  const items = state.selectedItems.slice(); // [{type:'file'|'folder', id}]
  if(items.length===0){ alert('Chọn ít nhất 1 mục'); return; }

  // Nếu chỉ có 1 mục và đó là "file" -> tận dụng download_one
  if(items.length===1 && items[0].type === 'file'){
    window.open(ajaxUrl + '&action=download_one&file_id=' + items[0].id, '_blank');
    return;
  }

  // Ngược lại (nhiều file hoặc có folder) -> POST 'items' JSON để server zip
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = ajaxUrl + '&action=download';
  form.style.display = 'none';
  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'items';
  input.value = JSON.stringify(items);
  form.appendChild(input);
  document.body.appendChild(form);
  form.submit();
  setTimeout(()=>form.remove(), 2000);
});

  // ---------------- Select all ----------------
$('#ft-select-all').addEventListener('change', (e)=>{
  const on = e.target.checked;
  state.selectedItems = []; // reset
  $$('#ft-table tbody .ft-row-sel').forEach(cb=>{
    cb.checked = on;
    const tr = cb.closest('tr');
    const id = Number(tr.dataset.id);
    const type = tr.dataset.type; // 'file' | 'folder'
    if(on) state.selectedItems.push({type, id});
  });
});

  // ---------------- Versions modal ----------------
  function openVersionsModal(file){
    const modal = document.createElement('div');
    modal.className = 'ft-modal';
    modal.innerHTML = `
      <div class="ft-modal-dialog">
        <div class="ft-modal-header">
          <h3>Versions – ${file.filename}</h3>
          <button class="icon close" data-close>&times;</button>
        </div>
        <div class="ft-modal-body">
          <div id="ft-vers-list">Loading…</div>
        </div>
        <div class="ft-modal-footer">
          <button class="btn" data-close>Close</button>
          <button class="btn primary" id="ft-restore-btn" disabled>Restore selected</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
    modal.querySelectorAll('[data-close]').forEach(b=>b.addEventListener('click', ()=>modal.remove()));

    api('get_versions', { file_id: file.id }, 'GET').then(r=>{
      const host = modal.querySelector('#ft-vers-list');
      if(!r || r.ok===false){ host.textContent = (r && r.error) || 'Failed to load versions'; return; }
      if(!r.versions || r.versions.length===0){ host.textContent = 'No versions'; return; }
      const wrap = document.createElement('div');
      wrap.className = 'vers-wrap';
      r.versions.forEach(v=>{
        const row = document.createElement('label');
        row.className = 'vers-row';
        row.innerHTML = `
          <input type="radio" name="pick-version" value="${v.version}">
          <span class="vers-v">v${v.version}</span>
          <span class="vers-size">${fmtSize(v.size_bytes)}</span>
          <span class="vers-time">${timeago(v.created_at)}</span>
        `;
        wrap.appendChild(row);
      });
      host.innerHTML = '';
      host.appendChild(wrap);
      const restoreBtn = modal.querySelector('#ft-restore-btn');
      host.addEventListener('change', e=>{
        if(e.target && e.target.name==='pick-version'){ restoreBtn.disabled = false; }
      });
      restoreBtn.addEventListener('click', async ()=>{
        const picked = $('input[name="pick-version"]:checked', host);
        if(!picked) return;
        const form = new URLSearchParams();
        form.set('file_id', file.id);
        form.set('version', picked.value);
        const r2 = await api('restore_version', form, 'POST');
        if(!r2 || r2.ok===false){ alert((r2 && r2.error) || 'Failed to restore'); return; }
        modal.remove();
        loadItems(state.currentFolderId);
      });
    });
  }

  // ---------------- Bind search inputs ----------------
  $('#ft-search-input').addEventListener('input', ()=>{
    const q = $('#ft-search-input').value.trim();
    if(q.length===0 && !$('#ft-important-only').checked && !$('#ft-filter-tag').value){
      state.searching = false;
      loadItems(state.currentFolderId);
    } else {
      doSearch();
    }
  });
  $('#ft-filter-tag').addEventListener('change', doSearch);
  $('#ft-important-only').addEventListener('change', doSearch);

  // ---------------- Init ----------------
  (async function init(){
    await loadTree();
    await loadItems(state.currentFolderId);
  })();
})();


// === OPEN PREVIEW on click Name cell (PDF/Word/Excel) ===
(function(){
  const table = document.querySelector('#ft-table');
  if (!table) return;
  const EXTS = new Set(['pdf','doc','docx','xls','xlsx']);
  table.addEventListener('click', function(e){
    const cell = e.target.closest('td');
    const row  = e.target.closest('tr.ft-row[data-type="file"]');
    if (!row || !cell) return;
    if (!cell.classList.contains('ft-name')) return; // only Name column
    if (e.target.closest('input[type="checkbox"], .ft-actions, button, a[data-skip-preview], a.ft-open')) return;
    const id  = row.dataset.id;
    let ext = (row.dataset.ext || '').toLowerCase();
    if (!ext) {
      const t = (cell.textContent || '').trim();
      const m = t.match(/\.([A-Za-z0-9]+)$/);
      ext = m ? m[1].toLowerCase() : '';
    }
    if (id && EXTS.has(ext)) {
      const url = `pages/partials/file_preview.php?id=${id}`;
      window.open(url, '_blank', 'noopener');
    }
  });
})();
