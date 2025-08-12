/* assets/js/project_tab_naming.js
 * - POST-only actions (list/get/create/update/delete)
 * - Filename rule mirrored with server: codes UPPER, number 4-digits, filename TitleCase ASCII (no spaces)
 * - VN diacritics -> ASCII with explicit mapping (no character loss)
 * - Table includes extra columns + Edit/Delete (for managers)
 * - Button label always "Save"
 */

(function () {
  const root = document.getElementById('tab-naming-root');
  if (!root) return;

  const projectId = parseInt(root.dataset.projectId || '0', 10);
  const isManager = root.dataset.isManager === '1';
  const endpoint  = root.dataset.endpoint || 'partials/project_tab_naming.php';

  // ===== Helpers =====
  const up = (s) => (s || '').toUpperCase();

  // Explicit VN → ASCII mapping (avoid environment-dependent normalize/iconv)
  const vnToAscii = (s) => {
    if (!s) return '';
    const rules = [
      [/đ/g, 'd'], [/Đ/g, 'D'],
      (/[áàảãạăắằẳẵặâấầẩẫậ]/g), 'a',
      (/[ÁÀẢÃẠĂẮẰẲẴẶÂẤẦẨẪẬ]/g), 'A',
      (/[éèẻẽẹêếềểễệ]/g), 'e',
      (/[ÉÈẺẼẸÊẾỀỂỄỆ]/g), 'E',
      (/[íìỉĩị]/g), 'i',
      (/[ÍÌỈĨỊ]/g), 'I',
      (/[óòỏõọôốồổỗộơớờởỡợ]/g), 'o',
      (/[ÓÒỎÕỌÔỐỒỔỖỘƠỚỜỞỠỢ]/g), 'O',
      (/[úùủũụưứừửữự]/g), 'u',
      (/[ÚÙỦŨỤƯỨỪỬỮỰ]/g), 'U',
      (/[ýỳỷỹỵ]/g), 'y',
      (/[ÝỲỶỸỴ]/g), 'Y',
    ];
    // apply
    for (let i = 0; i < rules.length; i += 2) {
      s = s.replace(rules[i], rules[i + 1]);
    }
    return s;
  };

  const pad4 = (n) => {
    n = ('' + (n ?? '')).replace(/\D/g, '');
    if (!n) n = '1';
    let x = parseInt(n, 10);
    if (!Number.isFinite(x) || x < 1) x = 1;
    const s = '' + x;
    return s.length >= 4 ? s : '0000'.slice(s.length) + s;
  };

  const sanitizeExt = (e) => {
    if (!e) return '';
    e = e.trim().toLowerCase();
    return /^[a-z0-9]{1,10}$/.test(e) ? e : '';
  };

  const toTitleChunksJoin = (s) => {
    s = vnToAscii(s);
    const chunks = s.split(/[^A-Za-z0-9]+/).filter(Boolean);
    const fixed  = chunks.map(ch => ch.charAt(0).toUpperCase() + ch.slice(1).toLowerCase());
    const joined = fixed.join('');
    return joined.replace(/[^A-Za-z0-9_\-]/g, '');
  };

  const parseFileInput = (raw) => {
    raw = (raw || '').trim();
    const idx = raw.lastIndexOf('.');
    if (idx > 0 && idx < raw.length - 1) {
      const title = toTitleChunksJoin(raw.slice(0, idx));
      const ext   = sanitizeExt(raw.slice(idx + 1));
      if (title && ext) return { title, ext };
      if (title) return { title, ext: '' };
      return { title: '', ext: '' };
    }
    return { title: toTitleChunksJoin(raw), ext: '' };
  };

  // ===== DOM =====
  const $ = (sel) => document.querySelector(sel);
  const nfId      = $('#nf_id');
  const nfProject = $('#nf_project_name');
  const nfOrigin  = $('#nf_originator');
  const nfSystem  = $('#nf_system');
  const nfLevel   = $('#nf_level');
  const nfType    = $('#nf_type');
  const nfRole    = $('#nf_role');
  const nfNumber  = $('#nf_number');
  const nfTitle   = $('#nf_title');

  const preview   = $('#namingPreview');
  const btnSave   = $('#btnSaveNaming');
  const btnCancel = $('#btnCancelEdit');
  const tableBody = document.querySelector('#namingTable tbody');

  // ===== Compose -> Preview (exactly same rule as PHP) =====
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

    return { joined, title, ext };
  };
  [nfProject, nfOrigin, nfSystem, nfLevel, nfType, nfRole, nfNumber, nfTitle].forEach(el => {
    if (!el) return;
    el.addEventListener('input', compose);
    el.addEventListener('change', compose);
  });
  compose();

  // ===== AJAX helper =====
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

  // ===== Reload table =====
  const reloadList = async () => {
    if (!tableBody) return;
    tableBody.innerHTML = `<tr><td colspan="10">Loading...</td></tr>`;
    try {
      const body = new FormData();
      body.append('action', 'list');
      body.append('project_id', String(projectId));
      const data = await fetchJSON(endpoint, { method: 'POST', body });
      const rows = data.data || [];
      const isMgr = !!data.is_manager;
      if (!rows.length) {
        tableBody.innerHTML = `<tr><td colspan="10">No records</td></tr>`;
        return;
      }
      tableBody.innerHTML = rows.map(r => {
        const file = r.computed_filename || '';
        const num  = ('' + (r.number_seq ?? 1)).padStart(4, '0');
        const time = r.updated_at ? new Date((r.updated_at || '').replace(' ', 'T')).toLocaleString() : '';
        const actions = isMgr
          ? `<button class="btn-ghost btn-sm btn-edit" data-id="${r.id}">Edit</button>
             <button class="btn-ghost btn-sm btn-del"  data-id="${r.id}">Delete</button>`
          : '';
        const fileLink = isMgr
          ? `<a href="javascript:void(0)" class="link-filename btn-edit" data-id="${r.id}">${file}</a>`
          : `<span class="link-filename disabled">${file}</span>`;
        return `
          <tr data-id="${r.id}">
            <td>${fileLink}</td>
            <td>${r.project_name || ''}</td>
            <td>${r.originator || ''}</td>
            <td>${r.system_code || ''}</td>
            <td>${r.level_code || ''}</td>
            <td>${r.type_code || ''}</td>
            <td>${r.role_code || ''}</td>
            <td>${num}</td>
            <td>${time}</td>
            ${isMgr ? `<td>${actions}</td>` : ``}
          </tr>
        `;
      }).join('');
    } catch (err) {
      tableBody.innerHTML = `<tr><td colspan="10">Load failed: ${err.message}</td></tr>`;
    }
  };

  // ===== Edit / Delete =====
  const setEditMode = (row) => {
    nfId.value      = row.id;
    nfProject.value = row.project_name || '';
    nfOrigin.value  = row.originator || '';
    nfSystem.value  = row.system_code || 'ZZ';
    nfLevel.value   = row.level_code  || 'ZZ';
    nfType.value    = row.type_code   || 'M3';
    nfRole.value    = row.role_code   || 'Z';
    nfNumber.value  = ('' + (row.number_seq || 1)).padStart(4, '0');

    const ext = (row.extension || '').toLowerCase();
    nfTitle.value   = row.file_title + (ext ? ('.' + ext) : '');

    compose();
    if (btnSave) btnSave.textContent = 'Save';
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
    if (btnSave) btnSave.textContent = 'Save';
    if (btnCancel) btnCancel.style.display = 'none';
  };

  root.addEventListener('click', async (e) => {
    const editBtn = e.target.closest('.btn-edit');
    const delBtn  = e.target.closest('.btn-del');

    if (editBtn) {
      if (!isManager) return;
      const id = parseInt(editBtn.dataset.id || '0', 10);
      if (!id) return;
      try {
        const body = new FormData();
        body.append('action', 'get');
        body.append('project_id', String(projectId));
        body.append('id', String(id));
        const data = await fetchJSON(endpoint, { method: 'POST', body });
        if (data && data.data) setEditMode(data.data);
      } catch (err) {
        alert('Failed to load the record: ' + err.message);
      }
      return;
    }

    if (delBtn) {
      if (!isManager) return;
      const id = parseInt(delBtn.dataset.id || '0', 10);
      if (!id) return;
      if (!confirm('Delete this record?')) return;
      try {
        const body = new FormData();
        body.append('action', 'delete');
        body.append('project_id', String(projectId));
        body.append('id', String(id));
        await fetchJSON(endpoint, { method: 'POST', body });
        await reloadList();
      } catch (err) {
        alert('Delete failed: ' + err.message);
      }
      return;
    }
  });

  // ===== Save =====
  btnSave && btnSave.addEventListener('click', async () => {
    if (!isManager) return;
    const { title, ext } = compose();

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

    // Gửi cả giá trị đã tách và input thô để server xử lý giống Preview
    body.append('file_title', title);
    body.append('extension', ext);
    body.append('file_title_raw', nfTitle.value || '');

    btnSave.disabled = true;
    btnSave.classList.add('is-busy');

    try {
      await fetchJSON(endpoint, { method: 'POST', body });
      clearForm();
      await reloadList();
    } catch (err) {
      alert(err.message || 'Save failed.');
    } finally {
      btnSave.disabled = false;
      btnSave.classList.remove('is-busy');
    }
  });

  btnCancel && btnCancel.addEventListener('click', clearForm);

  // Initial
  reloadList();
})();
