// assets/js/project_tab_colors.js
(function () {
  const root = document.getElementById('project-colors');
  if (!root) return;

  const projectId = parseInt(root.dataset.projectId || '0', 10);
  const canManage = root.dataset.canManage === '1';
  const ENDPOINT = root.dataset.endpoint || 'partials/project_tab_colors.php';

  // ---------------- API helper (robust) ----------------
  async function api(action, data = {}) {
    const form = new URLSearchParams();
    form.set('action', action);
    if (projectId) form.set('project_id', String(projectId));
    Object.keys(data).forEach(k => form.set(k, data[k]));

    let res, text;
    try {
      res = await fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                   'X-Requested-With': 'XMLHttpRequest' },
        body: form.toString(),
        credentials: 'same-origin' // 👈 rất quan trọng để gửi cookie phiên
      });
      text = await res.text();
    } catch (err) {
      console.error('Network error:', err);
      throw new Error('Không kết nối được máy chủ.');
    }

    try {
      return JSON.parse(text);
    } catch (e) {
      console.error('Expected JSON, got instead:\n', text);
      throw new Error('Máy chủ trả về dữ liệu không hợp lệ.');
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

  function renderGroups(data) {
    listEl.innerHTML = '';
    const { groups, itemsByGroup } = data;

    if (!groups || groups.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'empty';
      empty.textContent = 'Chưa có nhóm màu sắc nào.';
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

        const add = iconButton('add', 'Thêm màu');
        add.innerHTML = '+';
        add.addEventListener('click', () => addNewRow(box, g.id));

        const del = iconButton('delete', 'Xóa nhóm');
        del.innerHTML = '🗑';
        del.addEventListener('click', async () => {
          if (!confirm('Xóa nhóm này và toàn bộ màu bên trong?')) return;
          try {
            const r = await api('delete_group', { group_id: g.id });
            if (r.ok) {
              box.remove();
            } else {
              alert(r.msg || 'Không thể xóa nhóm.');
            }
          } catch (err) {
            alert(err.message || 'Không thể xóa nhóm.');
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
          <th style="width:44px">#</th>
          <th>Label</th>
          <th style="width:220px">Mã màu (HEX)</th>
          <th style="width:64px">Preview</th>
          ${canManage ? '<th style="width:90px"></th>' : ''}
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
        if (validateHex(inp.value)) pv.style.backgroundColor = inp.value;
      });
      inp.addEventListener('change', () => saveRow(tr));
      tdHex.appendChild(inp);
    } else {
      const code = document.createElement('code');
      code.textContent = item.hex_color || '';
      tdHex.appendChild(code);
    }
    tr.appendChild(tdHex);

    const tdPreview = document.createElement('td');
    tdPreview.appendChild(hexPreviewSpan(item.hex_color || '#FFFFFF'));
    tr.appendChild(tdPreview);

    if (canManage) {
      const tdAct = document.createElement('td');
      const del = iconButton('delete-row', 'Xóa');
      del.innerHTML = '✕';
      del.addEventListener('click', async () => {
        if (!confirm('Xóa màu này?')) return;
        try {
          const r = await api('delete_item', { id: item.id });
          if (r.ok) {
            tr.remove();
            renumberRows(tr.parentElement);
          } else {
            alert(r.msg || 'Không thể xóa.');
          }
        } catch (err) {
          alert(err.message || 'Không thể xóa.');
        }
      });
      tdAct.appendChild(del);
      tr.appendChild(tdAct);
    }

    return tr;
  }

  function renumberRows(tbody) {
    [...tbody.querySelectorAll('tr')].forEach((tr, i) => {
      const cell = tr.querySelector('.cell-no');
      if (cell) cell.textContent = String(i + 1);
    });
  }

  function addNewRow(groupBox, groupId) {
    const tbody = groupBox.querySelector('tbody');
    const tmp = {
      id: 0,
      group_id: groupId,
      label: '',
      hex_color: '#FFFFFF',
      sort_order: (tbody.children.length || 0) + 1
    };
    const tr = renderRow(tmp, tbody.children.length + 1);
    tbody.appendChild(tr);
    const labelInput = tr.querySelector('.input-label');
    if (labelInput) labelInput.focus();
  }

  async function saveRow(tr) {
    const id = parseInt(tr.dataset.itemId, 10) || 0;
    const label = tr.querySelector('.input-label')?.value?.trim() || '';
    const hex = (tr.querySelector('.input-hex')?.value || '').toUpperCase();

    if (!label) {
      alert('Label không được để trống.');
      return;
    }
    if (!validateHex(hex)) {
      alert('Mã màu không hợp lệ. Ví dụ: #1A2B3C');
      return;
    }

    try {
      if (id > 0) {
        const r = await api('update_item', { id, label, hex_color: hex });
        if (!r.ok) alert(r.msg || 'Không thể lưu thay đổi.');
      } else {
        const groupId = parseInt(tr.closest('.group-box').dataset.groupId, 10);
        const sortOrder = parseInt(tr.querySelector('.cell-no').textContent, 10) || 0;
        const r = await api('add_item', { group_id: groupId, label, hex_color: hex, sort_order: sortOrder });
        if (r.ok && r.item) {
          tr.dataset.itemId = r.item.id;
        } else {
          alert(r.msg || 'Không thể thêm màu.');
        }
      }

      const pv = tr.querySelector('.hex-preview');
      if (pv && validateHex(hex)) pv.style.backgroundColor = hex;
    } catch (err) {
      alert(err.message || 'Không thể lưu dữ liệu.');
    }
  }

  // ---------------- Events (khu vực 1) ----------------
  if (canManage) {
    const btnSaveGroup = document.getElementById('btn-save-group');
    const inpName = document.getElementById('color-group-name');

    const onCreate = async () => {
      const name = (inpName.value || '').trim();
      if (!name) {
        alert('Vui lòng nhập tên group.');
        return;
      }
      btnSaveGroup.disabled = true;
      try {
        const r = await api('add_group', { name });
        if (r.ok) {
          inpName.value = '';
          await loadAll();
        } else {
          alert(r.msg || 'Không thể tạo group.');
        }
      } catch (err) {
        alert(err.message || 'Không thể tạo group.');
      } finally {
        btnSaveGroup.disabled = false;
      }
    };

    btnSaveGroup?.addEventListener('click', onCreate);
    inpName?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') onCreate();
    });
  }

  // ---------------- Load initial ----------------
  async function loadAll() {
    try {
      const r = await api('list');
      if (r.ok) renderGroups(r.data);
      else listEl.innerHTML = `<div class="error">${r.msg || 'Không thể tải dữ liệu.'}</div>`;
    } catch (err) {
      listEl.innerHTML = `<div class="error">${err.message || 'Không thể tải dữ liệu.'}</div>`;
    }
  }

  loadAll();
})();
