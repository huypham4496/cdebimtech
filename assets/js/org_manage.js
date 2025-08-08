
/**
 * organization_manage.js
 * Handles Share Plan toggles (master + per-member) with robust form-POSTs
 * Compatible with PHP form handlers that expect $_POST (no JSON payloads).
 *
 * Expected HTML hooks:
 *  - Master toggle: <input type="checkbox" id="sharePlanToggle" data-org-id="...">
 *  - Member toggle(s): <input type="checkbox" class="member-share-toggle" data-org-id="..." data-member-id="..." data-user-id="...">
 *  - Optional CSRF:
 *      <meta name="csrf-token" content="..."> OR
 *      <input type="hidden" name="csrf_token" value="..."> anywhere on the page
 *
 * PHP handlers (examples — adjust to your app):
 *  - action=toggle_share_subscription  -> toggles organizations.share_subscription
 *      required: org_id, share_subscription (0|1)
 *  - action=toggle_member_share -> toggles organization_members.is_shared
 *      required: org_id, member_id or user_id, is_shared (0|1)
 */

(function () {
  const byId = (id) => document.getElementById(id);
  const qs = (sel, root=document) => root.querySelector(sel);
  const qsa = (sel, root=document) => Array.from(root.querySelectorAll(sel));

  function getCSRF() {
    const meta = qs('meta[name="csrf-token"]');
    if (meta && meta.content) return meta.content;
    const hidden = qs('input[name="csrf_token"]');
    if (hidden && hidden.value) return hidden.value;
    return '';
  }

  function postForm(url, dataObj) {
    const body = new URLSearchParams();
    Object.entries(dataObj).forEach(([k,v]) => {
      if (v !== undefined && v !== null) body.append(k, String(v));
    });
    const headers = {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
    };
    return fetch(url, {
      method: 'POST',
      headers,
      body
    }).then(async (res) => {
      const txt = await res.text();
      let json;
      try { json = JSON.parse(txt); } catch (_) {
        // Standardize error shape if backend returns plain text
        json = { ok: res.ok, raw: txt };
      }
      return json;
    });
  }

  function toast(msg, type='info') {
    // minimal toast; replace with your UI
    console.log(`[${type.toUpperCase()}]`, msg);
  }

  function restoreToggle(input, prevChecked) {
    // avoid firing the listener again when reverting
    input.dataset._reverting = '1';
    input.checked = prevChecked;
    setTimeout(() => { delete input.dataset._reverting; }, 0);
  }

  // MASTER "Share Plan" toggle
  const master = byId('sharePlanToggle');
  if (master) {
    master.addEventListener('change', async (e) => {
      if (master.dataset._reverting === '1') return;

      const orgId = master.getAttribute('data-org-id');
      if (!orgId) {
        toast('Thiếu organization_id cho nút Share Plan.', 'error');
        restoreToggle(master, !master.checked);
        return;
      }
      const shareVal = master.checked ? 1 : 0;
      const csrf = getCSRF();

      const payload = {
        action: 'toggle_share_subscription',
        org_id: orgId,
        share_subscription: shareVal
      };
      if (csrf) payload.csrf_token = csrf;

      // IMPORTANT: post to the same PHP page so $_POST is populated
      const url = 'organization_manage.php';

      const prev = !master.checked;
      const res = await postForm(url, payload);
      if (!res || res.ok === false) {
        const msg = res && res.msg ? res.msg : (res && res.raw ? res.raw : 'Bad request');
        toast(`Không thể cập nhật Share Plan: ${msg}`, 'error');
        restoreToggle(master, prev);
        return;
      }

      // Optionally reflect server state if returned
      if (typeof res.share_subscription !== 'undefined') {
        const serverState = Number(res.share_subscription) === 1;
        if (serverState !== master.checked) restoreToggle(master, serverState);
      }

      toast(shareVal ? 'Đã bật chia sẻ gói cho tổ chức.' : 'Đã tắt chia sẻ gói cho tổ chức.', 'success');
    });
  }

  // Per-member share toggles
  qsa('.member-share-toggle').forEach((el) => {
    el.addEventListener('change', async () => {
      if (el.dataset._reverting === '1') return;

      const orgId = el.getAttribute('data-org-id');
      const memberId = el.getAttribute('data-member-id'); // preferred
      const userId = el.getAttribute('data-user-id');     // fallback in case PHP expects user_id
      const isShared = el.checked ? 1 : 0;
      const csrf = getCSRF();

      if (!orgId || (!memberId && !userId)) {
        toast('Thiếu org_id hoặc member_id/user_id cho nút chia sẻ thành viên.', 'error');
        restoreToggle(el, !el.checked);
        return;
      }

      const payload = {
        action: 'toggle_member_share',
        org_id: orgId,
        is_shared: isShared
      };
      if (memberId) payload.member_id = memberId;
      if (userId) payload.user_id = userId;
      if (csrf) payload.csrf_token = csrf;

      const url = 'organization_manage.php';
      const prev = !el.checked;
      const res = await postForm(url, payload);

      if (!res || res.ok === false) {
        const msg = res && res.msg ? res.msg : (res && res.raw ? res.raw : 'Bad request');
        toast(`Không thể cập nhật chia sẻ cho thành viên: ${msg}`, 'error');
        restoreToggle(el, prev);
        return;
      }

      // if server returns the canonical row value, respect it
      if (typeof res.is_shared !== 'undefined') {
        const serverState = Number(res.is_shared) === 1;
        if (serverState !== el.checked) restoreToggle(el, serverState);
      }

      toast(isShared ? 'Đã bật chia sẻ cho thành viên.' : 'Đã tắt chia sẻ cho thành viên.', 'success');
    });
  });

})();
