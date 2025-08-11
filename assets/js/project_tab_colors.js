// assets/js/project_tab_colors.js
(function () {
  const root = document.getElementById('project-colors');
  if (!root) return;

  const projectId = parseInt(root.dataset.projectId || '0', 10);
  const elGroupList = document.getElementById('group-list');
  const elItemList  = document.getElementById('item-list');
  const formCreateGroup = document.getElementById('form-create-group');
  const formCreateItem  = document.getElementById('form-create-item');
  const swatch = document.getElementById('swatch');
  const itemsTitle = document.getElementById('items-title');

  let currentGroupId = null;

  const post = async (action, data = {}) => {
    const body = new URLSearchParams({ action, ...data });
    const res = await fetch(location.pathname + location.search, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || !json.ok) {
      const msg = (json && (json.msg || json.error)) || `Lỗi ${res.status}`;
      throw new Error(msg);
    }
    return json;
  };

  const loadGroups = async () => {
    if (!projectId) {
      elGroupList.innerHTML = `<li>Thiếu project_id — truyền bằng query ?project_id=...</li>`;
      return;
    }
    const { groups } = await post('list_groups', { project_id: projectId });
    elGroupList.innerHTML = '';
    if (!groups.length) {
      elGroupList.innerHTML = `<li>Chưa có nhóm nào</li>`;
    }
    groups.forEach(g => {
      const li = document.createElement('li');
      li.innerHTML = `
        <div class="pcg__group">
          <strong>${escapeHtml(g.name)}</strong>
          <small>${g.item_count} danh mục</small>
        </div>
        <div class="pcg__actions">
          <button data-act="open" data-id="${g.id}">Mở</button>
          <button data-act="del"  data-id="${g.id}">Xoá</button>
        </div>
      `;
      elGroupList.appendChild(li);
    });
  };

  const openGroup = async (groupId, groupName = '') => {
    currentGroupId = groupId;
    formCreateItem.removeAttribute('disabled');
    itemsTitle.textContent = `Danh mục màu · ${groupName || ''}`;
    await loadItems(groupId);
  };

  const loadItems = async (groupId) => {
    const { items } = await post('list_items', { group_id: groupId });
    elItemList.innerHTML = '';
    if (!items.length) {
      elItemList.innerHTML = `<li>Nhóm này chưa có danh mục màu</li>`;
    }
    items.forEach(it => {
      const li = document.createElement('li');
      li.className = 'pcg__item';
      li.innerHTML = `
        <div class="pcg__badge" style="background:${it.hex_code}"></div>
        <div>
          <div><strong>${escapeHtml(it.label)}</strong></div>
          <small>${it.hex_code}</small>
        </div>
        <div class="pcg__actions">
          <button data-act="del-item" data-id="${it.id}">Xoá</button>
        </div>
      `;
      elItemList.appendChild(li);
    });
  };

  formCreateGroup.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(formCreateGroup);
    const name = (fd.get('name') || '').toString().trim();
    const note = (fd.get('note') || '').toString().trim();
    if (!name) return;

    try {
      await post('create_group', { project_id: projectId, name, note });
      formCreateGroup.reset();
      await loadGroups();
    } catch (err) {
      alert(err.message || 'Lỗi tạo nhóm');
    }
  });

  formCreateItem.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!currentGroupId) return;

    const fd = new FormData(formCreateItem);
    let label = (fd.get('label') || '').toString().trim();
    let hex   = (fd.get('hex_code') || '').toString().trim().toUpperCase();

    if (!label || !hex) return;

    try {
      // tạo preview đơn giản (HTML) — bạn có thể thay bằng SVG/base64
      const preview = `<div style="width:36px;height:24px;border-radius:6px;border:1px solid #e5e7eb;background:${hex}"></div>`;
      await post('create_item', { group_id: currentGroupId, label, hex_code: hex, preview });
      formCreateItem.reset();
      swatch.style.background = '';
      await loadItems(currentGroupId);
      await loadGroups(); // cập nhật item_count
    } catch (err) {
      alert(err.message || 'Lỗi tạo danh mục màu');
    }
  });

  elGroupList.addEventListener('click', async (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;
    const id = parseInt(btn.dataset.id || '0', 10);
    const act = btn.dataset.act;
    if (act === 'open') {
      // Lấy tên nhóm để hiển thị ở tiêu đề
      const name = btn.parentElement?.previousElementSibling?.querySelector('strong')?.textContent || '';
      await openGroup(id, name);
    }
    if (act === 'del') {
      if (confirm('Xoá nhóm này? (Sẽ xoá cả danh mục bên trong)')) {
        try {
          await post('delete_group', { id });
          if (id === currentGroupId) {
            currentGroupId = null;
            formCreateItem.setAttribute('disabled', 'true');
            itemsTitle.textContent = 'Danh mục màu';
            elItemList.innerHTML = '';
          }
          await loadGroups();
        } catch (err) {
          alert(err.message || 'Không xoá được nhóm');
        }
      }
    }
  });

  elItemList.addEventListener('click', async (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;
    const id = parseInt(btn.dataset.id || '0', 10);
    const act = btn.dataset.act;
    if (act === 'del-item') {
      if (confirm('Xoá danh mục màu này?')) {
        try {
          await post('delete_item', { id });
          await loadItems(currentGroupId);
          await loadGroups();
        } catch (err) {
          alert(err.message || 'Không xoá được danh mục');
        }
      }
    }
  });

  // Preview ô swatch khi gõ mã màu
  formCreateItem.querySelector('input[name="hex_code"]').addEventListener('input', (e) => {
    let v = e.target.value.trim();
    if (v && v[0] !== '#') v = '#' + v;
    if (/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(v)) {
      swatch.style.background = v;
    } else {
      swatch.style.background = '';
    }
  });

  const escapeHtml = (s) => (s || '').replace(/[&<>"']/g, (m) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[m]));

  // Khởi động
  loadGroups().catch(err => {
    elGroupList.innerHTML = `<li>Lỗi tải nhóm: ${err.message}</li>`;
  });
})();
