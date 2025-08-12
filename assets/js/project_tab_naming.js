/* Naming Rule Tab JS
 * - Endpoint via data-endpoint (relative to /pages/)
 * - Extension is parsed from the single "Tên file" input (e.g. TruCauT2.rvt)
 * - All messages in English
 */

(function () {
  const root = document.getElementById('tab-naming-root');
  if (!root) return;

  const projectId = parseInt(root.dataset.projectId || '0', 10);
  const isManager = root.dataset.isManager === '1';
  const endpoint  = root.dataset.endpoint || 'partials/project_tab_naming.php';

  const $ = (sel) => document.querySelector(sel);
  const nfId        = $('#nf_id');
  const nfProject   = $('#nf_project_name');
  const nfOrigin    = $('#nf_originator');
  const nfSystem    = $('#nf_system');
  const nfLevel     = $('#nf_level');
  const nfType      = $('#nf_type');
  const nfRole      = $('#nf_role');
  const nfNumber    = $('#nf_number');
  const nfTitle     = $('#nf_title');

  const preview     = $('#namingPreview');
  const btnSave     = $('#btnSaveNaming');
  const btnSaveText = $('#btnSaveText');
  const btnCancel   = $('#btnCancelEdit');
  const tableBody   = document.querySelector('#namingTable tbody');

  const pad4 = (n) => {
    n = ('' + (n ?? '')).replace(/\D/g, '');
    if (!n) n = '1';
    let x = parseInt(n, 10);
    if (!Number.isFinite(x) || x < 1) x = 1;
    const s = '' + x;
    return s.length >= 4 ? s : '0000'.slice(s.length) + s;
  };

  const sanitizeTitle = (s) => {
    if (!s) return '';
    // drop diacritics
    try { s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, ''); } catch(_){}
    s = s.replace(/đ/g,'d').replace(/Đ/g,'D');
    s = s.replace(/\s+/g, '');
    s = s.replace(/[^A-Za-z0-9_\-]/g, '');
    return s;
  };

  const sanitizeExt = (e) => {
    if (!e) return '';
    e = e.trim().toLowerCase();
    return /^[a-z0-9]{1,10}$/.test(e) ? e : '';
  };

  const up = (s) => (s || '').toUpperCase();

  const parseFileInput = (raw) => {
    raw = (raw || '').trim();
    const idx = raw.lastIndexOf('.');
    if (idx > 0 && idx < raw.length - 1) {
      const title = sanitizeTitle(raw.slice(0, idx));
      const ext   = sanitizeExt(raw.slice(idx + 1));
      if (title && ext) return { title, ext };
      if (title) return { title, ext: '' };
      return { title: '', ext: '' };
    }
    return { title: sanitizeTitle(raw), ext: '' };
  };

  const compose = () => {
    const pName = up(nfProject?.value?.trim());
    const org   = up(nfOrigin?.value?.trim());
    const sys   = up(nfSystem?.value);
    const lvl   = up(nfLevel?.value);
    const typ   = up(nfType?.value);
    const rol   = up(nfRole?.value);
    const num   = pad4(nfNumber?.value);

    const { title, ext } = parseFileInput(nfTitle?.value);
    const base = [pName, org, sys, lvl, typ, rol, num, title].join('-');
    const joined = ext ? `${base}.${ext}` : base;

    if (preview) preview.textContent = joined;
    if (btnSaveText) btnSaveText.textContent = joined;
    return { joined, title, ext };
  };

  [nfProject, nfOrigin, nfSystem, nfLevel, nfType, nfRole, nfNumber, nfTitle].forEach(el => {
    if (!el) return;
    el.addEventListener('input', compose);
    el.addEventListener('change', compose);
  });
  compose();

  const fetchJSON = async (url, opts) => {
    const res = await fetch(url, opts);
    let data = {};
    try { data = await res.json(); } catch (_) {}
    if (!res.ok || data.ok === false) {
      const msg = (data && data.error) ? data.error : `Request failed (${res.status})`;
      throw new Error(msg);
    }
    return data;
  };

  const reloadList = async () => {
    tableBody.innerHTML = `<tr><td colspan="6">Loading...</td></tr>`;
    try {
      const data = await fetchJSON(`${endpoint}?action=list&project_id=${projectId}`);
      const rows = data.data || [];
      const isMgr = !!data.is_manager;
      if (!rows.length) {
        tableBody.innerHTML = `<tr><td colspan="6">No records</td></tr>`;
        return;
      }
      tableBody.innerHTML = rows.map(r => {
        const file = r.computed_filename || '';
        const cls  = isMgr ? 'link-filename' : 'link-filename disabled';
        const num  = ('' + (r.number_seq ?? 1)).padStart(4, '0');
        const time = r.updated_at ? new Date((r.updated_at || '').replace(' ', 'T')).toLocaleString() : '';
        return `
          <tr data-id="${r.id}">
            <td><a href="javascript:void(0)" class="${cls}" data-id="${r.id}">${file}</a></td>
            <td>${r.originator || ''}</td>
            <td>${r.type_code || ''}</td>
            <td>${r.role_code || ''}</td>
            <td>${num}</td>
            <td>${time}</td>
          </tr>
        `;
      }).join('');
    } catch (err) {
      tableBody.innerHTML = `<tr><td colspan="6">Load failed: ${err.message}</td></tr>`;
    }
  };

  const setEditMode = (row) => {
    nfId.value      = row.id;
    nfProject.value = row.project_name || '';
    nfOrigin.value  = row.originator || '';
    nfSystem.value  = row.system_code || 'ZZ';
    nfLevel.value   = row.level_code  || 'ZZ';
    nfType.value    = row.type_code   || 'M3';
    nfRole.value    = row.role_code   || 'Z';
    nfNumber.value  = ('' + (row.number_seq || 1)).padStart(4, '0');

    // Merge title + extension back into one field for editing
    const ext = (row.extension || '').toLowerCase();
    nfTitle.value   = row.file_title + (ext ? ('.' + ext) : '');

    compose();
    if (btnSave) btnSave.firstChild.nodeValue = 'Update: ';
    if (btnCancel) btnCancel.style.display = '';
  };

  const clearForm = () => {
    nfId.value = '';
    nfProject.value = '';
    nfOrigin.value = '';
    nfSystem.value = 'ZZ';
    nfLevel.value = 'ZZ';
    nfType.value = 'M3';
    nfRole.value = 'S';
    nfNumber.value = '0001';
    nfTitle.value = '';
    compose();
    if (btnSave) btnSave.firstChild.nodeValue = 'Save: ';
    if (btnCancel) btnCancel.style.display = 'none';
  };

  root.addEventListener('click', async (e) => {
    const a = e.target.closest('.link-filename');
    if (!a) return;
    if (!isManager || a.classList.contains('disabled')) return;
    const id = parseInt(a.dataset.id || '0', 10);
    if (!id) return;
    try {
      const data = await fetchJSON(`${endpoint}?action=get&project_id=${projectId}&id=${id}`);
      if (data && data.data) setEditMode(data.data);
    } catch (err) {
      alert('Failed to load the record: ' + err.message);
    }
  });

  btnSave && btnSave.addEventListener('click', async () => {
    if (!isManager) return;
    const { joined, title, ext } = compose();

    const body = new FormData();
    const updating = !!nfId.value;
    body.append('action', updating ? 'update' : 'create');
    body.append('project_id', String(projectId));
    if (updating) body.append('id', nfId.value);

    body.append('project_name', (nfProject.value || '').trim());
    body.append('originator', (nfOrigin.value || '').trim());
    body.append('system_code', nfSystem.value);
    body.append('level_code', nfLevel.value);
    body.append('type_code', nfType.value);
    body.append('role_code', nfRole.value);
    body.append('number_seq', (nfNumber.value || '').replace(/\D/g, '') || '1');
    body.append('file_title', title);      // sanitized base name
    body.append('extension', ext);         // sanitized ext (can be '')

    btnSave.disabled = true;
    btnSave.classList.add('is-busy');
    const oldText = btnSave.textContent;
    btnSave.textContent = updating ? 'Updating...' : 'Saving...';

    try {
      await fetchJSON(endpoint, { method: 'POST', body });
      clearForm();
      await reloadList();
      btnSave.textContent = 'Save: ' + (preview?.textContent || joined);
    } catch (err) {
      alert(err.message || 'Save failed.');
      btnSave.textContent = oldText;
    } finally {
      btnSave.disabled = false;
      btnSave.classList.remove('is-busy');
    }
  });

  btnCancel && btnCancel.addEventListener('click', () => {
    clearForm();
  });

  reloadList();
})();
