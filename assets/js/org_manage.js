/* assets/js/org_manage.js (reload-after-success)
 * - Nút bấm/checkbox sẽ gọi PHP qua fetch và **reload trang** ngay khi thành công.
 * - Ngăn trang chuyển hướng sang JSON bằng preventDefault + type="button".
 */

(function () {
  'use strict';

  const ENDPOINT = '/pages/organization_manage.php';
  const qs  = (sel, root = document) => root.querySelector(sel);
  const qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  function getCSRFToken() {
    const meta = qs('meta[name="csrf-token"]');
    if (meta && meta.content) return meta.content;
    const input = qs('input[name="csrf_token"]');
    return input ? input.value : null;
  }

  async function postForm(params) {
    const headers = {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    };
    const csrf = getCSRFToken();
    if (csrf && !('csrf_token' in params)) params.csrf_token = csrf;

    const body = new URLSearchParams();
    Object.entries(params).forEach(([k,v]) => {
      if (v !== undefined && v !== null) body.append(k, String(v));
    });

    const res = await fetch(ENDPOINT, {
      method: 'POST',
      headers,
      body: body.toString(),
      credentials: 'same-origin'
    });

    const text = await res.text();
    try {
      const data = JSON.parse(text);
      if (!res.ok || data?.ok === false) {
        throw new Error(data?.msg || `HTTP ${res.status}`);
      }
      return data;
    } catch (e) {
      const msg = text && text.length < 400 ? text : 'Server returned non-JSON.';
      throw new Error(`HTTP ${res.status} – ${msg}`);
    }
  }

  function preventNavigate(ev) {
    // Chặn form submit/link mặc định
    if (ev) { ev.preventDefault(); ev.stopPropagation(); }
  }

  async function onToggleOrgShareClick(ev) {
    preventNavigate(ev);
    const btn = ev.currentTarget;
    // Đảm bảo không phải submit form
    if (btn.type && btn.type.toLowerCase() === 'submit') btn.type = 'button';
    const orgId = btn.dataset.orgId || btn.getAttribute('data-org-id');
    if (!orgId) return;
    btn.disabled = true;
    try {
      await postForm({ action: 'toggle_share', organization_id: orgId });
      // Thành công -> reload trang để lấy dữ liệu mới
      window.location.reload();
    } catch (e) {
      btn.disabled = false;
      alert(e.message || e);
    }
  }

  async function onToggleOrgShareCheckbox(ev) {
    preventNavigate(ev);
    const cb = ev.currentTarget;
    const orgId = cb.dataset.orgId;
    const target = cb.checked ? 1 : 0;
    cb.disabled = true;
    try {
      await postForm({
        action: 'toggle_share',
        organization_id: orgId,
        share_subscription: target
      });
      window.location.reload();
    } catch (e) {
      cb.checked = !target;
      cb.disabled = false;
      alert(e.message || e);
    }
  }

  async function onToggleMemberShare(ev) {
    preventNavigate(ev);
    const el = ev.currentTarget;
    const memberId = el.dataset.memberId;
    const orgId = el.dataset.orgId;
    if (!orgId || !memberId) return;
    // Nếu là button submit trong form, đổi sang button thường
    if (el.tagName === 'BUTTON' && el.type && el.type.toLowerCase() === 'submit') el.type = 'button';

    // Xác định target
    let target;
    if (el.tagName === 'INPUT' && el.type === 'checkbox') {
      target = el.checked ? 1 : 0;
    } else {
      const now = el.getAttribute('aria-pressed') === 'true' || el.classList.contains('is-on');
      target = now ? 0 : 1;
    }

    el.disabled = true;
    try {
      await postForm({
        action: 'toggle_member_share',
        organization_id: orgId,
        member_id: memberId,
        is_shared: target
      });
      window.location.reload();
    } catch (e) {
      // Revert nếu là checkbox
      if (el.tagName === 'INPUT' && el.type === 'checkbox') {
        el.checked = !Boolean(target);
      }
      el.disabled = false;
      alert(e.message || e);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    // Button toggle org share
    qsa('button[data-action="toggle-org-share"]').forEach(btn => {
      // Bảo đảm là button thường, tránh submit form
      if (!btn.type || btn.type.toLowerCase() === 'submit') btn.type = 'button';
      btn.addEventListener('click', onToggleOrgShareClick);
    });

    // Checkbox toggle org share (nếu còn)
    qsa('input.toggle-org-share[type="checkbox"]').forEach(cb => {
      cb.addEventListener('change', onToggleOrgShareCheckbox);
    });

    // Member share (checkbox hoặc button)
    qsa('.toggle-member-share').forEach(el => {
      const evt = (el.tagName === 'INPUT' ? 'change' : 'click');
      if (el.tagName === 'BUTTON' && (!el.type || el.type.toLowerCase() === 'submit')) el.type = 'button';
      el.addEventListener(evt, onToggleMemberShare);
    });
  });

})();
