(function(){
  function onReady(fn){ if (document.readyState!=='loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
  function log(){ try{ (console && console.log).apply(console, ['[Meetings]'].concat([].slice.call(arguments))); }catch(e){} }
  function warn(){ try{ (console && console.warn).apply(console, ['[Meetings]'].concat([].slice.call(arguments))); }catch(e){} }
  function error(){ try{ (console && console.error).apply(console, ['[Meetings]'].concat([].slice.call(arguments))); }catch(e){} }

  function deriveApiUrl(){
    if (window.CDE && CDE.meetingsApiUrl){ return CDE.meetingsApiUrl; }
    var scripts = document.getElementsByTagName('script');
    for (var i=0;i<scripts.length;i++){
      var src = scripts[i].getAttribute('src') || '';
      if (src.indexOf('project_tab_meetings.js') !== -1){
        try{
          var a = document.createElement('a'); a.href = src;
          var path = a.pathname.replace(/\/assets\/js\/.*$/i, '/pages/partials/project_tab_meetings.php');
          return path;
        }catch(e){}
      }
    }
    // Fallback: guess from current path
    try{
      var here = location.pathname;
      var base = here.replace(/\/pages\/.*/, '');
      return base + '/pages/partials/project_tab_meetings.php';
    }catch(e){}
    return '/pages/partials/project_tab_meetings.php';
  }

  function esc(s){ return (s==null?'':String(s)).replace(/[&<>"']/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[c];}); }
  function fmtDate(d){
    if (!d) return '';
    var dt = new Date(d);
    return isNaN(dt) ? String(d) : dt.toLocaleString();
  }

  onReady(function init(){
    var root = document.querySelector('.meetings-wrapper');
    if (!root){ warn('Root .meetings-wrapper not found'); return; }

    var projectId = root.getAttribute('data-project-id') || (new URLSearchParams(location.search).get('project_id') || '');
    var tbody = document.getElementById('meetings-tbody') || (root.querySelector('tbody') || null);
    var btnNew = document.getElementById('btn-new-meeting');
    var modal = document.getElementById('mtg-modal');
    var btnSave = document.getElementById('mtg-save');

    var titleEl = document.getElementById('mtg-title');
    var startEl = document.getElementById('mtg-start');
    var locEl = document.getElementById('mtg-location');
    var linkEl = document.getElementById('mtg-online');

    var API_URL = deriveApiUrl();
    window._MEETINGS_DEBUG = { projectId: projectId, API_URL: API_URL };
    log('Init', window._MEETINGS_DEBUG);

    function api(action, data, method){
      method = method || 'GET';
      var url = API_URL + '?action=' + encodeURIComponent(action) + '&project_id=' + encodeURIComponent(projectId||'');
      var opts = { method: method, credentials: 'same-origin' };
      if (method === 'POST'){
        var body = new URLSearchParams();
        body.set('project_id', projectId||'');
        if (data){ for (var k in data){ if (Object.prototype.hasOwnProperty.call(data,k)) body.set(k, data[k]); } }
        opts.headers = { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' };
        opts.body = body.toString();
      }
      log('Fetch', url, opts);
      return fetch(url, opts).then(function(r){
        var ct = r.headers.get('content-type') || '';
        if (ct.indexOf('application/json') === -1){
          return r.text().then(function(t){
            error('Non-JSON response', ct, 'Body:', t);
            throw new Error('Non-JSON response');
          });
        }
        return r.json();
      });
    }

    function render(rows){
      if (!tbody) return;
      if (!rows || !rows.length){
        tbody.innerHTML = '<tr><td colspan="7">No meetings yet.</td></tr>';
        return;
      }
      var html = '';
      for (var i=0;i<rows.length;i++){
        var row = rows[i] || {};
        var title = esc(row.title);
        var creator = esc(row.creator_name);
        var createdTxt = fmtDate(row.created_at);
        var startTxt = fmtDate(row.start_at || row.start_time);
        var link = row.online_link || row.online_url || '';
        var onlineHtml = link ? '<a href="'+esc(link)+'" target="_blank" rel="noopener">Join</a>' : '<span class="muted">N/A</span>';
        var locationTxt = esc(row.location);
        var id = row.id;
        var detail = (window.CDE && CDE.meetingDetailUrl) ? (CDE.meetingDetailUrl + '?project_id=' + encodeURIComponent(projectId||'') + '&id=' + encodeURIComponent(id||'')) : '#';
        html += '<tr>'
          + '<td class="col-title"><a class="mtg-title-link" href="'+detail+'">'+title+'</a></td>'
          + '<td class="col-creator">'+creator+'</td>'
          + '<td class="col-created">'+createdTxt+'</td>'
          + '<td class="col-start">'+startTxt+'</td>'
          + '<td class="col-link">'+onlineHtml+'</td>'
          + '<td class="col-location">'+locationTxt+'</td>'
          + '<td class="col-action"><a class="btn-link" href="'+detail+'">View</a></td>'
          + '</tr>';
      }
      tbody.innerHTML = html;
    }

    function load(){
      if (!tbody) return;
      tbody.innerHTML = '<tr><td colspan="7">Loading...</td></tr>';
      api('list').then(function(res){
        log('List response', res);
        if (!res || res.ok !== true){ tbody.innerHTML = '<tr><td colspan="7">' + esc((res && (res.error||res.message)) || 'Failed to load.') + '</td></tr>'; return; }
        render(res.rows);
      }).catch(function(err){
        error('Load failed', err);
        tbody.innerHTML = '<tr><td colspan="7">Failed to load.</td></tr>';
      });
    }

    function showModal(show){
      if (!modal) return;
      try {
        if (show){
          modal.hidden = false;
          modal.style.display = 'flex';
          if (titleEl) setTimeout(function(){ try{ titleEl.focus(); }catch(e){} }, 0);
        }else{
          modal.hidden = true;
          modal.style.display = 'none';
        }
      } catch(e){}
    }

    // Direct bindings
    if (btnNew) btnNew.addEventListener('click', function(){ showModal(true); });
    if (modal){
      var closeBtn = modal.querySelector('.mtg-close');
      var cancelBtn = modal.querySelector('.mtg-cancel');
      if (closeBtn) closeBtn.addEventListener('click', function(){ showModal(false); });
      if (cancelBtn) cancelBtn.addEventListener('click', function(){ showModal(false); });
    }

    // Delegation fallback (if buttons are rendered later)
    document.addEventListener('click', function(e){
      var t = e.target;
      if (t.closest && t.closest('#btn-new-meeting')){ showModal(true); }
      if (t.closest && t.closest('.mtg-close')){ showModal(false); }
      if (t.closest && t.closest('.mtg-cancel')){ showModal(false); }
      if (t.closest && t.closest('#mtg-save')){
        e.preventDefault();
        if (!btnSave) btnSave = t.closest('#mtg-save');
        doSave();
      }
    });

    function doSave(){
      var title = (titleEl && titleEl.value || '').trim();
      var start_at = startEl && startEl.value || '';
      var location = (locEl && locEl.value || '').trim();
      var online_link = (linkEl && linkEl.value || '').trim();
      if (!title){ alert('Title is required'); return; }
      if (!start_at){ alert('Start time is required'); return; }
      api('create', { title: title, start_at: start_at, location: location, online_link: online_link }, 'POST')
        .then(function(res){
          log('Create response', res);
          if (!res || res.ok !== true){ alert((res && (res.message || res.error)) || 'Failed to save'); return; }
          if (titleEl) titleEl.value=''; if (startEl) startEl.value=''; if (locEl) locEl.value=''; if (linkEl) linkEl.value='';
          showModal(false);
          load();
        }).catch(function(err){
          error('Create failed', err);
          alert('Failed to save');
        });
    }

    if (btnSave) btnSave.addEventListener('click', function(e){ e.preventDefault(); doSave(); });

    // Initial load
    load();

    // Re-attach after a short delay in case DOM was injected late
    setTimeout(function(){
      if (!btnNew){ btnNew = document.getElementById('btn-new-meeting'); if (btnNew) btnNew.addEventListener('click', function(){ showModal(true); }); }
      if (!btnSave){ btnSave = document.getElementById('mtg-save'); if (btnSave) btnSave.addEventListener('click', function(e){ e.preventDefault(); doSave(); }); }
    }, 300);
  });
})();