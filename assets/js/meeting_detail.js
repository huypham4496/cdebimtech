// assets/js/meeting_detail.js
(function(){
  const $ = (sel, ctx=document) => ctx.querySelector(sel);
  const $$ = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));
  const editor = $("#editor");
  const toolbar = $("#editor-toolbar");
  const fontSize = $("#font-size");
  const fontName = $("#font-name");
  const memberList = $("#member-list");
  const externalList = $("#external-list");

  function api(action, options={}) {
    const url = `?ajax=${encodeURIComponent(action)}&meeting_id=${encodeURIComponent(window.MEETING_ID)}`;
    return fetch(url, options);
  }

  function renderMemberCheckbox(u, checked) {
    const div = document.createElement('label');
    div.className = "member-item";
    div.innerHTML = `
      <input type="checkbox" value="${u.id}" ${checked ? 'checked' : ''}>
      <span class="name">${u.full_name || ''}</span>
      <span class="email">${u.email || ''}</span>
    `;
    return div;
  }

  function renderExternalRow(e={name:'', email:''}) {
    const row = document.createElement('div');
    row.className = 'external-row';
    row.innerHTML = `
      <input type="text" class="ext-name" placeholder="Full name" value="${e.external_name || e.name || ''}">
      <input type="email" class="ext-email" placeholder="Email (optional)" value="${e.external_email || e.email || ''}">
      <button class="icon remove" title="Remove">&times;</button>
    `;
    row.querySelector('.remove').addEventListener('click', () => row.remove());
    return row;
  }

  function applyToolbarCommand(cmd, value=null) {
    document.execCommand(cmd, false, value);
    editor.focus();
  }

  function insertTable(rows=3, cols=3) {
    const table = document.createElement('table');
    table.className = 'ed-table';
    for (let r=0;r<rows;r++) {
      const tr = document.createElement('tr');
      for (let c=0;c<cols;c++) {
        const td = document.createElement('td');
        td.innerHTML = '&nbsp;';
        tr.appendChild(td);
      }
      table.appendChild(tr);
    }
    const range = window.getSelection().getRangeAt(0);
    range.insertNode(table);
  }

  // Toolbar bindings
  toolbar.addEventListener('click', (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;
    const cmd = btn.dataset.cmd;
    if (!cmd) return;
    const value = btn.dataset.value || null;
    if (cmd === 'foreColor' || cmd === 'backColor') {
      applyToolbarCommand(cmd, value);
    } else {
      applyToolbarCommand(cmd);
    }
  });
  fontSize.addEventListener('change', () => {
    if (fontSize.value) applyToolbarCommand('fontSize', fontSize.value);
  });
  fontName.addEventListener('change', () => {
    if (fontName.value) applyToolbarCommand('fontName', fontName.value);
  });
  $("#btn-insert-table").addEventListener('click', () => insertTable(3,3));

  // Load data
  function loadAll() {
    api('load').then(r => r.json()).then(data => {
      if (data.error) { alert(data.error); return; }

      const m = data.meeting;
      $("#md-start-time").textContent = m.start_time || '—';
      $("#md-location").textContent = m.location || '—';
      const a = $("#md-online");
      if (m.online_link) {
        a.textContent = m.online_link;
        a.href = m.online_link;
      } else {
        a.textContent = '—';
        a.removeAttribute('href');
      }
      $("#md-short").textContent = m.short_desc || '—';

      if (data.detail && data.detail.content_html) {
        editor.innerHTML = data.detail.content_html;
      } else {
        editor.innerHTML = '<p><em>Ghi nội dung cuộc họp tại đây…</em></p>';
      }

      // Members
      memberList.innerHTML = '';
      const checkedUserIds = new Set((data.attendees || []).filter(x => !x.is_external).map(x => String(x.user_id)));
      (data.members || []).forEach(u => {
        memberList.appendChild(renderMemberCheckbox(u, checkedUserIds.has(String(u.id))));
      });

      // External attendees
      externalList.innerHTML = '';
      (data.attendees || []).filter(x => x.is_external).forEach(x => {
        externalList.appendChild(renderExternalRow(x));
      });
      if (externalList.children.length === 0) {
        externalList.appendChild(renderExternalRow());
      }

      if (data.needs_migration) {
        console.warn("Note: project_meeting_details table not found. Click Save to auto-create.");
      }
    }).catch(err => {
      console.error(err);
      alert('Failed to load meeting.');
    });
  }

  $("#btn-add-external").addEventListener('click', () => {
    externalList.appendChild(renderExternalRow());
  });

  $("#btn-save").addEventListener('click', () => {
    const content_html = editor.innerHTML;
    const selected_user_ids = $$("input[type=checkbox]", memberList).filter(i => i.checked).map(i => parseInt(i.value,10));
    const external_participants = $$(".external-row", externalList).map(row => ({
      name: $(".ext-name", row).value.trim(),
      email: $(".ext-email", row).value.trim()
    }));

    api('save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ content_html, selected_user_ids, external_participants })
    }).then(r => r.json()).then(data => {
      if (data.ok) {
        alert('Đã lưu và gửi thông báo (nếu có).');
        loadAll();
      } else {
        alert(data.error || 'Save failed');
      }
    }).catch(err => {
      console.error(err);
      alert('Save failed');
    });
  });

  $("#btn-export").addEventListener('click', () => {
    window.location.href = `?ajax=export_doc&meeting_id=${encodeURIComponent(window.MEETING_ID)}`;
  });

  // Init
  loadAll();
})();