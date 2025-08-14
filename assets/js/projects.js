/* projects.js — handles Edit modal & UX */
(function(){
  function qs(s,el){ return (el||document).querySelector(s); }
  function qsa(s,el){ return Array.from((el||document).querySelectorAll(s)); }

  const modal = qs('#editModal');
  const backdrop = qs('#editModal .modal-backdrop');
  const closeBtns = qsa('[data-close]', modal);
  const form = qs('#editModal form');

  function openModal(){
    modal.classList.add('show');
    modal.setAttribute('aria-hidden','false');
  }
  function closeModal(){
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden','true');
  }

  qsa('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      const data = btn.getAttribute('data-edit');
      if (!data) return;
      try {
        const p = JSON.parse(data);
        qs('#edit_id').value = p.id || '';
        qs('#edit_name').value = p.name || '';
        qs('#edit_status').value = p.status || 'active';
        qs('#edit_location').value = p.location || '';
        qs('#edit_tags').value = p.tags || '';
        qs('#edit_description').value = p.description || '';
        openModal();
      } catch (e){ console.error(e); }
    });
  });

  [backdrop, ...closeBtns].forEach(el => el.addEventListener('click', closeModal));

  // Close on ESC
  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape') closeModal();
  });
})();
