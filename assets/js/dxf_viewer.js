// assets/js/dxf_viewer.js
(function(){
  function ready(fn){ if(document.readyState!=='loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
  ready(function(){
    var src = window.CDE_DXF_SRC;
    var mount = document.getElementById('dxf-view');
    if(!src || !mount){
      return;
    }

    function showError(msg){
      mount.innerHTML = '<div class="note muted">'+msg+'</div>';
    }

    fetch(src, { credentials:'same-origin' })
      .then(function(r){
        if(!r.ok) throw new Error('HTTP '+r.status);
        return r.text();
      })
      .then(function(txt){
        if(!(window.DxfParser && window.THREE && window.ThreeDxf)){
          showError('Thiếu thư viện viewer (three.js / dxf-parser / three-dxf).');
          return;
        }
        var parser = new window.DxfParser();
        var parsed;
        try {
          parsed = parser.parseSync(txt);
        } catch(err){
          showError('Không phân tích được DXF: ' + String(err && err.message || err));
          return;
        }

        var width = mount.clientWidth || 1200;
        var height = mount.clientHeight || 700;
        var viewer = new window.ThreeDxf.Viewer(parsed, mount, width, height);
        viewer.resize(width, height);
        viewer.render();

        // Orbit controls
        if (window.THREE && THREE.OrbitControls){
          var controls = new THREE.OrbitControls(viewer.camera, viewer.renderer.domElement);
          controls.enableDamping = true;
          controls.dampingFactor = 0.05;
          controls.screenSpacePanning = true;
          function animate(){
            requestAnimationFrame(animate);
            controls.update();
            viewer.render();
          }
          animate();
        }

        // Resize handler
        window.addEventListener('resize', function(){
          var w = mount.clientWidth || width;
          var h = mount.clientHeight || height;
          viewer.resize(w, h);
          viewer.render();
        });
      })
      .catch(function(err){
        showError('Không tải được DXF: ' + String(err && err.message || err));
      });
  });
})();
