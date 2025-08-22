/* global MEETING_ID */
(function () {
  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

  const editor = $("#editor");
  const toolbar = $("#editor-toolbar");
  const btnSave = $("#btn-save");
  const btnExport = $("#btn-export");
  const memberList = $("#member-list");
  const externalList = $("#external-list");
  const btnAddExternal = $("#btn-add-external");

  const mdStart = $("#md-start-time");
  const mdLocation = $("#md-location");
  const mdOnline = $("#md-online");
  const mdShort = $("#md-short");

  const blockFormat = $("#block-format");
  const fontNameSel = $("#font-name");
  const fontSizePtSel = $("#font-size-pt");
  const colorFore = $("#color-fore");
  const colorBack = $("#color-back");

  // -------- helpers --------
  const toast = (msg, type = "info") => {
    let t = document.createElement("div");
    t.className = `md-toast ${type}`;
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(() => t.classList.add("show"));
    setTimeout(() => { t.classList.remove("show"); setTimeout(() => t.remove(), 250); }, 2200);
  };

  const findOkTrue = (text) => /\{\s*"ok"\s*:\s*true\s*[,}]/i.test(text || "");
  const jsonSafe = async (resp) => {
    const text = await resp.text();
    try { return JSON.parse(text); } catch (e) {}
    const first = text.indexOf("{");
    const last = text.lastIndexOf("}");
    if (first !== -1 && last !== -1 && last > first) {
      const body = text.slice(first, last + 1);
      try { return JSON.parse(body); } catch (e) {}
    }
    // salvage: if ok:true appears anywhere, accept it
    if (findOkTrue(text)) return { ok: true, _raw: text };
    return { ok: false, _raw: text };
  };

  const fetchJSON = async (url, options = {}) => {
    const resp = await fetch(url, { credentials: "same-origin", ...options });
    const data = await jsonSafe(resp);
    // success conditions: http ok & no error, OR ok:true spotted
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

  const insertHTML = (html) => document.execCommand("insertHTML", false, html);
  const runCmd = (cmd, val = null) => document.execCommand(cmd, false, val);

  // apply inline style by wrapping selection with a <span style="...">
  const applyInlineStyle = (styleStr) => {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return;
    const range = sel.getRangeAt(0);
    if (range.collapsed) return;
    const frag = range.extractContents();
    const span = document.createElement("span");
    span.setAttribute("style", styleStr);
    span.appendChild(frag);
    range.insertNode(span);
    // restore selection around the span
    sel.removeAllRanges();
    const r = document.createRange();
    r.selectNodeContents(span);
    sel.addRange(r);
  };

  const applyFontSizePt = (pt) => {
    if (!pt) return;
    // dùng inline style để size chính xác theo pt (giống Word)
    applyInlineStyle(`font-size:${pt}pt`);
  };

  // -------- load data --------
  const loadAll = async () => {
    const url = `./project_tab_meetings_detail.php?ajax=load&meeting_id=${encodeURIComponent(MEETING_ID)}`;
    const { meeting, detail, attendees, members } = await fetchJSON(url);

    // KV1
    mdStart.textContent = meeting.start_time || "—";
    mdLocation.textContent = meeting.location || "—";
    mdOnline.textContent = meeting.online_link || "—";
    if (meeting.online_link) mdOnline.href = meeting.online_link;
    mdShort.textContent = meeting.short_desc || "—";

    // KV2
    editor.innerHTML = (detail && detail.content_html) ? detail.content_html : "";

    // KV3 members (internal)
    const picked = new Set(
      (attendees || [])
        .filter(a => Number(a.is_external) === 0 && a.user_id)
        .map(a => String(a.user_id))
    );

    memberList.innerHTML = "";
    members.forEach(m => {
      const id = `mem_${m.id}`;
      const row = createEl("label", "mem-row");
      row.innerHTML = `
        <input type="checkbox" class="mem-ckb" value="${m.id}" id="${id}" ${picked.has(String(m.id)) ? "checked" : ""}>
        <span class="mem-name">${(m.full_name || "").trim() || (m.email || "")}</span>
        <span class="mem-email">${m.email || ""}</span>
      `;
      memberList.appendChild(row);
    });

    // KV3 externals
    externalList.innerHTML = "";
    (attendees || [])
      .filter(a => Number(a.is_external) === 1)
      .forEach(a => addExternalRow(a.external_name || "", a.external_email || ""));
  };

  // -------- external rows --------
  const addExternalRow = (name = "", email = "") => {
    const row = createEl("div", "ext-row");
    row.innerHTML = `
      <input type="text" class="ext-name" placeholder="Họ tên" value="${name}">
      <input type="email" class="ext-email" placeholder="Email" value="${email}">
      <button class="btn icon danger ext-del" title="Xóa">&times;</button>
    `;
    row.querySelector(".ext-del").addEventListener("click", () => row.remove());
    externalList.appendChild(row);
    return row;
  };
  btnAddExternal.addEventListener("click", () => addExternalRow());

  // -------- toolbar events --------
  toolbar.addEventListener("click", (e) => {
    const btn = e.target.closest("button");
    if (!btn) return;

    const cmd = btn.getAttribute("data-cmd");
    if (cmd) {
      const val = btn.getAttribute("data-value") || null;
      runCmd(cmd, val);
      editor.focus();
      return;
    }

    if (btn.id === "btn-link") {
      const url = prompt("URL:");
      if (url) runCmd("createLink", url);
      return;
    }
    if (btn.id === "btn-unlink") { runCmd("unlink"); return; }
    if (btn.id === "btn-image") {
      const url = prompt("Ảnh URL:");
      if (url) runCmd("insertImage", url);
      return;
    }
    if (btn.id === "btn-hr") { runCmd("insertHorizontalRule"); return; }
    if (btn.id === "btn-clear") { runCmd("removeFormat"); return; }
    if (btn.id === "btn-insert-table") {
      const r = Math.max(1, parseInt(prompt("Số hàng:", "3") || "3", 10));
      const c = Math.max(1, parseInt(prompt("Số cột:", "3") || "3", 10));
      let html = '<table class="md-table"><tbody>';
      for (let i = 0; i < r; i++) {
        html += "<tr>";
        for (let j = 0; j < c; j++) html += "<td>&nbsp;</td>";
        html += "</tr>";
      }
      html += "</tbody></table>";
      insertHTML(html);
      return;
    }
  });

  blockFormat.addEventListener("change", (e) => {
    const tag = e.target.value || "p";
    document.execCommand("formatBlock", false, tag === "p" ? "p" : tag);
    editor.focus();
  });
  fontNameSel.addEventListener("change", (e) => {
    if (e.target.value) document.execCommand("fontName", false, e.target.value);
    editor.focus();
  });
  fontSizePtSel.addEventListener("change", (e) => {
    const pt = e.target.value;
    if (pt) applyFontSizePt(pt);
    editor.focus();
  });
  colorFore.addEventListener("input", (e) => { document.execCommand("foreColor", false, e.target.value); editor.focus(); });
  colorBack.addEventListener("input", (e) => {
    // dùng hiliteColor (phổ biến hơn backColor)
    if (!document.queryCommandSupported("hiliteColor")) document.execCommand("backColor", false, e.target.value);
    else document.execCommand("hiliteColor", false, e.target.value);
    editor.focus();
  });

  // -------- save --------
  const save = async () => {
    const selected_user_ids = $$(".mem-ckb:checked").map(i => parseInt(i.value, 10)).filter(Boolean);
    const external_participants = $$(".ext-row").map(r => ({
      name: $(".ext-name", r).value.trim(),
      email: $(".ext-email", r).value.trim()
    })).filter(x => x.name || x.email);

    const payload = {
      content_html: editor.innerHTML,
      selected_user_ids,
      external_participants
    };

    btnSave.disabled = true;
    btnSave.classList.add("loading");

    try {
      const url = `./project_tab_meetings_detail.php?ajax=save&meeting_id=${encodeURIComponent(MEETING_ID)}`;
      const data = await fetchJSON(url, {
        method: "POST",
        headers: { "Content-Type": "application/json; charset=utf-8" },
        body: JSON.stringify(payload)
      });
      if (data && data.ok) {
        toast("Đã lưu & gửi thông báo", "success");
      } else {
        throw new Error((data && (data.detail || data.error)) || "Save failed");
      }
    } catch (err) {
      console.error("Save raw:", err._raw || err);
      // nếu server vẫn trả noise nhưng có ok:true thì fetchJSON đã coi là thành công; còn lại báo lỗi
      toast("Save failed: " + err.message, "error");
    } finally {
      btnSave.disabled = false;
      btnSave.classList.remove("loading");
    }
  };
  btnSave.addEventListener("click", save);

  // -------- export --------
  btnExport.addEventListener("click", () => {
    const url = `./project_tab_meetings_detail.php?ajax=export_doc&meeting_id=${encodeURIComponent(MEETING_ID)}`;
    window.location.href = url;
  });

  // init
  const init = async () => {
    await loadAll();
    // set A4 width preview by adding class
    editor.classList.add("editor-a4");
  };
  init().catch(e => toast("Load failed: " + e.message, "error"));
})();
