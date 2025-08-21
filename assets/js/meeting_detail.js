// File: assets/js/project_tab_meetings_detail.js
(function(){
  const $ = (s, c=document)=>c.querySelector(s);
  const $$ = (s, c=document)=>Array.from(c.querySelectorAll(s));

  const editor = $("#editor");
  const toolbar = $("#editor-toolbar");
  const fontSize = $("#font-size");
  const fontName = $("#font-name");
  const memberList = $("#member-list");
  const externalList = $("#external-list");

  function api(action, options={}){
    const u = new URL(window.location.href);
    u.searchParams.set('ajax', action);
    u.searchParams.set('meeting_id', window.MEETING_ID);
    return fetch(u.toString(), options);
  }

  function renderMemberCheckbox(u, checked){
    const label = document.createElement('label');
    label.className = 'member-item';
    label.innerHTML = `
      <input type="checkbox" value="${u.id}" ${checked ? 'checked':''}>
      <span class="name">${u.full_name || ''}</span>
      <span class="email">${u.email || ''}</span>
    `;
    return label;
  }
  function renderExternalRow(x={name:'',email:''}){
    const row = document.createElement('div');
    row.className = 'external-row';
    row.innerHTML = `
      <input type="text" class="ext-name" placeholder="Full name" value="${x.external_name || x.name || ''}">
      <input type="email" class="ext-email" placeholder="Email (optional)" value="${x.external_email || x.email || ''}">
      <button class="icon remove" title="Remove">&times;</button>
    `;
    row.querySelector('.remove').addEventListener('click', ()=>row.remove());
    return row;
  }
  function exec(cmd, val=null){ document.execCommand(cmd, false, val); editor.focus(); }
  function insertTable(r=3,c=3){
    const t=document.createElement('table'); t.className='ed-table';
    for(let i=0;i<r;i++){ const tr=document.createElement('tr');
      for(let j=0;j<c;j++){ const td=document.createElement('td'); td.innerHTML='&nbsp;'; tr.appendChild(td); }
      t.appendChild(tr);
    }
    const sel=window.getSelection(); if(!sel.rangeCount) { editor.appendChild(t); return; }
    const range=sel.getRangeAt(0); range.insertNode(t);
  }

  toolbar.addEventListener('click',(e)=>{
    const btn = e.target.closest('button'); if(!btn) return;
    const cmd = btn.dataset.cmd; if(!cmd) return;
    const val = btn.dataset.value || null;
    if (cmd==='foreColor' || cmd==='backColor') exec(cmd, val); else exec(cmd);
  });
  fontSize.addEventListener('change',()=>{ if(fontSize.value) exec('fontSize', fontSize.value); });
  fontName.addEventListener('change',()=>{ if(fontName.value) exec('fontName', fontName.value); });
  $("#btn-insert-table").addEventListener('click', ()=>insertTable(3,3));

  function loadAll(){
    api('load').then(r=>r.json()).then(j=>{
      if (j.error){ alert(j.error); return; }
      const m=j.meeting || {};
      $("#md-start-time").textContent = m.start_time || '—';
      $("#md-location").textContent = m.location || '—';
      const a=$("#md-online");
      if(m.online_link){ a.textContent=m.online_link; a.href=m.online_link; } else { a.textContent='—'; a.removeAttribute('href'); }
      $("#md-short").textContent = m.short_desc || '—';

      if (j.detail && j.detail.content_html) editor.innerHTML = j.detail.content_html;
      else editor.innerHTML = '<p><em>Ghi nội dung cuộc họp tại đây…</em></p>';

      memberList.innerHTML='';
      const checked = new Set((j.attendees||[]).filter(x=>!x.is_external).map(x=>String(x.user_id)));
      (j.members||[]).forEach(u=> memberList.appendChild(renderMemberCheckbox(u, checked.has(String(u.id)))) );

      externalList.innerHTML='';
      (j.attendees||[]).filter(x=>x.is_external).forEach(x=> externalList.appendChild(renderExternalRow(x)) );
      if (!externalList.children.length) externalList.appendChild(renderExternalRow());

      if (j.needs_migration) console.warn('Note table will be auto-created on save.');
    }).catch(()=> alert('Failed to load meeting.'));
  }

  $("#btn-add-external").addEventListener('click',()=> externalList.appendChild(renderExternalRow()) );

  $("#btn-save").addEventListener('click',()=>{
    const content_html = editor.innerHTML;
    const selected_user_ids = $$('input[type=checkbox]', memberList).filter(i=>i.checked).map(i=>parseInt(i.value,10));
    const external_participants = $$('.external-row', externalList).map(r=>({
      name: $('.ext-name', r).value.trim(),
      email: $('.ext-email', r).value.trim()
    }));
    api('save', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ content_html, selected_user_ids, external_participants })
    }).then(r=>r.json()).then(j=>{
      if (j.ok){ alert('Đã lưu và gửi thông báo (nếu có).'); loadAll(); }
      else alert(j.error || 'Save failed');
    }).catch(()=> alert('Save failed'));
  });

  $("#btn-export").addEventListener('click',()=>{
    const u = new URL(window.location.href);
    u.searchParams.set('ajax','export_doc');
    u.searchParams.set('meeting_id', window.MEETING_ID);
    window.location.href = u.toString();
  });

  loadAll();
})();
