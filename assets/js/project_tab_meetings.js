(function(){
  function onReady(fn){ if(document.readyState!=='loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
  function esc(s){ return (s==null?'':String(s)).replace(/[&<>"']/g, function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[c];}); }
  function fmtDate(d){ if(!d) return ''; var x=new Date(d); return isNaN(x)? String(d) : x.toLocaleString(); }
  function log(){ try{ console.log.apply(console, ['[Meetings]'].concat([].slice.call(arguments))); }catch(e){} }
  function warn(){ try{ console.warn.apply(console, ['[Meetings]'].concat([].slice.call(arguments))); }catch(e){} }
  function error(){ try{ console.error.apply(console, ['[Meetings]'].concat([].slice.call(arguments))); }catch(e){} }

  // Element.closest polyfill (old browsers)
  if (!Element.prototype.closest) {
    Element.prototype.closest = function(selector) {
      var el = this; while (el){ if (el.matches && el.matches(selector)) return el; el = el.parentElement; } return null;
    };
  }

  function deriveApiUrl(){
    if (window.CDE && CDE.meetingsApiUrl) return CDE.meetingsApiUrl;
    // Try from current script path: /assets/js/... -> /pages/partials/project_tab_meetings.php
    var scripts = document.getElementsByTagName('script');
    for (var i=0;i<scripts.length;i++){
      var src = scripts[i].getAttribute('src') || '';
      if (src.indexOf('project_tab_meetings.js') !== -1){
        try {
          var a=document.createElement('a'); a.href=src;
          return a.pathname.replace(/\/assets\/js\/.*$/i, '/pages/partials/project_tab_meetings.php');
        } catch(e){}
      }
    }
    // Fallback: guess from current path (…/pages/*)
    try {
      var here = location.pathname;
      var base = here.replace(/\/pages\/.*/, '');
      return base + '/pages/partials/project_tab_meetings.php';
    } catch(e){}
    return '/pages/partials/project_tab_meetings.php';
  }

  function safeJson(r, url){
    var ct = (r.headers && r.headers.get && r.headers.get('content-type')) || '';
    if (ct.indexOf('application/json') !== -1) return r.json();
    return r.text().then(function(t){
      var txt = (t||'').trim().replace(/^\uFEFF/, ''); // strip BOM
      try { return JSON.parse(txt); }
      catch(e){ error('Non-JSON response', url, 'CT:', ct, 'Body:', txt.slice(0,1000)); throw new Error('Non-JSON response'); }
    });
  }

  onReady(function init(){
    var root = document.getElementById('cde-meetings') || document.querySelector('.meetings-wrapper');
    if (!root){ warn('Wrapper not found'); return; }

    var projectId = root.getAttribute('data-project-id')
                  || (document.getElementById('project_id') && document.getElementById('project_id').value)
                  || (new URLSearchParams(location.search).get('project_id') || '');
    if (!projectId) warn('Missing project_id');

    var tbody = document.getElementById('meetings-tbody') || root.querySelector('tbody');
    var btnNew = document.getElementById('btn-new-meeting') || root.querySelector('.btn-new-meeting,[data-action="new-meeting"]');
    var modal = document.getElementById('mtg-modal') || root.querySelector('.mtg-modal,#meeting-modal');
    var btnSave = document.getElementById('mtg-save') || root.querySelector('#mtg-save,.btn-save-meeting');

    var titleEl = document.getElementById('mtg-title') || root.querySelector('#mtg-title,[name="title"]');
    var startEl = document.getElementById('mtg-start') || root.querySelector('#mtg-start,[name="start_at"]');
    var locEl   = document.getElementById('mtg-location') || root.querySelector('#mtg-location,[name="location"]');
    var linkEl  = document.getElementById('mtg-online') || root.querySelector('#mtg-online,[name="online_link"]');

    var API_URL = deriveApiUrl();
    window._MEETINGS_DEBUG = { projectId: projectId, API_URL: API_URL };
    log('Init', window._MEETINGS_DEBUG);

    function api(action, data, method){
      method = method || 'GET';
      if (!projectId){ if (tbody) tbody.innerHTML = '<tr><td colspan="7">Missing project id.</td></tr>'; return Promise.reject(new Error('Missing project id')); }
      var url = API_URL + '?action=' + encodeURIComponent(action) + '&project_id=' + encodeURIComponent(projectId);
      var opts = { method: method, credentials: 'same-origin' };
      if (method === 'POST'){
        var body = new URLSearchParams();
        body.set('project_id', projectId);
        if (data){ for (var k in data){ if (Object.prototype.hasOwnProperty.call(data,k)) body.set(k, data[k]); } }
        opts.headers = { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' };
        opts.body = body.toString();
      }
      log('Fetch', url, opts);
      return fetch(url, opts).then(function(r){ return safeJson(r, url); });
    }

    function render(rows){
      if (!tbody) return;
      if (!rows || !rows.length){ tbody.innerHTML = '<tr><td colspan="7">No meetings yet.</td></tr>'; return; }
      var html = '';
      for (var i=0;i<rows.length;i++){
        var row = rows[i] || {};
        var title = esc(row.title);
        var creator = esc(row.creator_name || row.creator || '');
        var createdTxt = fmtDate(row.created_at);
        var startTxt = fmtDate(row.start_at || row.start_time);
        var link = row.online_link || row.online_url || '';
        var onlineHtml = link ? '<a href="'+esc(link)+'" target="_blank" rel="noopener">Join</a>' : '<span class="muted">N/A</span>';
        var locationTxt = esc(row.location);
        var id = row.id;
        var detail = (window.CDE && CDE.meetingDetailUrl) ? (CDE.meetingDetailUrl + '?project_id=' + encodeURIComponent(projectId) + '&id=' + encodeURIComponent(id)) : '#';
        html += '<tr data-id="'+esc(id)+'">'
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
        if (!res || res.ok !== true){
          var rows = (res && (res.rows||res.items)) || [];
          if (rows.length){ render(rows); return; }
          tbody.innerHTML = '<tr><td colspan="7">' + esc((res && (res.error||res.message)) || 'Failed to load.') + '</td></tr>';
          return;
        }
        render(res.rows || res.items || []);
      }).catch(function(err){
        error('Load failed', err);
        tbody.innerHTML = '<tr><td colspan="7">Failed to load.</td></tr>';
      });
    }

    function showModal(show){
      if (!modal) return;
      if (show){ modal.hidden = false; modal.style.display = 'flex'; try{ titleEl && titleEl.focus(); }catch(e){} }
      else { modal.hidden = true; modal.style.display = 'none'; }
    }

    function doSave(){
      var title = (titleEl && titleEl.value || '').trim();
      var start_at = startEl && startEl.value || (root.querySelector('[name="start_at"]') && root.querySelector('[name="start_at"]').value) || '';
      var location = (locEl && locEl.value || (root.querySelector('[name="location"]') && root.querySelector('[name="location"]').value) || '').trim();
      var online_link = (linkEl && linkEl.value || (root.querySelector('[name="online_link"]') && root.querySelector('[name="online_link"]').value) || '').trim();
      if (!title){ alert('Title is required'); return; }
      if (!start_at){ alert('Start time is required'); return; }
      api('create', { title: title, start_at: start_at, location: location, online_link: online_link }, 'POST')
        .then(function(res){
          log('Create response', res);
          if (!res || res.ok !== true){ alert((res && (res.message||res.error)) || 'Failed to save'); return; }
          if (titleEl) titleEl.value=''; if (startEl) startEl.value=''; if (locEl) locEl.value=''; if (linkEl) linkEl.value='';
          showModal(false); load();
        }).catch(function(err){ error('Create failed', err); alert('Failed to save'); });
    }

    // Direct binds
    if (btnNew) btnNew.addEventListener('click', function(){ showModal(true); });
    if (btnSave) btnSave.addEventListener('click', function(e){ e.preventDefault(); doSave(); });
    if (modal){
      var closeBtn = modal.querySelector('.mtg-close'), cancelBtn = modal.querySelector('.mtg-cancel');
      if (closeBtn) closeBtn.addEventListener('click', function(){ showModal(false); });
      if (cancelBtn) cancelBtn.addEventListener('click', function(){ showModal(false); });
    }

    // Delegation fallback
    document.addEventListener('click', function(e){
      var t=e.target;
      if (t.closest && t.closest('#btn-new-meeting,.btn-new-meeting,[data-action="new-meeting"]')){ showModal(true); }
      if (t.closest && t.closest('#mtg-save,.btn-save-meeting')){ e.preventDefault(); doSave(); }
      if (t.closest && t.closest('.mtg-close,.mtg-cancel')){ showModal(false); }
    });

    // Start
    load();
  });
})();