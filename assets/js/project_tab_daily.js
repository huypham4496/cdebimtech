// assets/js/project_tab_daily.js

(async function () {
  const root = document.getElementById('daily-logs-root');
  if (!root) return;

  const projectId = root.dataset.projectId;
  const currentUser = root.dataset.currentUser;

  const table = document.getElementById('daily-logs-table').querySelector('tbody');
  const search = document.getElementById('daily-search');
  const btnCreate = document.getElementById('btn-create-daily');
  const btnExport = document.getElementById('btn-export');

  function statusLabel(status) {
    return status == 1 ? '<span class="badge badge-success">Approved</span>' : '<span class="badge badge-muted">Pending</span>';
  }

  function renderRow(log) {
    return `<tr>
      <td>${log.code}</td>
      <td>${log.entry_date}</td>
      <td>${log.name}</td>
      <td>${log.first_name || ''} ${log.last_name || ''}</td>
      <td>${log.group_name || ''}</td>
      <td>${statusLabel(log.is_approved)}</td>
      <td>
        <button class="btn btn-sm btn-approve" data-id="${log.id}" ${log.is_approved == 1 ? 'disabled' : ''}>
          <i class="fas fa-check"></i>
        </button>
      </td>
    </tr>`;
  }

  async function loadList() {
    const q = search.value.trim();
    const res = await fetch(`/pages/partials/project_tab_daily.php?action=list&project_id=${projectId}&q=${encodeURIComponent(q)}`);
    const data = await res.json();
    table.innerHTML = '';
    if (data.ok && data.items.length) {
      data.items.forEach(log => {
        table.insertAdjacentHTML('beforeend', renderRow(log));
      });
    } else {
      table.innerHTML = '<tr><td colspan="7">No logs found</td></tr>';
    }
  }

  async function approveLog(id) {
    const formData = new FormData();
    formData.append('id', id);
    const res = await fetch(`/pages/partials/project_tab_daily.php?action=approve&project_id=${projectId}`, {
      method: 'POST',
      body: formData
    });
    const json = await res.json();
    alert(json.message);
    if (json.ok) loadList();
  }

  table.addEventListener('click', e => {
    if (e.target.closest('.btn-approve')) {
      const id = e.target.closest('.btn-approve').dataset.id;
      approveLog(id);
    }
  });

  search.addEventListener('input', () => loadList());
  btnExport.addEventListener('click', () => {
    const q = search.value.trim();
    location.href = `/pages/partials/project_tab_daily.php?action=export&project_id=${projectId}&q=${encodeURIComponent(q)}`;
  });

  btnCreate.addEventListener('click', () => alert('Feature not implemented yet.'));

  loadList();
})();
