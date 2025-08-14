// assets/js/project_tab_meetings.js
(function(){
    const root = document.getElementById('meetings-tab');
    if (!root) return;
    const projectId = parseInt(root.dataset.projectId || '0', 10);
    const canCreate = root.dataset.canCreate === '1';

    // Utility
    function post(action, data, asForm = true) {
        const payload = new FormData();
        payload.append('action', action);
        for (const k in data) {
            if (Array.isArray(data[k])) {
                data[k].forEach(v => payload.append(k+'[]', v));
            } else {
                payload.append(k, data[k]);
            }
        }
        return fetch('project_tab_meetings.php?project_id='+projectId, {
            method: 'POST',
            body: payload,
            credentials: 'same-origin'
        }).then(r => {
            const ct = r.headers.get('Content-Type') || '';
            if (ct.includes('application/msword')) return r.text(); // export
            return r.json();
        });
    }

    function fmtDateTime(dt) {
        if (!dt) return '';
        const d = new Date(dt.replace(' ', 'T'));
        if (isNaN(d.getTime())) return dt;
        return d.toLocaleString();
    }

    // List view handlers
    const tbody = document.getElementById('mt-tbody');
    if (tbody) {
        const btnSearch = document.getElementById('mt-btn-search');
        const inpKw = document.getElementById('mt-keyword');
        const inpFrom = document.getElementById('mt-date-from');
        const inpTo = document.getElementById('mt-date-to');
        const btnCreate = document.getElementById('mt-btn-create');
        const modal = document.getElementById('mt-create-modal');
        const closeEls = modal ? modal.querySelectorAll('[data-close]') : [];

        function loadList() {
            tbody.innerHTML = `<tr><td colspan="5" class="loading">Loading...</td></tr>`;
            post('list', {
                keyword: inpKw.value.trim(),
                date_from: inpFrom.value,
                date_to: inpTo.value
            }).then(res => {
                if (!res.ok) throw new Error(res.error || 'Failed to load');
                if (res.items.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="5" class="empty">No meetings found.</td></tr>`;
                    return;
                }
                tbody.innerHTML = res.items.map(it => {
                    const viewUrl = `project_view.php?project_id=${projectId}&tab=meetings&meeting_id=${it.id}`;
                    const actions = [];
                    if (it.can_edit) {
                        actions.push(`<button class="danger" data-del="${it.id}">Delete</button>`);
                    }
                    actions.push(`<a href="${viewUrl}" class="link">View</a>`);
                    return `<tr>
                        <td><a href="${viewUrl}" class="title">${it.title}</a><div class="sub">${it.short_desc || ''}</div></td>
                        <td>${it.creator_name || ''}</td>
                        <td>${fmtDateTime(it.start_time || it.created_at)}</td>
                        <td>${it.location || ''}</td>
                        <td class="actions">${actions.join(' ')}</td>
                    </tr>`;
                }).join('');
            }).catch(err => {
                tbody.innerHTML = `<tr><td colspan="5" class="error">${err.message}</td></tr>`;
            });
        }

        btnSearch && btnSearch.addEventListener('click', loadList);
        loadList();

        // Create modal
        function openModal(){ if (!modal) return; modal.classList.remove('hidden'); }
        function closeModal(){ if (!modal) return; modal.classList.add('hidden'); }
        closeEls.forEach(el => el.addEventListener('click', closeModal));
        btnCreate && btnCreate.addEventListener('click', () => { if (canCreate) openModal(); });

        if (modal) {
            const btnSave = modal.querySelector('#mtc-save');
            const errEl = modal.querySelector('#mtc-error');
            btnSave && btnSave.addEventListener('click', () => {
                errEl.textContent = '';
                const title = modal.querySelector('#mtc-title').value.trim();
                const short_desc = modal.querySelector('#mtc-short').value.trim();
                const online_url = modal.querySelector('#mtc-online').value.trim();
                const location = modal.querySelector('#mtc-location').value.trim();
                const start_time = modal.querySelector('#mtc-start').value;

                if (!title) { errEl.textContent = 'Title is required.'; return; }
                post('create', { title, short_desc, online_url, location, start_time }).then(res => {
                    if (!res.ok) throw new Error(res.error || 'Create failed');
                    closeModal();
                    window.location.href = `project_view.php?project_id=${projectId}&tab=meetings&meeting_id=${res.id}`;
                }).catch(e => { errEl.textContent = e.message; });
            });

            // close modal when clicking outside
            modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
        }

        // Delete
        tbody.addEventListener('click', (e) => {
            const delId = e.target.closest('button')?.dataset?.del;
            if (delId) {
                if (!confirm('Delete this meeting?')) return;
                post('delete', { meeting_id: delId }).then(res => {
                    if (!res.ok) throw new Error(res.error || 'Delete failed');
                    loadList();
                }).catch(err => alert(err.message));
            }
        });
    }

    // Detail view handlers
    const detail = document.querySelector('.cde-meeting-detail');
    if (detail) {
        const meetingId = parseInt(detail.dataset.meetingId || '0', 10);
        const titleEl = document.getElementById('md-title');
        const startEl = document.getElementById('md-start');
        const locationEl = document.getElementById('md-location');
        const onlineEl = document.getElementById('md-online');
        const shortEl = document.getElementById('md-short');
        const editor = document.getElementById('md-editor');
        const err = document.getElementById('md-error');
        const btnSave = document.getElementById('md-save');
        const btnSaveNotify = document.getElementById('md-save-notify');
        const btnExport = document.getElementById('md-export');

        // Toolbar
        const toolbar = document.getElementById('md-toolbar');
        toolbar.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;
            const cmd = btn.dataset.cmd;
            if (cmd === 'createTable') {
                // simple 3x3 table
                const table = document.createElement('table');
                table.border = 1;
                for (let r=0;r<3;r++){
                    const tr = document.createElement('tr');
                    for (let c=0;c<3;c++){ const td=document.createElement('td'); td.innerHTML='&nbsp;'; tr.appendChild(td); }
                    table.appendChild(tr);
                }
                editor.focus();
                document.execCommand('insertHTML', false, table.outerHTML);
                return;
            }
            const val = btn.dataset.value || null;
            document.execCommand(cmd, false, val);
            editor.focus();
        });
        document.getElementById('md-font-size').addEventListener('change', (e)=>{
            document.execCommand('fontSize', false, e.target.value);
            editor.focus();
        });
        document.getElementById('md-color').addEventListener('input', (e)=>{
            document.execCommand('foreColor', false, e.target.value);
            editor.focus();
        });
        document.getElementById('md-bg').addEventListener('input', (e)=>{
            document.execCommand('hiliteColor', false, e.target.value);
            editor.focus();
        });

        function toDatetimeLocalValue(iso) {
            if (!iso) return '';
            const d = new Date(iso.replace(' ', 'T'));
            if (isNaN(d.getTime())) return '';
            const pad = (n)=> String(n).padStart(2,'0');
            return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        }

        function loadDetail() {
            post('get', { meeting_id: meetingId }).then(res => {
                if (!res.ok) throw new Error(res.error || 'Load failed');
                const m = res.meeting;
                titleEl.value = m.title || '';
                startEl.value = toDatetimeLocalValue(m.start_time || '');
                locationEl.value = m.location || '';
                onlineEl.value = m.online_url || '';
                shortEl.value = m.short_desc || '';
                editor.innerHTML = m.content || '';
                // participants
                const existing = new Set((res.participants || []).filter(x=>x.internal_user_id).map(x=>String(x.internal_user_id)));
                document.querySelectorAll('.mt-member').forEach(chk => {
                    chk.checked = existing.has(chk.value);
                });
                const externals = (res.participants || []).filter(x=>x.external_name).map(x=>x.external_name).join('\n');
                const extArea = document.getElementById('md-external');
                extArea.value = externals;
                const canEdit = !!res.can_edit;
                [titleEl,startEl,locationEl,onlineEl,shortEl,editor].forEach(el => el.disabled = !canEdit);
                btnSave.disabled = !canEdit;
                btnSaveNotify.disabled = !canEdit;
            }).catch(e => err.textContent = e.message);
        }
        loadDetail();

        function gatherAndSave(notify=false) {
            err.textContent = '';
            const data = {
                meeting_id: meetingId,
                title: titleEl.value.trim(),
                start_time: startEl.value,
                location: locationEl.value.trim(),
                online_url: onlineEl.value.trim(),
                short_desc: shortEl.value.trim(),
                content: editor.innerHTML
            };
            post('update', data).then(res => {
                if (!res.ok) throw new Error(res.error || 'Save failed');
                if (!notify) { alert('Saved'); return; }
                // participants
                const internal = Array.from(document.querySelectorAll('.mt-member:checked')).map(chk => chk.value);
                const externals = document.getElementById('md-external').value.split('\n').map(s=>s.trim()).filter(s=>s.length>0);
                return post('save_participants_and_notify', { meeting_id: meetingId, internal_user_ids: internal, external_names: externals });
            }).then(res => {
                if (!res) return;
                if (!res.ok) throw new Error(res.error || 'Notify failed');
                alert('Saved & notifications sent');
            }).catch(e => err.textContent = e.message);
        }

        btnSave && btnSave.addEventListener('click', ()=>gatherAndSave(false));
        btnSaveNotify && btnSaveNotify.addEventListener('click', ()=>gatherAndSave(true));

        btnExport && btnExport.addEventListener('click', () => {
            // export via POST then force download by creating a hidden form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'project_tab_meetings.php?project_id='+projectId;
            const a1 = document.createElement('input'); a1.type='hidden'; a1.name='action'; a1.value='export_word'; form.appendChild(a1);
            const a2 = document.createElement('input'); a2.type='hidden'; a2.name='meeting_id'; a2.value=meetingId; form.appendChild(a2);
            document.body.appendChild(form);
            form.submit();
            setTimeout(()=>form.remove(), 1000);
        });
    }
})();