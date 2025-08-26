/* global MEETING_ID, MEETING_ENDPOINT_BASE */
(function () {
  // ---------- Short helpers ----------
  var $  = function(sel, ctx){ return (ctx||document).querySelector(sel); };
  var $$ = function(sel, ctx){ return Array.prototype.slice.call((ctx||document).querySelectorAll(sel)); };

  var editorWrap      = $("#editor");          // KV2 editor box (target element)
  var btnSave         = $("#btn-save");
  var btnExport       = $("#btn-export");
  var memberList      = $("#member-list");
  var externalList    = $("#external-list");
  var btnAddExternal  = $("#btn-add-external");

  var mdStart    = $("#md-start-time");
  var mdLocation = $("#md-location");
  var mdOnline   = $("#md-online");
  var mdShort    = $("#md-short");

  // ---------- Extract PROJECT_ID from current URL (?id=...) ----------
  var PROJECT_ID = (function () {
    try {
      if (typeof window.PROJECT_ID === "number") return window.PROJECT_ID;
      var idStr = new URLSearchParams(window.location.search).get("id");
      var n = parseInt(idStr, 10);
      return isNaN(n) ? 0 : n;
    } catch (e) {
      return 0;
    }
  })();

  // ---------- Track dirty state (unsaved changes) ----------
  var isDirty = false;

  // Warn when leaving the page if there are unsaved changes
  window.addEventListener("beforeunload", function(e){
    if (isDirty) {
      e.preventDefault();
      e.returnValue = ""; // required for Chrome
    }
  });

  // ---------- Utilities ----------
  function loadScript(src) {
    return new Promise(function(resolve, reject){
      var s = document.createElement("script");
      s.src = src; s.async = true;
      s.onload = resolve;
      s.onerror = function(){ reject(new Error("Failed to load " + src)); };
      document.head.appendChild(s);
    });
  }
  function createEl(tag, cls, html){
    var el = document.createElement(tag);
    if (cls) el.className = cls;
    if (typeof html !== "undefined") el.innerHTML = html;
    return el;
  }
  function toast(msg, type){
    type = type || "info";
    var t = document.createElement("div");
    t.className = "md-toast " + type;
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(function(){ t.classList.add("show"); });
    setTimeout(function(){ t.classList.remove("show"); setTimeout(function(){ t.remove(); }, 250); }, 2200);
  }
  function jsonSafe(resp) {
    return resp.text().then(function(text){
      try { return JSON.parse(text); } catch(e){}
      var first = text.indexOf("{");
      var last  = text.lastIndexOf("}");
      if (first !== -1 && last !== -1 && last > first) {
        var body = text.slice(first, last + 1);
        try { return JSON.parse(body); } catch(e2){}
      }
      return { ok: resp.ok, _raw: text };
    });
  }
  function fetchJSON(url, options) {
    options = options || {};
    options.credentials = "same-origin";
    return fetch(url, options).then(function(resp){
      return jsonSafe(resp).then(function(data){
        if ((resp.ok && !(data && data.error)) || (data && data.ok === true)) return data;
        var msg = (data && (data.detail || data.error)) || ("HTTP " + resp.status);
        var err = new Error(typeof msg === "string" ? msg : "Unknown error");
        err._raw = data && data._raw;
        throw err;
      });
    });
  }

  // ---------- Public helpers for HTML ----------
  window.getEditorHTML = function(){
    if (window.tinymce && window.tinymce.activeEditor) {
      return window.tinymce.activeEditor.getContent({ format: "html" });
    }
    return editorWrap ? editorWrap.innerHTML : "";
  };
  window.setEditorHTML = function(html){
    if (window.tinymce && window.tinymce.activeEditor) {
      window.tinymce.activeEditor.setContent(html || ""); return;
    }
    if (editorWrap) editorWrap.innerHTML = html || "";
  };

  // ---------- Data load (KV1 + KV3, KV2 content HTML) ----------
  function loadAll(){
    var base = (window.MEETING_ENDPOINT_BASE || "./");
    var url  = base + "project_tab_meetings_detail.php?ajax=load&meeting_id=" + encodeURIComponent(MEETING_ID);
    return fetchJSON(url).then(function(res){
      var meeting   = res.meeting || {};
      var detail    = res.detail || {};
      var attendees = res.attendees || [];
      var members   = res.members || [];

      if (mdStart)    mdStart.textContent    = meeting.start_time  || "—";
      if (mdLocation) mdLocation.textContent = meeting.location    || "—";
      if (mdOnline) {
        mdOnline.textContent = meeting.online_link || "—";
        if (meeting.online_link) mdOnline.href = meeting.online_link;
      }
      if (mdShort)    mdShort.textContent    = meeting.short_desc  || "—";

      // Pre-set DOM content (TinyMCE will load this on init)
      window.setEditorHTML(detail.content_html || "");

      // Mark clean on initial load (no unsaved changes yet)
      isDirty = false;

      // Members (internal)
      if (memberList) {
        var picked = {};
        attendees.forEach(function(a){ if (Number(a.is_external) === 0 && a.user_id) picked[String(a.user_id)] = true; });
        memberList.innerHTML = "";
        members.forEach(function(m){
          var id = "mem_" + m.id;
          var row = createEl("label", "mem-row");
          row.innerHTML =
            '<input type="checkbox" class="mem-ckb" value="'+ m.id +'" id="'+ id +'" '+ (picked[String(m.id)] ? "checked" : "") +'>\
             <span class="mem-name">'+ ((m.full_name || "").trim() || (m.email || "")) +'</span>\
             <span class="mem-email">'+ (m.email || "") +'</span>';
          memberList.appendChild(row);
        });

        // Any checkbox change -> mark dirty
        memberList.addEventListener("change", function(e){
          if (e.target && e.target.classList.contains("mem-ckb")) {
            isDirty = true;
          }
        }, { once: false });
      }

      // Externals
      if (externalList) {
        externalList.innerHTML = "";
        attendees.filter(function(a){ return Number(a.is_external) === 1; })
          .forEach(function(a){ addExternalRow(a.external_name || "", a.external_email || ""); });
      }
    });
  }

  // ---------- External attendees ----------
  function addExternalRow(name, email){
    name = name || ""; email = email || "";
    var row = createEl("div", "ext-row");
    row.innerHTML =
      '<input type="text" class="ext-name" placeholder="Họ tên" value="'+ name +'">\
       <input type="email" class="ext-email" placeholder="Email" value="'+ email +'">\
       <button class="btn icon danger ext-del" title="Xóa">&times;</button>';
    row.querySelector(".ext-del").addEventListener("click", function(){ 
      row.remove(); 
      isDirty = true;
    });
    // Mark dirty on edits
    row.querySelector(".ext-name").addEventListener("input", function(){ isDirty = true; });
    row.querySelector(".ext-email").addEventListener("input", function(){ isDirty = true; });
    if (externalList) externalList.appendChild(row);
    return row;
  }
  if (btnAddExternal) btnAddExternal.addEventListener("click", function(){ 
    addExternalRow("", ""); 
    // Mark dirty when user actually types (inputs already have listeners)
  });

  // ---------- Toolbar host BEFORE editor so it always stays visible ----------
  function ensureToolbarHostAdjacent(){
    if (!editorWrap) return;
    var host = document.getElementById("kv2-toolbar");
    if (!host) {
      host = document.createElement("div");
      host.id = "kv2-toolbar";
      host.className = "kv2-toolbar";
      host.setAttribute("contenteditable","false");
      host.style.userSelect = "none";
    }
    if (editorWrap.previousSibling !== host) {
      editorWrap.parentNode.insertBefore(host, editorWrap);
    }
  }

  // ---------- TinyMCE (classic) ----------
  function loadTiny(){
    // tinymce folder is sibling of pages: ../tinymce/js/tinymce/tinymce.min.js
    return loadScript("../tinymce/js/tinymce/tinymce.min.js");
  }

  function initTiny(initialHTML){
    if (!window.tinymce) throw new Error("TinyMCE chưa sẵn sàng");
    // Classic mode (iframe) to keep toolbar always visible
    window.tinymce.remove();
    window.tinymce.init({
      selector: "#editor",
      inline: false,
      fixed_toolbar_container: "#kv2-toolbar",
      menubar: "file edit view insert format tools table help",
      // Self-hosted
      base_url: "../tinymce/js/tinymce",
      suffix: ".min",
      license_key: "gpl",
      plugins: "advlist anchor autolink autoresize autosave charmap code codesample directionality emoticons fullscreen help image importcss insertdatetime link lists media nonbreaking pagebreak preview quickbars save searchreplace table visualblocks visualchars wordcount",
      toolbar: "undo redo | blocks fontfamily fontsize | bold italic underline strikethrough subscript superscript | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | charmap emoticons insertdatetime | removeformat | code fullscreen | searchreplace",
      table_toolbar: "tableprops tabledelete | tableinsertrowbefore tableinsertrowafter tabledeleterow | tableinsertcolbefore tableinsertcolafter tabledeletecol | tablemergecells tablesplitcells",
      table_default_attributes: { border: "1" },
      table_default_styles: { "border-collapse": "collapse", width: "100%" },
      branding: false,
      content_style:
        "body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;font-size:15px;line-height:1.7;color:#0f172a;}"+
        "p:first-child{margin-top:0}"+
        "table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;font-size:14px;}"+
        "th{text-align:left;background:#f3f6fb;font-weight:600}"+
        "td,th{border:1px solid #e5e7eb;padding:10px;vertical-align:top}"+
        "blockquote{border-left:3px solid #2563eb;background:#f8fafc;margin:10px 0;padding:10px 12px;border-radius:10px;}"+
        "img{max-width:100%;height:auto;border-radius:10px;box-shadow:0 6px 14px rgba(15,23,42,.06)}",
      // ---- Upload images directly to server (robust handler) ----
      automatic_uploads: true,
      images_upload_credentials: true,
      paste_data_images: true,
      images_reuse_filename: false,
      convert_urls: false,
      images_file_types: "jpg,jpeg,png,gif,webp",
      images_upload_handler: function (blobInfo, progress) {
        return new Promise(function(resolve, reject){
          var base = (window.MEETING_ENDPOINT_BASE || "./");
          // POST body only (no query string) — some servers reject query on multipart
          var url  = base + "project_tab_meetings_detail.php";

          var fd = new FormData();
          fd.append("file", blobInfo.blob(), blobInfo.filename());
          fd.append("ajax", "upload_image");
          fd.append("meeting_id", MEETING_ID);
          fd.append("project_id", PROJECT_ID); // <-- so PHP can build PRJxxxxx without DB
          // Optional CSRF token
          var meta = document.querySelector('meta[name="csrf-token"]');
          if (meta && meta.content) { fd.append("csrf_token", meta.content); }

          var xhr = new XMLHttpRequest();
          xhr.open("POST", url, true);
          xhr.withCredentials = true;
          xhr.upload.onprogress = function (e){ if (e.lengthComputable) progress(e.loaded / e.total * 100); };
          xhr.onload = function(){
            var body = xhr.responseText || "";
            if (xhr.status < 200 || xhr.status >= 300) {
              var msg = "HTTP " + xhr.status;
              try { var j = JSON.parse(body); if (j && (j.error || j.message)) msg += " - " + (j.error || j.message); } catch(e){}
              reject(msg);
              return;
            }
            var json;
            try { json = JSON.parse(body); } catch(e){ reject("Invalid JSON from server"); return; }
            if (!json || !(json.location || json.url)) { reject((json && (json.error||json.message)) || "Invalid response"); return; }
            resolve(json.location || json.url);
          };
          xhr.onerror = function(){ reject("Network error"); };
          xhr.send(fd);
        });
      },
      setup: function(ed){
        ed.on("init", function(){
          if (typeof initialHTML === "string") ed.setContent(initialHTML || "");
          // Editor just initialized -> mark clean
          if (ed.setDirty) ed.setDirty(false);
          if (ed.undoManager && ed.undoManager.clear) ed.undoManager.clear();
          isDirty = false;
        });

        // Any change to content -> mark dirty
        ed.on("change input undo redo keyup SetContent", function(){
          // init has already marked clean; subsequent SetContent (e.g., paste) should mark dirty
          isDirty = true;
        });
      }
    });
  }

  // ---------- Save / Export ----------
  function doSave(){
    var selected_user_ids = $$(".mem-ckb:checked").map(function(i){ return parseInt(i.value, 10); }).filter(function(n){ return !!n; });
    var external_participants = $$(".ext-row").map(function(r){
      return { name: $(".ext-name", r).value.trim(), email: $(".ext-email", r).value.trim() };
    }).filter(function(x){ return x.name || x.email; });

    var payload = {
      content_html: window.getEditorHTML(),
      selected_user_ids: selected_user_ids,
      external_participants: external_participants
    };

    if (btnSave) { btnSave.disabled = true; btnSave.classList.add("loading"); }
    var base = (window.MEETING_ENDPOINT_BASE || "./");
    var url  = base + "project_tab_meetings_detail.php?ajax=save&meeting_id=" + encodeURIComponent(MEETING_ID);
    fetchJSON(url, {
      method: "POST",
      headers: { "Content-Type": "application/json; charset=utf-8" },
      body: JSON.stringify(payload)
    }).then(function(){
      toast("Đã lưu & gửi thông báo", "success");

      // Mark clean after successful save
      isDirty = false;
      if (window.tinymce && window.tinymce.activeEditor) {
        var ed = window.tinymce.activeEditor;
        if (ed.setDirty) ed.setDirty(false);
        if (ed.undoManager && ed.undoManager.clear) ed.undoManager.clear();
      }
    }).catch(function(err){
      console.error("Save raw:", err._raw || err);
      toast("Save failed: " + err.message, "error");
    }).finally(function(){
      if (btnSave) { btnSave.disabled = false; btnSave.classList.remove("loading"); }
    });
  }
  if (btnSave) btnSave.addEventListener("click", doSave);

  if (btnExport) btnExport.addEventListener("click", function(){
    // Intentional navigation -> don't warn
    isDirty = false;
    var base = (window.MEETING_ENDPOINT_BASE || "./");
    var url  = base + "project_tab_meetings_detail.php?ajax=export_doc&meeting_id=" + encodeURIComponent(MEETING_ID);
    window.location.href = url;
  });

  // ---------- Init ----------
  function init(){
    return loadAll().then(function(){
      if (editorWrap) editorWrap.classList.add("editor-a4");
      ensureToolbarHostAdjacent();
      var initialHTML = window.getEditorHTML();
      return loadTiny().then(function(){ initTiny(initialHTML); });
    });
  }

  init().catch(function(e){
    console.error(e);
    toast("Load failed: " + e.message, "error");
  });
})();