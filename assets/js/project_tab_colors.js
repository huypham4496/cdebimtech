// assets/js/project_tab_colors.js
// Purpose: Frontend logic for the Colors tab (list/add/edit/delete + color picker).
// Notes:
//  - All user-facing messages are in English per request.

(function () {
  const root = document.getElementById('project-colors');
  if (!root) return;

  const projectId = parseInt(root.dataset.projectId || '0', 10);
  const canManage = root.dataset.canManage === '1';
  const ENDPOINT = root.dataset.endpoint || 'partials/project_tab_colors.php';

  // ---------------- API helper ----------------
  async function api(action, data = {}) {
    const form = new URLSearchParams();
    form.set('action', action);
    if (projectId) form.set('project_id', String(projectId));
    Object.keys(data).forEach(k => form.set(k, data[k]));

    let res, text;
    try {
      res = await fetch(ENDPOINT, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: form.toString(),
        credentials: 'same-origin'
      });
      text = await res.text();
    } catch (err) {
      console.error('Network error:', err);
      throw new Error('Unable to reach server.');
    }

    try {
      return JSON.parse(text);
    } catch (e) {
      console.error('Expected JSON, got instead:\n', text);
      throw new Error('Server returned invalid data.');
    }
  }

  // ---------------- UI helpers ----------------
  const listEl = document.getElementById('color-groups-list');

  function hexPreviewSpan(hex) {
    const span = document.createElement('span');
    span.className = 'hex-preview';
    span.style.backgroundColor = hex || '#FFFFFF';
    span.title = hex || '';
    return span;
  }

  function iconButton(cls, title = '') {
    const btn = document.createElement('button');
    btn.className = `icon-btn ${cls}`;
    btn.type = 'button';
    if (title) btn.title = title;
    return btn;
  }

  function validateHex(hex) {
    return /^#([0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})$/.test(hex);
  }

  function clamp(v, min, max) { v = Number(v); return Number.isFinite(v) ? Math.min(max, Math.max(min, v)) : min; }
  function toHex2(n) { const s = clamp(n,0,255).toString(16).toUpperCase(); return s.length === 1 ? '0'+s : s; }
  function rgbToHex(r,g,b) { return '#' + toHex2(r) + toHex2(g) + toHex2(b); }
  function hexToRgb(hex) {
    if (!validateHex(hex)) return {r:255,g:255,b:255};
    let h = hex.replace('#','').toUpperCase();
    if (h.length === 3) h = h.split('').map(c=>c+c).join('');
    return { r: parseInt(h.slice(0,2),16), g: parseInt(h.slice(2,4),16), b: parseInt(h.slice(4,6),16) };
  }
  function normalizeHex(hex) {
    if (!validateHex(hex)) return null;
    let h = hex.toUpperCase();
    if (/^#([0-9A-F]{3})$/.test(h)) {
      const c = h.slice(1);
      h = '#' + c[0] + c[0] + c[1] + c[1] + c[2] + c[2];
    }
    return h;
  }

  // ---------- Color popover ----------
  let activePicker = null;

  function closePicker() {
    if (!activePicker) return;
    document.removeEventListener('mousedown', onDocDown, true);
    document.removeEventListener('keydown', onKeyDown, true);
    activePicker.wrap.remove();
    activePicker = null;
  }
  function onDocDown(e) {
    if (!activePicker) return;
    const w = activePicker.wrap;
    if (!w.contains(e.target)) closePicker();
  }
  function onKeyDown(e) { if (e.key === 'Escape') closePicker(); }

  function positionPopover(wrap, anchorRect) {
    const margin = 12;
    const gap = 6;
    const vw = window.innerWidth;
    const vh = window.innerHeight;

    const pop = wrap.getBoundingClientRect();

    let left = Math.round(anchorRect.left);
    if (left < margin) left = margin;
    if (left + pop.width + margin > vw) left = Math.max(margin, Math.round(vw - pop.width - margin));

    let top = Math.round(anchorRect.bottom + gap);
    let isAbove = false;
    if (top + pop.height + margin > vh) {
      const above = Math.round(anchorRect.top - pop.height - gap);
      if (above >= margin) {
        top = above; isAbove = true;
      } else {
        top = margin; isAbove = true;
      }
    }
    if (top < margin) top = margin;
    if (top + pop.height + margin > vh) top = Math.max(margin, Math.round(vh - pop.height - margin));

    wrap.style.left = left + 'px';
    wrap.style.top = top + 'px';
    wrap.classList.toggle('is-above', isAbove);
  }

  function openPickerFor(tr, preview) {
    closePicker();
    const rect = preview.getBoundingClientRect();

    const wrap = document.createElement('div');
    wrap.className = 'cde-popover';

    const row1 = document.createElement('div');
    row1.className = 'picker-row';
    const inputColor = document.createElement('input');
    inputColor.type = 'color';
    row1.appendChild(inputColor);

    const row2 = document.createElement('div');
    row2.className = 'picker-row';
    const hexLabel = document.createElement('label'); hexLabel.textContent = 'HEX';
    const inputHex = document.createElement('input');
    inputHex.type = 'text'; inputHex.placeholder = '#RRGGBB'; inputHex.maxLength = 7;
    inputHex.classList.add('hex-field');                // << sizing class
    row2.appendChild(hexLabel); row2.appendChild(inputHex);

    const row3 = document.createElement('div');
    row3.className = 'picker-row';
    const rLabel = document.createElement('label'); rLabel.textContent = 'R';
    const gLabel = document.createElement('label'); gLabel.textContent = 'G';
    const bLabel = document.createElement('label'); bLabel.textContent = 'B';
    const rIn = document.createElement('input'); rIn.type='number'; rIn.min='0'; rIn.max='255'; rIn.classList.add('rgb-field');  // << sizing class
    const gIn = document.createElement('input'); gIn.type='number'; gIn.min='0'; gIn.max='255'; gIn.classList.add('rgb-field');
    const bIn = document.createElement('input'); bIn.type='number'; bIn.min='0'; bIn.max='255'; bIn.classList.add('rgb-field');
    row3.appendChild(rLabel); row3.appendChild(rIn);
    row3.appendChild(gLabel); row3.appendChild(gIn);
    row3.appendChild(bLabel); row3.appendChild(bIn);

    const actions = document.createElement('div');
    actions.className = 'picker-actions';
    const btnApply = document.createElement('button'); btnApply.className='btn primary'; btnApply.textContent='Apply';
    const btnClose = document.createElement('button'); btnClose.className='btn'; btnClose.textContent='Close';
    actions.appendChild(btnApply); actions.appendChild(btnClose);

    wrap.appendChild(row1);
    wrap.appendChild(row2);
    wrap.appendChild(row3);
    wrap.appendChild(actions);
    document.body.appendChild(wrap);

    const hexInputInRow = tr.querySelector('.input-hex');
    let currentHex = (hexInputInRow?.value || preview.style.backgroundColor || '#FFFFFF').toString();
    if (!validateHex(currentHex)) currentHex = '#FFFFFF';
    currentHex = normalizeHex(currentHex) || '#FFFFFF';
    const {r,g,b} = hexToRgb(currentHex);

    inputColor.value = currentHex;
    inputHex.value = currentHex;
    rIn.value = String(r);
    gIn.value = String(g);
    bIn.value = String(b);

    // Clamp / flip within viewport
    requestAnimationFrame(() => positionPopover(wrap, rect));

    function syncFromHex(hex) {
      const norm = normalizeHex(hex);
      if (!norm) return;
      inputHex.value = norm;
      inputColor.value = norm;
      const rgb = hexToRgb(norm);
      rIn.value = String(rgb.r); gIn.value = String(rgb.g); bIn.value = String(rgb.b);
    }
    function syncFromRGB() {
      const rr = clamp(rIn.value, 0, 255);
      const gg = clamp(gIn.value, 0, 255);
      const bb = clamp(bIn.value, 0, 255);
      const hex = rgbToHex(rr, gg, bb);
      inputHex.value = hex;
      inputColor.value = hex;
    }

    inputColor.addEventListener('input', () => { syncFromHex(inputColor.value); });
    inputHex.addEventListener('input', () => { if (validateHex(inputHex.value)) syncFromHex(inputHex.value); });
    [rIn,gIn,bIn].forEach(el => {
      el.addEventListener('input', () => { syncFromRGB(); });
      el.addEventListener('change', () => { el.value = String(clamp(el.value, 0, 255)); syncFromRGB(); });
    });

    btnApply.addEventListener('click', () => {
      const hex = normalizeHex(inputHex.value);
      if (!hex) { alert('Invalid HEX code.'); return; }
      if (hexInputInRow) hexInputInRow.value = hex;
      preview.style.backgroundColor = hex;
      saveRow(tr).catch(err => alert(err.message || 'Cannot save data.'));
      closePicker();
    });
    btnClose.addEventListener('click', () => closePicker());

    setTimeout(() => {
      document.addEventListener('mousedown', onDocDown, true);
      document.addEventListener('keydown', onKeyDown, true);
    }, 0);

    activePicker = { wrap, color: inputColor, hex: inputHex, r: rIn, g: gIn, b: bIn, tr, preview };
  }

  function renderGroups(data) {
    listEl.innerHTML = '';
    const { groups, itemsByGroup } = data;

    if (!groups || groups.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'empty';
      empty.textContent = 'No color groups yet.';
      listEl.appendChild(empty);
      return;
    }

    groups.forEach(g => {
      const box = document.createElement('div');
      box.className = 'group-box';
      box.dataset.groupId = g.id;

      const header = document.createElement('div');
      header.className = 'group-header';

      const name = document.createElement('div');
      name.className = 'group-name';
      name.textContent = g.name;
      header.appendChild(name);

      if (canManage) {
        const right = document.createElement('div');
        right.className = 'group-actions';

        const add = iconButton('add', 'Add color');
        add.textContent = '+';
        add.addEventListener('click', () => addNewRow(box, g.id));

        const del = iconButton('delete', 'Delete group');
        del.textContent = '🗑';
        del.addEventListener('click', async () => {
          if (!confirm('Delete this group and all colors inside?')) return;
          try {
            const r = await api('delete_group', { group_id: g.id });
            if (r.ok) {
              box.remove();
            } else {
              alert(r.msg || 'Cannot delete group.');
            }
          } catch (err) {
            alert(err.message || 'Cannot delete group.');
          }
        });

        right.appendChild(add);
        right.appendChild(del);
        header.appendChild(right);
      }

      box.appendChild(header);

      const body = document.createElement('div');
      body.className = 'group-body';

      const table = document.createElement('table');
      table.className = 'colors-table';

      const thead = document.createElement('thead');
      thead.innerHTML = `
        <tr>
          <th>#</th>
          <th>Label</th>
          <th style="width:180px">HEX code</th>
          <th style="width:72px">Preview</th>
          ${canManage ? '<th style="width:96px">Actions</th>' : ''}
        </tr>
      `;
      table.appendChild(thead);

      const tbody = document.createElement('tbody');
      const items = itemsByGroup[g.id] || [];
      items.forEach((it, idx) => {
        tbody.appendChild(renderRow(it, idx + 1));
      });
      table.appendChild(tbody);

      body.appendChild(table);
      box.appendChild(body);
      listEl.appendChild(box);
    });
  }

  function renderRow(item, no) {
    const tr = document.createElement('tr');
    tr.dataset.itemId = item.id;

    const tdNo = document.createElement('td');
    tdNo.className = 'cell-no';
    tdNo.textContent = String(no ?? '');
    tr.appendChild(tdNo);

    const tdLabel = document.createElement('td');
    if (canManage) {
      const inp = document.createElement('input');
      inp.type = 'text';
      inp.className = 'input input-label';
      inp.value = item.label || '';
      inp.maxLength = 255;
      inp.addEventListener('change', () => saveRow(tr));
      tdLabel.appendChild(inp);
    } else {
      tdLabel.textContent = item.label || '';
    }
    tr.appendChild(tdLabel);

    const tdHex = document.createElement('td');
    if (canManage) {
      const inp = document.createElement('input');
      inp.type = 'text';
      inp.className = 'input input-hex';
      inp.placeholder = '#RRGGBB';
      inp.value = item.hex_color || '';
      inp.maxLength = 7;
      inp.addEventListener('input', () => {
        const pv = tr.querySelector('.hex-preview');
        if (validateHex(inp.value)) pv.style.backgroundColor = normalizeHex(inp.value);
      });
      inp.addEventListener('change', () => {
        const norm = normalizeHex(inp.value);
        if (!norm) { alert('Invalid HEX code. Example: #1A2B3C'); return; }
        inp.value = norm;
        saveRow(tr);
      });
      tdHex.appendChild(inp);
    } else {
      const code = document.createElement('code');
      code.textContent = item.hex_color || '';
      tdHex.appendChild(code);
    }
    tr.appendChild(tdHex);

    const tdPreview = document.createElement('td');
    const pv = hexPreviewSpan(item.hex_color || '#FFFFFF');
    if (canManage) {
      pv.addEventListener('click', () => openPickerFor(tr, pv));
    }
    tdPreview.appendChild(pv);
    tr.appendChild(tdPreview);

    if (canManage) {
      const tdAct = document.createElement('td');
      const del = iconButton('delete-row', 'Delete');
      del.textContent = '✕';
      del.addEventListener('click', async () => {
        if (!confirm('Delete this color?')) return;
        const tbody = tr.parentElement;
        const currId = parseInt(tr.dataset.itemId, 10) || 0;
        if (currId <= 0) {
          tr.remove();
          renumberRows(tbody);
          return;
        }
        try {
          const r = await api('delete_item', { id: currId });
          if (r.ok) {
            tr.remove();
            renumberRows(tbody);
          } else {
            alert(r.msg || 'Cannot delete.');
          }
        } catch (err) {
          alert(err.message || 'Cannot delete.');
        }
      });
      tdAct.appendChild(del);
      tr.appendChild(tdAct);
    }

    return tr;
  }

  function renumberRows(tbody) {
    if (!tbody) return;
    const rows = tbody.querySelectorAll('tr');
    rows.forEach((row, i) => {
      const cell = row.querySelector('.cell-no');
      if (cell) cell.textContent = String(i + 1);
    });
  }

  function addNewRow(groupBox, groupId) {
    const tbody = groupBox.querySelector('tbody');
    const tmp = { id: 0, group_id: groupId, label: '', hex_color: '#FFFFFF', sort_order: (tbody.children.length || 0) + 1 };
    const tr = renderRow(tmp, tbody.children.length + 1);
    tbody.appendChild(tr);
    const labelInput = tr.querySelector('.input-label');
    if (labelInput) labelInput.focus();
  }

  async function saveRow(tr) {
    const id = parseInt(tr.dataset.itemId, 10) || 0;
    const label = tr.querySelector('.input-label')?.value?.trim() || '';
    const hexRaw = (tr.querySelector('.input-hex')?.value || '').toUpperCase();
    const hex = normalizeHex(hexRaw);

    if (!label) {
      alert('Label is required.');
      throw new Error('Label is required.');
    }
    if (!hex) {
      alert('Invalid HEX code. Example: #1A2B3C');
      throw new Error('Invalid HEX code.');
    }

    if (id > 0) {
      const r = await api('update_item', { id, label, hex_color: hex });
      if (!r.ok) throw new Error(r.msg || 'Cannot save changes.');
    } else {
      const groupId = parseInt(tr.closest('.group-box').dataset.groupId, 10);
      const sortOrder = parseInt(tr.querySelector('.cell-no').textContent, 10) || 0;
      const r = await api('add_item', { group_id: groupId, label, hex_color: hex, sort_order: sortOrder });
      if (r.ok && r.item) {
        tr.dataset.itemId = r.item.id;
      } else {
        throw new Error(r.msg || 'Cannot add color.');
      }
    }

    const pv = tr.querySelector('.hex-preview');
    if (pv) pv.style.backgroundColor = hex;
  }

  // ---------------- Events (Create group) ----------------
  if (canManage) {
    const btnSaveGroup = document.getElementById('btn-save-group');
    const inpName = document.getElementById('color-group-name');

    const onCreate = async () => {
      const name = (inpName.value || '').trim();
      if (!name) {
        alert('Name is required.');
        return;
      }
      btnSaveGroup.disabled = true;
      try {
        const r = await api('add_group', { name });
        if (r.ok) {
          inpName.value = '';
          await loadAll();
        } else {
          alert(r.msg || 'Cannot create group.');
        }
      } catch (err) {
        alert(err.message || 'Cannot create group.');
      } finally {
        btnSaveGroup.disabled = false;
      }
    };

    btnSaveGroup?.addEventListener('click', onCreate);
    inpName?.addEventListener('keydown', (e) => { if (e.key === 'Enter') onCreate(); });
  }

  // ---------------- Initial load ----------------
  async function loadAll() {
    try {
      const r = await api('list');
      if (r.ok) renderGroups(r.data);
      else listEl.innerHTML = `<div class="error">${r.msg || 'Failed to load data.'}</div>`;
    } catch (err) {
      listEl.innerHTML = `<div class="error">${err.message || 'Failed to load data.'}</div>`;
    }
  }

  loadAll();
})();
