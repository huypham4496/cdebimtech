/* assets/js/projects.js — UI behaviors & charts (vanilla Canvas) */
(function(){
  const BRAND = '#fca415';
  const BORDER = '#e5e7eb';
  const TEXT = '#0f172a';
  const MUTED = '#64748b';

  function easeOutCubic(t){ return 1 - Math.pow(1 - t, 3); }

  function fitCanvas(canvas){
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    const w = Math.max(100, rect.width || canvas.width || 300);
    const h = Math.max(100, rect.height || canvas.height || 300);
    canvas.width = Math.floor(w * dpr);
    canvas.height = Math.floor(h * dpr);
    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    return { ctx, w, h };
  }

  function drawDonut(canvas, percent, opts={}){
    percent = Math.max(0, Math.min(100, percent));
    const thickness = opts.thickness ?? 14;
    const bg = opts.bg ?? BORDER;
    const fg = opts.fg ?? BRAND;
    const label = opts.label ?? '';
    const showText = opts.showText ?? true;

    const { ctx, w, h } = fitCanvas(canvas);
    const r = Math.min(w, h) / 2 - thickness - 6;
    const cx = w/2, cy = h/2;

    ctx.clearRect(0, 0, w, h);

    // background ring
    ctx.beginPath();
    ctx.arc(cx, cy, r, 0, Math.PI*2);
    ctx.strokeStyle = bg;
    ctx.lineWidth = thickness;
    ctx.lineCap = 'round';
    ctx.stroke();

    // foreground arc
    const start = -Math.PI/2;
    const end = start + (percent/100) * Math.PI*2;
    ctx.beginPath();
    ctx.arc(cx, cy, r, start, end, false);
    ctx.strokeStyle = fg;
    ctx.lineWidth = thickness;
    ctx.lineCap = 'round';
    ctx.stroke();

    if (showText){
      ctx.fillStyle = TEXT;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.font = '600 16px system-ui, -apple-system, Segoe UI, Roboto, Arial';
      ctx.fillText(Math.round(percent) + '%', cx, cy);
      if (label){
        ctx.fillStyle = MUTED;
        ctx.font = '500 12px system-ui, -apple-system, Segoe UI, Roboto, Arial';
        ctx.fillText(label, cx, cy + 18);
      }
    }
  }

  function animateDonut(canvas, target, opts={}){
    const duration = opts.duration ?? 1200;
    const startTime = performance.now();
    function tick(now){
      const t = Math.min(1, (now - startTime)/duration);
      const p = easeOutCubic(t) * target;
      drawDonut(canvas, p, opts);
      if (t < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  function parseNumber(val){
    if (val == null) return NaN;
    if (typeof val === 'number') return val;
    const s = String(val).trim().replace(/,/g,'');
    const m = s.match(/^([\d.]+)\s*(b|kb|mb|gb|tb)?$/i);
    if (m){
      const n = parseFloat(m[1]);
      const unit = (m[2] || '').toLowerCase();
      const k = unit==='tb'? 1024**4 : unit==='gb'? 1024**3 : unit==='mb'?1024**2 : unit==='kb'?1024 : 1;
      return n * k;
    }
    const n = parseFloat(s);
    return isNaN(n) ? NaN : n;
  }

  function initStorageDonuts(){
    const nodes = document.querySelectorAll('canvas[data-donut]');
    nodes.forEach(canvas => {
      const used = parseNumber(canvas.dataset.used);
      const total = parseNumber(canvas.dataset.total);
      let percent = parseFloat(canvas.dataset.percent);
      if (isNaN(percent)){
        percent = (!isNaN(used) && !isNaN(total) && total > 0) ? (used/total)*100 : 0;
      }
      const label = canvas.dataset.label || 'Storage';
      animateDonut(canvas, Math.max(0, Math.min(100, percent)), { label });
    });
  }

  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initStorageDonuts);
  } else {
    initStorageDonuts();
  }
})();
