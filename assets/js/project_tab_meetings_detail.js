
/* global MEETING_ID, MEETING_ENDPOINT_BASE */
(function () {
  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

  const editorWrap = $("#editor");
  const btnSave = $("#btn-save");
  const btnExport = $("#btn-export");
  const memberList = $("#member-list");
  const externalList = $("#external-list");
  const btnAddExternal = $("#btn-add-external");

  const mdStart = $("#md-start-time");
  const mdLocation = $("#md-location");
  const mdOnline = $("#md-online");
  const mdShort = $("#md-short");

  let quill = null;

  /* --------- Loaders ---------- */
  function loadScript(src) {
    return new Promise((res, rej) => {
      const s = document.createElement("script");
      s.src = src; s.async = true;
      s.onload = res; s.onerror = () => rej(new Error("Failed to load " + src));
      document.head.appendChild(s);
    });
  }
  function loadCSS(href) {
    return new Promise((res, rej) => {
      if ([...document.styleSheets].some(ss => (ss.href || "").includes(href))) return res();
      const l = document.createElement("link");
      l.rel = "stylesheet"; l.href = href;
      l.onload = res; l.onerror = () => rej(new Error("Failed to load " + href));
      document.head.appendChild(l);
    });
  }
  async function loadQuill() {
    await loadCSS("https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css");
    await loadCSS("https://unpkg.com/quill-better-table@1.2.10/dist/quill-better-table.css");
    await loadScript("https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js");
    try { await loadScript("https://unpkg.com/quill-better-table@1.2.10/dist/quill-better-table.min.js"); }
    catch (e) { console.warn("quill-better-table not available:", e.message); }
  }

  /* --------- Toast ---------- */
  const toast = (msg, type = "info") => {
    const t = document.createElement("div");
    t.className = `md-toast ${type}`;
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(() => t.classList.add("show"));
    setTimeout(() => { t.classList.remove("show"); setTimeout(() => t.remove(), 250); }, 2200);
  };

  const jsonSafe = async (resp) => {
    const text = await resp.text();
    try { return JSON.parse(text); } catch (e) {}
    const first = text.indexOf("{");
    const last = text.lastIndexOf("}");
    if (first !== -1 && last !== -1 && last > first) {
      const body = text.slice(first, last + 1);
      try { return JSON.parse(body); } catch (e) {}
    }
    return { ok: resp.ok, _raw: text };
  };
  const fetchJSON = async (url, options = {}) => {
    const resp = await fetch(url, { credentials: "same-origin", ...options });
    const data = await jsonSafe(resp);
    if ((resp.ok && !(data && data.error)) || (data && data.ok === true)) return data;
    const msg = (data && (data.detail || data.error)) || `HTTP ${resp.status}`;
    const err = new Error(typeof msg === "string" ? msg : "Unknown error");
    err._raw = data && data._raw;
    throw err;
  };
  const createEl = (tag, cls, html) => {
    const el = document.createElement(tag);
    if (cls) el.className = cls;
    if (html !== undefined) el.innerHTML = html;
    return el;
  };

  /* --------- Data load -------- */
  const loadAll = async () => {
    const base = (window.MEETING_ENDPOINT_BASE || "./");
    const url = `${base}project_tab_meetings_detail.php?ajax=load&meeting_id=${encodeURIComponent(MEETING_ID)}`;
    const { meeting, detail, attendees, members } = await fetchJSON(url);

    if (mdStart) mdStart.textContent = meeting.start_time || "—";
    if (mdLocation) mdLocation.textContent = meeting.location || "—";
    if (mdOnline) {
      mdOnline.textContent = meeting.online_link || "—";
      if (meeting.online_link) mdOnline.href = meeting.online_link;
    }
    if (mdShort) mdShort.textContent = meeting.short_desc || "—";

    window.setEditorHTML((detail && detail.content_html) ? detail.content_html : "");

    const picked = new Set(
      (attendees || [])
        .filter(a => Number(a.is_external) === 0 && a.user_id)
        .map(a => String(a.user_id))
    );
    if (memberList) {
      memberList.innerHTML = "";
      (members || []).forEach(m => {
        const id = `mem_${m.id}`;
        const row = createEl("label", "mem-row");
        row.innerHTML = `
          <input type="checkbox" class="mem-ckb" value="${m.id}" id="${id}" ${picked.has(String(m.id)) ? "checked" : ""}>
          <span class="mem-name">${(m.full_name || "").trim() || (m.email || "")}</span>
          <span class="mem-email">${m.email || ""}</span>
        `;
        memberList.appendChild(row);
      });
    }

    if (externalList) {
      externalList.innerHTML = "";
      (attendees || [])
        .filter(a => Number(a.is_external) === 1)
        .forEach(a => addExternalRow(a.external_name || "", a.external_email || ""));
    }
  };

  /* -------- external rows -------- */
  const addExternalRow = (name = "", email = "") => {
    const row = createEl("div", "ext-row");
    row.innerHTML = `
      <input type="text" class="ext-name" placeholder="Họ tên" value="${name}">
      <input type="email" class="ext-email" placeholder="Email" value="${email}">
      <button class="btn icon danger ext-del" title="Xóa">&times;</button>
    `;
    row.querySelector(".ext-del").addEventListener("click", () => row.remove());
    externalList && externalList.appendChild(row);
    return row;
  };
  btnAddExternal && btnAddExternal.addEventListener("click", () => addExternalRow());

  /* --------- Quill init -------- */
  async function initQuill(initialHTML) {
    await loadQuill();
    if (!window.Quill) throw new Error("Quill chưa sẵn sàng");

    // Size & Font whitelist
    const Size = Quill.import('attributors/style/size');
    Size.whitelist = ['8pt','9pt','10pt','11pt','12pt','14pt','16pt','18pt','20pt','22pt','24pt','26pt','28pt','36pt','48pt','72pt'];
    Quill.register(Size, true);
    const Font = Quill.import('attributors/style/font');
    Font.whitelist = ['Arial','Times New Roman','Tahoma','Verdana','Courier New'];
    Quill.register(Font, true);

    const toolbarOptions = [
      [{ header: [1, 2, 3, 4, 5, 6, false] }],
      [{ font: ['Arial','Times New Roman','Tahoma','Verdana','Courier New'] }],
      [{ size: ['8pt','9pt','10pt','11pt','12pt','14pt','16pt','18pt','20pt','22pt','24pt','26pt','28pt','36pt','48pt','72pt'] }],
      ['bold','italic','underline','strike', { script: 'sub' }, { script: 'super' }],
      ['blockquote','code-block'],
      [{ color: [] }, { background: [] }],
      [{ align: [] }, { direction: 'rtl' }],
      [{ list: 'ordered' }, { list: 'bullet' }, { list: 'check' }],
      [{ indent: '-1' }, { indent: '+1' }],
      ['link','image','video','clean']
    ];

    quill = new Quill('#editor', {
      theme: 'snow',
      modules: {
        toolbar: toolbarOptions,
        history: { delay: 1000, maxStack: 200, userOnly: true },
        ...(window.QuillBetterTable ? {
          'better-table': {
            operationMenu: { items: {} },
            bordered: true
          },
          keyboard: { bindings: window.QuillBetterTable.keyboardBindings }
        } : {})
      },
      formats: [
        'header','font','size',
        'bold','italic','underline','strike','script',
        'blockquote','code-block',
        'color','background','align','direction',
        'list','indent','link','image','video'
      ]
    });

    // Dán nội dung ban đầu
    if (initialHTML && typeof initialHTML === 'string') {
      quill.clipboard.dangerouslyPasteHTML(initialHTML, 'silent');
    }

    // ---- Thêm nút custom: Undo / Redo / Insert Table / Toggle Borders ----
    const toolbar = quill.getModule('toolbar');
    if (toolbar && toolbar.container) {
      const grp = document.createElement('span');
      grp.className = 'ql-formats';

      // Undo
      const btnUndo = document.createElement('button');
      btnUndo.type = 'button';
      btnUndo.className = 'ql-custom-btn ql-undo';
      btnUndo.title = 'Undo (Ctrl+Z)';
      btnUndo.innerHTML = '<svg viewBox="0 0 24 24"><path d="M12 5a7 7 0 0 1 0 14h-6" fill="none" stroke="currentColor" stroke-width="2"/><path d="M7 9l-4 4 4 4" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
      btnUndo.addEventListener('click', () => quill.history.undo());
      grp.appendChild(btnUndo);

      // Redo
      const btnRedo = document.createElement('button');
      btnRedo.type = 'button';
      btnRedo.className = 'ql-custom-btn ql-redo';
      btnRedo.title = 'Redo (Ctrl+Y)';
      btnRedo.innerHTML = '<svg viewBox="0 0 24 24"><path d="M12 5a7 7 0 0 0 0 14h6" fill="none" stroke="currentColor" stroke-width="2"/><path d="M17 9l4 4-4 4" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
      btnRedo.addEventListener('click', () => quill.history.redo());
      grp.appendChild(btnRedo);

      // Insert Table (grid picker)
      const btnTable = document.createElement('button');
      btnTable.type = 'button';
      btnTable.className = 'ql-custom-btn ql-insert-table';
      btnTable.title = 'Insert table';
      btnTable.innerHTML = '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
      grp.appendChild(btnTable);

      // Toggle Borders
      const btnBorders = document.createElement('button');
      btnBorders.type = 'button';
      btnBorders.className = 'ql-custom-btn ql-toggle-borders';
      btnBorders.title = 'Toggle table borders';
      btnBorders.innerHTML = '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 9h18M9 3v18" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
      grp.appendChild(btnBorders);

      toolbar.container.appendChild(grp);

      // Table size picker popover
      const picker = buildTablePicker((rows, cols) => {
        insertTable(rows, cols);
        hidePicker();
      });
      document.body.appendChild(picker.el);

      function showPickerNear(btn) {
        const r = btn.getBoundingClientRect();
        picker.el.style.left = (window.scrollX + r.left) + 'px';
        picker.el.style.top = (window.scrollY + r.bottom + 6) + 'px';
        picker.el.style.display = 'block';
      }
      function hidePicker() { picker.el.style.display = 'none'; }
      const isPickerOpen = () => picker.el.style.display === 'block';

      // Hover-to-open + click-to-toggle for Insert Table
      let overBtn = false, overPicker = false, hideTimer = null;
      const scheduleHide = () => {
        clearTimeout(hideTimer);
        hideTimer = setTimeout(() => {
          if (!overBtn && !overPicker) hidePicker();
        }, 180);
      };
      btnTable.addEventListener('mouseenter', () => {
        overBtn = true;
        if (!isPickerOpen()) showPickerNear(btnTable);
      });
      btnTable.addEventListener('mouseleave', () => { overBtn = false; scheduleHide(); });
      btnTable.addEventListener('focus', () => { if (!isPickerOpen()) showPickerNear(btnTable); });
      btnTable.addEventListener('blur', () => { scheduleHide(); });
      btnTable.addEventListener('click', (e) => {
        e.preventDefault(); e.stopPropagation();
        if (isPickerOpen()) hidePicker(); else showPickerNear(btnTable);
      });

      picker.el.addEventListener('mouseenter', () => { overPicker = true; });
      picker.el.addEventListener('mouseleave', () => { overPicker = false; scheduleHide(); });

      document.addEventListener('click', (e) => {
        if (!picker.el.contains(e.target) && !btnTable.contains(e.target)) hidePicker();
      });

      btnBorders.addEventListener('click', toggleBordersAtSelection);
    }
  }

  // ---- Table picker builder ----
  function buildTablePicker(onPick, max=10) {
    const el = document.createElement('div');
    el.className = 'ql-table-picker';
    const grid = document.createElement('div');
    grid.className = 'grid';
    const hint = document.createElement('div');
    hint.className = 'hint';
    hint.textContent = '0 × 0';
    el.appendChild(grid);
    el.appendChild(hint);

    let hoverR = 0, hoverC = 0;
    for (let r=1;r<=max;r++){
      for (let c=1;c<=max;c++){
        const cell = document.createElement('div');
        cell.className = 'cell';
        cell.dataset.r = r;
        cell.dataset.c = c;
        grid.appendChild(cell);
      }
    }
    const cells = () => Array.from(grid.children);
    const redraw = () => {
      cells().forEach(cell => {
        const r = +cell.dataset.r, c = +cell.dataset.c;
        cell.classList.toggle('active', r<=hoverR && c<=hoverC);
      });
      hint.textContent = `${hoverR} × ${hoverC}`;
    };
    grid.addEventListener('mousemove', (e) => {
      const t = e.target.closest('.cell'); if (!t) return;
      hoverR = +t.dataset.r; hoverC = +t.dataset.c; redraw();
    });
    grid.addEventListener('mouseleave', () => { hoverR=0; hoverC=0; redraw(); });
    grid.addEventListener('click', (e) => {
      const t = e.target.closest('.cell'); if (!t) return;
      const r = +t.dataset.r, c = +t.dataset.c;
      onPick && onPick(r,c);
    });

    return { el };
  }

  // ---- Helpers: robust HTML insertion ----
  function insertHTMLAtCursor(html) {
    try {
      quill.focus();
      let sel = window.getSelection();
      const root = quill.root;
      if (!sel || sel.rangeCount === 0 || !root.contains(sel.anchorNode)) {
        // đặt caret cuối tài liệu nếu đang ngoài editor
        quill.setSelection(quill.getLength(), 0);
        sel = window.getSelection();
      }
      if (!sel || sel.rangeCount === 0) {
        // fallback clipboard nếu vẫn không có range
        const idx = (quill.getSelection(true) && quill.getSelection(true).index) || quill.getLength();
        quill.clipboard.dangerouslyPasteHTML(idx, html, 'user');
        return true;
      }
      const range = sel.getRangeAt(0);
      range.deleteContents();
      const container = document.createElement('div');
      container.innerHTML = html;
      const frag = document.createDocumentFragment();
      let node, lastNode = null;
      while ((node = container.firstChild)) { lastNode = frag.appendChild(node); }
      range.insertNode(frag);
      if (lastNode) {
        const newRange = document.createRange();
        newRange.setStartAfter(lastNode);
        newRange.setEndAfter(lastNode);
        sel.removeAllRanges();
        sel.addRange(newRange);
      }
      return true;
    } catch (e) {
      console.warn('insertHTMLAtCursor fallback:', e.message);
      return false;
    }
  }

  // ---- Helpers: table ops ----
  function insertTable(rows, cols){
    if (!quill) return;

    // 1) Try plugin first
    try {
      const mod = quill.getModule('better-table');
      if (mod && typeof mod.insertTable === 'function') {
        mod.insertTable(rows, cols);
        return;
      }
    } catch(_) {}

    // 2) Native DOM insertion of a <table>, avoiding Quill's sanitize path
    const html = (() => {
      let s = '<table class="md-table"><tbody>';
      for (let r=0;r<rows;r++){
        s += '<tr>';
        for (let c=0;c<cols;c++){ s += '<td>&nbsp;</td>'; }
        s += '</tr>';
      }
      s += '</tbody></table>';
      return s;
    })();

    if (insertHTMLAtCursor(html)) return;

    // 3) Clipboard fallback
    const before = quill.getLength();
    const range = quill.getSelection(true);
    const index = range ? range.index : before;
    quill.clipboard.dangerouslyPasteHTML(index, html, 'user');
  }

  function findAncestor(node, tag) {
    const t = String(tag || '').toUpperCase();
    while (node && node !== quill.root) {
      if (node.tagName === t) return node;
      node = node.parentNode;
    }
    return null;
  }
  function getTableAtSelection(){
    if (!quill) return null;
    const sel = quill.getSelection(true);
    if (!sel) return null;
    const leafTuple = quill.getLeaf(sel.index);
    const leaf = leafTuple && leafTuple[0];
    if (!leaf || !leaf.domNode) return null;
    return findAncestor(leaf.domNode, 'TABLE');
  }
  function toggleBordersAtSelection(){
    const tbl = getTableAtSelection();
    if (!tbl) { toast('Đặt con trỏ vào bảng để bật/tắt viền'); return; }
    tbl.classList.toggle('no-borders');
  }

  /* -------- save -------- */
  const save = async () => {
    const selected_user_ids = $$(".mem-ckb:checked").map(i => parseInt(i.value, 10)).filter(Boolean);
    const external_participants = $$(".ext-row").map(r => ({
      name: $(".ext-name", r).value.trim(),
      email: $(".ext-email", r).value.trim()
    })).filter(x => x.name || x.email);

    const payload = {
      content_html: window.getEditorHTML(),
      selected_user_ids,
      external_participants
    };

    if (btnSave) {
      btnSave.disabled = true;
      btnSave.classList.add("loading");
    }

    try {
      const base = (window.MEETING_ENDPOINT_BASE || './');
      const url = `${base}project_tab_meetings_detail.php?ajax=save&meeting_id=${encodeURIComponent(MEETING_ID)}`;
      const data = await fetchJSON(url, {
        method: "POST",
        headers: { "Content-Type": "application/json; charset=utf-8" },
        body: JSON.stringify(payload)
      });
      if (data && data.ok) toast("Đã lưu & gửi thông báo", "success");
      else throw new Error((data && (data.detail || data.error)) || "Save failed");
    } catch (err) {
      console.error("Save raw:", err._raw || err);
      toast("Save failed: " + err.message, "error");
    } finally {
      if (btnSave) { btnSave.disabled = false; btnSave.classList.remove("loading"); }
    }
  };
  btnSave && btnSave.addEventListener("click", save);

  /* -------- export -------- */
  btnExport && btnExport.addEventListener("click", () => {
    const base = (window.MEETING_ENDPOINT_BASE || './');
    const url = `${base}project_tab_meetings_detail.php?ajax=export_doc&meeting_id=${encodeURIComponent(MEETING_ID)}`;
    window.location.href = url;
  });

  // Public helpers save/load HTML
  window.getEditorHTML = function(){
    if (quill) return quill.root.innerHTML;
    return editorWrap ? editorWrap.innerHTML : '';
  };
  window.setEditorHTML = function(html){
    if (quill) { quill.clipboard.dangerouslyPasteHTML(html || '', 'silent'); return; }
    if (editorWrap) editorWrap.innerHTML = html || '';
  };

  // init
  const init = async () => {
    await loadAll();
    if (editorWrap) editorWrap.classList.add("editor-a4");
    const initialHTML = window.getEditorHTML();
    await initQuill(initialHTML);
  };
  init().catch(e => {
    console.error(e);
    toast("Load failed: " + e.message, "error");
  });
})();
