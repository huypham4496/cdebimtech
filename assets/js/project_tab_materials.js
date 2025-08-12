// assets/js/project_tab_materials.js
(function () {
  const $ = (sel, ctx) => (ctx || document).querySelector(sel);
  const $$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));

  const root = $('#materials-tab');
  if (!root) return;

  const modeSelect = $('#mtl-mode');
  const searchInput = $('#mtl-search');
  const tblIn = $('#mtl-table-in');
  const tblOut = $('#mtl-table-out');

  const modal = $('#mtl-modal');
  const modalTitle = $('#mtl-modal-title');
  const modalClose = $('#mtl-modal-close');
  const form = $('#mtl-form');
  const actionInput = $('#mtl-action');
  const idInput = $('#mtl-id');

  const f = {
    name: $('#mtl-name'),
    code: $('#mtl-code'),
    supplier: $('#mtl-supplier'),
    warehouse: $('#mtl-warehouse'),
    qtyIn: $('#mtl-qty-in'),
    qtyOut: $('#mtl-qty-out'),
    unit: $('#mtl-unit'),
    receivedDate: $('#mtl-received-date'),
    outDate: $('#mtl-out-date'),
    content: $('#mtl-content')
  };

  const btnCreate = $('#mtl-btn-create');
  const btnCancel = $('#mtl-cancel');

  function setMode(mode) {
    modeSelect.value = mode;
    tblIn.style.display = mode === 'in' ? '' : 'none';
    tblOut.style.display = mode === 'out' ? '' : 'none';
    // Toggle form fields visibility
    $$('.mtl-group-in').forEach(el => el.style.display = mode === 'in' ? '' : 'none');
    $$('.mtl-group-out').forEach(el => el.style.display = mode === 'out' ? '' : 'none');
  }

  modeSelect.addEventListener('change', () => setMode(modeSelect.value));
  setMode('in');

  // Simple search filter
  function applySearch() {
    const q = (searchInput.value || '').toLowerCase().trim();
    const visibleTable = modeSelect.value === 'in' ? tblIn : tblOut;
    $$('.mtl-row', visibleTable).forEach(tr => {
      const name = (tr.getAttribute('data-name') || '').toLowerCase();
      const warehouse = (tr.getAttribute('data-warehouse') || '').toLowerCase();
      const person = (tr.getAttribute('data-person') || '').toLowerCase();
      const content = (tr.getAttribute('data-content') || '').toLowerCase();
      const hay = [name, warehouse, person, content].join(' ');
      tr.style.display = hay.includes(q) ? '' : 'none';
    });
  }
  searchInput.addEventListener('input', applySearch);

  // Modal helpers
  function openModal(title) {
    modalTitle.textContent = title;
    modal.style.display = 'block';
  }
  function closeModal() {
    modal.style.display = 'none';
    form.reset();
    idInput.value = '';
    actionInput.value = '';
  }
  modalClose.addEventListener('click', closeModal);
  btnCancel.addEventListener('click', closeModal);
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
  });
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });

  // Create
  btnCreate.addEventListener('click', () => {
    const mode = modeSelect.value;
    const now = new Date().toISOString().slice(0,10); // yyyy-mm-dd

    if (mode === 'in') {
      actionInput.value = 'create_in';
      f.qtyIn.required = true; f.qtyOut.required = false;
      f.receivedDate.value = now;
    } else {
      actionInput.value = 'create_out';
      f.qtyOut.required = true; f.qtyIn.required = false;
      f.outDate.value = now;
    }
    setMode(mode); // ensure correct fields shown
    openModal('Create');
  });

  // Edit IN
  $$('.mtl-edit-in').forEach(a => {
    a.addEventListener('click', (e) => {
      e.preventDefault();
      setMode('in');
      actionInput.value = 'update_in';
      idInput.value = a.dataset.id || '';
      f.name.value = a.dataset.name || '';
      f.code.value = a.dataset.code || '';
      f.supplier.value = a.dataset.supplier || '';
      f.warehouse.value = a.dataset.warehouse || '';
      f.qtyIn.value = a.dataset.qty_in || '';
      f.unit.value = a.dataset.unit || '';
      f.receivedDate.value = a.dataset.received_date || '';
      f.qtyIn.required = true; f.qtyOut.required = false;
      openModal('Edit');
    });
  });

  // Edit OUT
  $$('.mtl-edit-out').forEach(a => {
    a.addEventListener('click', (e) => {
      e.preventDefault();
      setMode('out');
      actionInput.value = 'update_out';
      idInput.value = a.dataset.id || '';
      f.name.value = a.dataset.name || '';
      f.code.value = a.dataset.code || '';
      f.qtyOut.value = a.dataset.qty_out || '';
      f.unit.value = a.dataset.unit || '';
      f.content.value = a.dataset.content || '';
      f.outDate.value = a.dataset.out_date || '';
      f.qtyOut.required = true; f.qtyIn.required = false;
      openModal('Edit');
    });
  });

})();
