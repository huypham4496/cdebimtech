(function(){
  const root = document.querySelector('.mtg-detail-wrap');
  if (!root) return;
  const editor = document.getElementById('editor');
  const toolbar = document.getElementById('editor-toolbar');
  const btnSave = document.getElementById('btn-save');
  const btnBack = document.getElementById('btn-back');
  const externals = document.getElementById('att-externals');

  toolbar.addEventListener('click', (e)=>{
    const btn = e.target.closest('button');
    if (!btn) return;
    const cmd = btn.getAttribute('data-cmd');
    const val = btn.getAttribute('data-value') || null;
    if (cmd){
      document.execCommand(cmd, false, val);
      editor.focus();
    } else if (btn.id === 'btn-insert-table'){
      const rows = parseInt(prompt('Rows?', '2'), 10) || 2;
      const cols = parseInt(prompt('Columns?', '2'), 10) || 2;
      const table = document.createElement('table');
      table.border = '1';
      table.style.borderCollapse = 'collapse';
      for (let r=0;r<rows;r++){ 
        const tr = document.createElement('tr');
        for (let c=0;c<cols;c++){ 
          const td = document.createElement('td'); td.style.padding='6px'; td.textContent = ' '; tr.appendChild(td);
        }
        table.appendChild(tr);
      }
      document.getSelection().getRangeAt(0).insertNode(table);
    }
  });

  btnBack.addEventListener('click', ()=>{
    if (confirm('Leave this page? Unsaved changes will be lost.')){
      window.location.href = (window.CDE && CDE.detail && CDE.detail.backUrl) ? CDE.detail.backUrl : 'javascript:history.back()';
    }
  });

  btnSave.addEventListener('click', ()=>{
    const content_html = editor.innerHTML;
    const memberIds = Array.from(document.querySelectorAll('.att-chk:checked')).map(el=>el.value).join(',');
    const exts = (externals.value || '').trim();

    fetch(CDE.detail.saveUrl, {
      method:'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body: new URLSearchParams({ content_html, members: memberIds, externals: exts }).toString()
    }).then(r=>r.json()).then(res=>{
      if (!res.ok){ alert(res.error || 'Save failed'); return; }
      alert('Saved successfully and notifications queued.');
    }).catch(()=> alert('Save failed'));
  });
})();