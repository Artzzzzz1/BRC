<?php
session_start();
require_once '../config/auth.php';
require_once '../config/db.php';
requireUser();
$activePage = '3dpreview';

// ── RIM CATALOG (shared with user-rimselect.php via config/rims.php) ──
// Whitelisting stops someone from passing ?rim=../../config/db.php etc.
$RIMS = [];
$catalog = require '../config/rims.php';
foreach ($catalog as $r) { $RIMS[$r['file']] = $r['name']; }

$rimFile = $_GET['rim'] ?? 'Wheel_only.glb';
$rimFile = basename($rimFile); // strip any path traversal attempt
if (!array_key_exists($rimFile, $RIMS)) {
  $rimFile = 'Wheel_only.glb';
}
$rimName = $RIMS[$rimFile];
// Allow a friendlier display name via query string, but only as a label —
// the actual file to load always comes from the whitelist above.
if (!empty($_GET['name'])) {
  $rimName = htmlspecialchars(strip_tags($_GET['name']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - 3D Rim Preview</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="user-dashboard.css">
  <style>
    /* ── Page Layout ── */
    .preview-page {
      display: grid;
      grid-template-columns: 1fr 320px;
      grid-template-rows: 1fr;
      gap: 0;
      flex: 1;
      min-height: 0;
      overflow: hidden;
    }

    @media (max-width: 768px) {
      .preview-page {
        grid-template-columns: 1fr;
        grid-template-rows: 42vh 1fr;
        overflow-y: auto;
      }
      .viewer-wrap {
        height: 42vh;
        min-height: 240px;
      }
      .control-panel {
        border-left: none;
        border-top: 1px solid #222;
        overflow-y: visible;
      }
      .viewer-hint .hint-desktop { display: none; }
      .viewer-hint .hint-mobile { display: inline; }
    }

    /* ── 3D Viewer ── */
    .viewer-wrap {
      /* NOTE: the <canvas> fills this entire area and paints its own opaque
         background every frame (see scene.background / createVignetteTexture
         in the script below), which sits on top of and hides whatever is
         set here. The actual vignette fix lives in the JS, not this CSS. */
      background: #0a0a0a;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      min-width: 0;
      min-height: 0;
    }
    #three-canvas {
      width: 100% !important;
      height: 100% !important;
      display: block;
    }
    .viewer-label {
      position: absolute;
      top: 20px;
      left: 24px;
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.6rem;
      letter-spacing: 0.06em;
      color: white;
      opacity: 0.5;
      pointer-events: none;
    }
    .viewer-label span { color: #CC0000; }
    .viewer-hint {
      position: absolute;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      font-size: 0.72rem;
      color: rgba(255,255,255,0.3);
      font-family: 'Montserrat', sans-serif;
      font-weight: 500;
      pointer-events: none;
      white-space: nowrap;
    }
    .loading-overlay {
      position: absolute;
      inset: 0;
      background: #0a0a0a;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 16px;
      z-index: 10;
    }
    .loading-spinner {
      width: 48px;
      height: 48px;
      border: 3px solid rgba(204,0,0,0.2);
      border-top-color: #CC0000;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .loading-text {
      font-family: 'Montserrat', sans-serif;
      font-size: 0.82rem;
      font-weight: 600;
      color: rgba(255,255,255,0.5);
    }

    /* ── Control Panel ── */
    .control-panel {
      background: #111;
      border-left: 1px solid #222;
      display: flex;
      flex-direction: column;
      overflow-y: auto;
    }
    .panel-head {
      padding: 20px 20px 16px;
      border-bottom: 1px solid #222;
    }
    .panel-head h2 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.3rem;
      letter-spacing: 0.04em;
      color: white;
      margin-bottom: 2px;
    }
    .panel-head p {
      font-size: 0.72rem;
      color: rgba(255,255,255,0.35);
      font-family: 'Montserrat', sans-serif;
      font-weight: 500;
    }
    .panel-section {
      padding: 18px 20px;
      border-bottom: 1px solid #1a1a1a;
    }
    .panel-section-label {
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #CC0000;
      margin-bottom: 12px;
    }

    /* ── Color Swatches ── */
    .color-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 8px;
    }
    .color-swatch {
      aspect-ratio: 1;
      border-radius: 8px;
      cursor: pointer;
      border: 2px solid transparent;
      transition: all 0.15s;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .color-swatch:hover { transform: scale(1.08); }
    .color-swatch.active {
      border-color: white;
      box-shadow: 0 0 0 3px rgba(255,255,255,0.2);
    }
    .color-swatch .swatch-check {
      display: none;
      color: white;
      font-size: 0.9rem;
      filter: drop-shadow(0 1px 2px rgba(0,0,0,0.8));
    }
    .color-swatch.active .swatch-check { display: block; }
    .color-name {
      text-align: center;
      font-size: 0.65rem;
      color: rgba(255,255,255,0.4);
      font-family: 'Montserrat', sans-serif;
      font-weight: 600;
      margin-top: 4px;
    }
    .color-item {
      display: flex;
      flex-direction: column;
    }

    /* ── Finish Buttons ── */
    .finish-grid {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .finish-btn {
      padding: 10px 14px;
      border-radius: 8px;
      border: 1.5px solid #2a2a2a;
      background: #1a1a1a;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: all 0.15s;
    }
    .finish-btn:hover { border-color: #444; background: #222; }
    .finish-btn.active { border-color: #CC0000; background: rgba(204,0,0,0.08); }
    .finish-icon {
      width: 32px;
      height: 32px;
      border-radius: 6px;
      flex-shrink: 0;
    }
    .finish-info h4 {
      font-size: 0.8rem;
      font-weight: 700;
      color: white;
      font-family: 'Montserrat', sans-serif;
      margin-bottom: 1px;
    }
    .finish-info p {
      font-size: 0.68rem;
      color: rgba(255,255,255,0.35);
      font-family: 'Montserrat', sans-serif;
    }
    .finish-check {
      margin-left: auto;
      color: #CC0000;
      font-size: 0.9rem;
      display: none;
    }
    .finish-btn.active .finish-check { display: block; }

    /* ── Selected Preview ── */
    .selected-preview {
      padding: 16px 20px;
      background: #0d0d0d;
      border-bottom: 1px solid #1a1a1a;
    }
    .selected-label {
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: rgba(255,255,255,0.3);
      margin-bottom: 8px;
      font-family: 'Montserrat', sans-serif;
    }
    .selected-value {
      font-size: 0.9rem;
      font-weight: 700;
      color: white;
      font-family: 'Montserrat', sans-serif;
    }
    .selected-value span { color: #CC0000; }

    /* ── Book Button ── */
    .panel-footer {
      padding: 16px 20px;
      margin-top: auto;
    }
    .btn-book {
      width: 100%;
      padding: 13px;
      background: #CC0000;
      color: white;
      border: none;
      border-radius: 8px;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.85rem;
      font-weight: 700;
      cursor: pointer;
      transition: background 0.2s;
      text-align: center;
      text-decoration: none;
      display: block;
    }
    .btn-book:hover { background: #aa0000; }
    .btn-reset {
      width: 100%;
      padding: 10px;
      background: transparent;
      color: rgba(255,255,255,0.3);
      border: 1px solid #222;
      border-radius: 8px;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.78rem;
      font-weight: 600;
      cursor: pointer;
      margin-top: 8px;
      transition: all 0.15s;
    }
    .btn-reset:hover { color: white; border-color: #444; }
  </style>
</head>
<body>
<?php include '../config/user-sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">
      <h1>3D Rim Preview</h1>
      <p><?= htmlspecialchars($rimName) ?></p>
    </div>
    <div class="topbar-actions">
      <a href="user-rimselect.php" class="btn btn-outline">← Choose a Different Rim</a>
    </div>
  </div>
  <div class="preview-page">

    <!-- ── 3D VIEWER ── -->
    <div class="viewer-wrap" id="viewerWrap">
      <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Loading 3D Model...</div>
      </div>
      <canvas id="three-canvas"></canvas>
      <div class="viewer-label">BRC <span>Calamba</span></div>
      <div class="viewer-hint">
        <span class="hint-desktop">🖱 Drag to rotate &nbsp;·&nbsp; Scroll to zoom</span>
        <span class="hint-mobile" style="display:none;">👆 Drag to rotate &nbsp;·&nbsp; Pinch to zoom</span>
      </div>
    </div>

    <!-- ── CONTROL PANEL ── -->
    <div class="control-panel">
      <div class="panel-head">
        <h2><?= htmlspecialchars($rimName) ?></h2>
        <p>Pick a color and finish to preview</p>
      </div>

      <!-- Selected Summary -->
      <div class="selected-preview">
        <div class="selected-label">Current Selection</div>
        <div class="selected-value" id="selectionText">
          <span id="selectedColor">Silver</span> — <span id="selectedFinish">Glossy</span>
        </div>
      </div>

      <!-- Colors -->
      <div class="panel-section">
        <div class="panel-section-label">Color</div>
        <div class="color-grid" id="colorGrid">
          <!-- Injected by JS -->
        </div>
      </div>

      <!-- Finish -->
      <div class="panel-section">
        <div class="panel-section-label">Finish / Texture</div>
        <div class="finish-grid" id="finishGrid">
          <!-- Injected by JS -->
        </div>
      </div>

      <!-- Footer -->
      <div class="panel-footer">
        <a href="user-appointment.php" class="btn-book">Book This Color →</a>
        <button class="btn-reset" onclick="resetSelection()">Reset to Default</button>
      </div>
    </div>

  </div>
</div>

<!-- Three.js from CDN, loaded as static tags (not JS-injected) so browsers
     with strict tracking prevention (e.g. Edge) don't block them. -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>
<script>
// ── SELECTED RIM (passed from user-rimselect.php, whitelisted server-side) ──
const RIM_FILE = '<?= $rimFile ?>';

// ── COLOR OPTIONS ──
const COLORS = [
  { name: 'Silver',  hex: '#C0C0C0' },
  { name: 'Black',   hex: '#1a1a1a' },
  { name: 'White',   hex: '#F5F5F5' },
  { name: 'Red',     hex: '#CC0000' },
  { name: 'Blue',    hex: '#1a4fcc' },
  { name: 'Gold',    hex: '#CFB53B' },
  { name: 'Green',   hex: '#1a7a2a' },
  { name: 'Orange',  hex: '#E85D04' },
];

// ── FINISH OPTIONS ──
const FINISHES = [
  {
    id: 'glossy',
    name: 'Glossy',
    desc: 'Shiny, mirror-like surface',
    roughness: 0.1,
    metalness: 0.2,
    icon: 'linear-gradient(135deg, #fff 0%, #aaa 100%)'
  },
  {
    id: 'matte',
    name: 'Matte',
    desc: 'Flat, non-reflective surface',
    roughness: 0.9,
    metalness: 0.0,
    icon: 'linear-gradient(135deg, #888 0%, #555 100%)'
  },
  {
    id: 'metallic',
    name: 'Metallic',
    desc: 'Sparkling metallic effect',
    roughness: 0.2,
    metalness: 0.95,
    icon: 'linear-gradient(135deg, #e0e0e0 0%, #888 40%, #e0e0e0 100%)'
  },
];

let currentColor = COLORS[0];
let currentFinish = FINISHES[0];
let rimMeshes = [];
let scene, camera, renderer, animationId;

// ── BUILD COLOR SWATCHES ──
const colorGrid = document.getElementById('colorGrid');
COLORS.forEach((c, i) => {
  const item = document.createElement('div');
  item.className = 'color-item';
  item.innerHTML = `
    <div class="color-swatch ${i === 0 ? 'active' : ''}"
         style="background:${c.hex}"
         onclick="selectColor(${i})"
         title="${c.name}">
      <span class="swatch-check">✓</span>
    </div>
    <div class="color-name">${c.name}</div>
  `;
  colorGrid.appendChild(item);
});

// ── BUILD FINISH BUTTONS ──
const finishGrid = document.getElementById('finishGrid');
FINISHES.forEach((f, i) => {
  const btn = document.createElement('div');
  btn.className = `finish-btn ${i === 0 ? 'active' : ''}`;
  btn.id = `finish-${f.id}`;
  btn.onclick = () => selectFinish(i);
  btn.innerHTML = `
    <div class="finish-icon" style="background:${f.icon}"></div>
    <div class="finish-info">
      <h4>${f.name}</h4>
      <p>${f.desc}</p>
    </div>
    <span class="finish-check">✓</span>
  `;
  finishGrid.appendChild(btn);
});

// ── BACKGROUND / ENVIRONMENT HELPERS ──
// A radial-gradient canvas texture used as scene.background. Lighter in the
// center so dark/black rims still read as a silhouette instead of merging
// into a flat black fill.
function createVignetteTexture() {
  // Drawn at the viewer's actual aspect ratio (not a fixed square) — a
  // circular gradient on a square canvas gets stretched into a squashed
  // oval with color bleeding into the corners once Three.js maps it onto a
  // widescreen viewport. Matching the aspect ratio keeps it a clean,
  // centered, roughly-circular vignette regardless of viewport shape.
  const wrap = document.getElementById('viewerWrap');
  const w = Math.max(wrap.clientWidth, 2);
  const h = Math.max(wrap.clientHeight, 2);
  const res = 512; // texture resolution along the shorter side
  const cw = w >= h ? Math.round(res * w / h) : res;
  const ch = h >= w ? Math.round(res * h / w) : res;

  const c = document.createElement('canvas');
  c.width = cw; c.height = ch;
  const ctx = c.getContext('2d');
  // "Dark Studio Gradient" — spotlight offset up/right to roughly match the
  // key light's position (set at x:2, y:3, z:2 in initThree below), instead
  // of sitting dead-center, so the backdrop reads as if lit from the same
  // direction as the rim itself.
  const cx = cw * 0.56, cy = ch * 0.38;
  const radius = Math.max(cw, ch) * 0.72; // how far the center tone reaches before fading
  const grad = ctx.createRadialGradient(cx, cy, 0, cx, cy, radius);
  grad.addColorStop(0, '#F4F4F5'); // Soft Off-White center
  grad.addColorStop(1, '#E4E4E7'); // Slightly darker gray edges
  ctx.fillStyle = grad;
  ctx.fillRect(0, 0, cw, ch);
  const tex = new THREE.CanvasTexture(c);
  tex.encoding = THREE.sRGBEncoding;
  return tex;
}

// A small procedural "studio" scene (bright soft panel above, darker floor
// below) baked into a PMREM environment map so Glossy/Metallic materials
// have gradient light to reflect, the same way real product-photo studios
// light chrome/metal with overhead softboxes. This is what gives Metallic
// finishes actual visible highlights instead of looking flat.
function createStudioEnvScene() {
  const s = new THREE.Scene();
  const topLight = new THREE.Mesh(
    new THREE.PlaneGeometry(8, 8),
    new THREE.MeshBasicMaterial({ color: 0xffffff, side: THREE.DoubleSide })
  );
  topLight.position.set(0, 6, 0);
  topLight.rotation.x = Math.PI / 2;
  s.add(topLight);

  const sideLight = new THREE.Mesh(
    new THREE.PlaneGeometry(6, 6),
    new THREE.MeshBasicMaterial({ color: 0x8899aa, side: THREE.DoubleSide })
  );
  sideLight.position.set(-6, 2, 2);
  sideLight.rotation.y = Math.PI / 2;
  s.add(sideLight);

  const floor = new THREE.Mesh(
    new THREE.PlaneGeometry(10, 10),
    new THREE.MeshBasicMaterial({ color: 0x1a1a1a, side: THREE.DoubleSide })
  );
  floor.position.set(0, -4, 0);
  floor.rotation.x = Math.PI / 2;
  s.add(floor);

  return s;
}

// ── THREE.JS SETUP ──
function initThree() {
  const wrap = document.getElementById('viewerWrap');
  const canvas = document.getElementById('three-canvas');

  // Scene
  scene = new THREE.Scene();
  // IMPORTANT: scene.background paints a SOLID, OPAQUE fill over the entire
  // canvas every frame — it completely covers any CSS background behind the
  // <canvas>, so the vignette has to live here, not in CSS. A flat near-black
  // fill (the old 0x0a0a0a) also gave black-colored rims nothing to contrast
  // against. This radial gradient is noticeably lighter in the center,
  // similar to a studio photography backdrop, specifically so dark/black
  // rims still read as a visible silhouette.
  scene.background = createVignetteTexture();

  // Camera
  camera = new THREE.PerspectiveCamera(45, wrap.clientWidth / wrap.clientHeight, 0.01, 100);
  camera.position.set(0, 0.08, 0.35);

  // Renderer
  renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
  renderer.setSize(wrap.clientWidth, wrap.clientHeight);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.outputEncoding = THREE.sRGBEncoding;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.0;
  renderer.shadowMap.enabled = true;

  // Environment map so Glossy/Metallic finishes have something to actually
  // reflect. Without this, a metal material (which has almost no diffuse
  // color of its own — that's physically correct, real metal behaves the
  // same way) relies entirely on direct lights for any visible highlight,
  // which is why Black + Metallic was nearly invisible: no amount of light
  // repositioning fixes that, it needs reflections. This is a lightweight
  // procedural studio-style gradient, not a photo, so it's cheap to build.
  const pmrem = new THREE.PMREMGenerator(renderer);
  pmrem.compileEquirectangularShader();
  const envScene = createStudioEnvScene();
  scene.environment = pmrem.fromScene(envScene, 0.04).texture;
  pmrem.dispose();

  // Lights — tuned down from the original rig, which was strong enough to
  // blow out lighter colors (Silver/White/Gold/Blue/Green) to near-white
  // under Glossy/Matte finishes.
  const ambient = new THREE.AmbientLight(0xffffff, 0.38);
  scene.add(ambient);

  const key = new THREE.DirectionalLight(0xffffff, 0.95);
  key.position.set(2, 3, 2);
  key.castShadow = true;
  scene.add(key);

  const fill = new THREE.DirectionalLight(0xffffff, 0.32);
  fill.position.set(-2, 1, -1);
  scene.add(fill);

  // Red brand-accent rim light — intentionally subtle. At the old 0.3
  // intensity it painted a visible red stain across concave surfaces
  // (worst on Metallic); this is a grazing side-back light instead of a
  // from-below one so it catches the silhouette edge rather than inner
  // spoke pockets.
  const rim = new THREE.DirectionalLight(0xCC0000, 0.16);
  rim.position.set(-1.5, -0.4, -2.2);
  scene.add(rim);

  const top = new THREE.PointLight(0xffffff, 0.4, 10);
  top.position.set(0, 3, 0);
  scene.add(top);

  // Load GLB
  loadGLB();

  // Orbit controls (manual)
  setupOrbit(canvas);

  // Resize
  window.addEventListener('resize', () => {
    camera.aspect = wrap.clientWidth / wrap.clientHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(wrap.clientWidth, wrap.clientHeight);
    // Rebuild the vignette at the new aspect ratio, otherwise it goes
    // stale/stretched again after a resize (e.g. rotating a phone, or
    // resizing a desktop browser window).
    if (scene.background && scene.background.dispose) scene.background.dispose();
    scene.background = createVignetteTexture();
  });

  // Animate
  animate();
}

function loadGLB() {
  // GLTFLoader is loaded as a static <script> tag in <head> above, so it's
  // guaranteed ready here — no runtime script injection needed.
  const loader = new THREE.FileLoader();
  loader.setResponseType('arraybuffer');
  loader.load(
    '../public/' + RIM_FILE,
    (buffer) => parseGLTF(buffer),
    (xhr) => {
      const pct = Math.round((xhr.loaded / xhr.total) * 100);
      document.querySelector('.loading-text').textContent = `Loading model... ${pct}%`;
    },
    (err) => {
      document.querySelector('.loading-text').textContent = 'Error loading model.';
      console.error(err);
    }
  );
}

function parseGLTF(buffer) {
  const gltfLoader = new THREE.GLTFLoader();
  gltfLoader.parse(buffer, '', (gltf) => {
    const model = gltf.scene;

    // Center and scale
    const box = new THREE.Box3().setFromObject(model);
    const center = box.getCenter(new THREE.Vector3());
    const size = box.getSize(new THREE.Vector3());
    const maxDim = Math.max(size.x, size.y, size.z);
    const scale = 0.25 / maxDim;

    model.position.sub(center.multiplyScalar(scale));
    model.scale.setScalar(scale);

    scene.add(model);

    // Collect meshes and apply default material
    rimMeshes = [];
    model.traverse((child) => {
      if (child.isMesh) {
        rimMeshes.push(child);
        child.castShadow = true;
        child.receiveShadow = true;
      }
    });

    applyMaterial();
    document.getElementById('loadingOverlay').style.display = 'none';
  }, (err) => {
    document.querySelector('.loading-text').textContent = 'Failed to parse model.';
    console.error(err);
  });
}

function applyMaterial() {
  const color = new THREE.Color(currentColor.hex);
  rimMeshes.forEach(mesh => {
    mesh.material = new THREE.MeshStandardMaterial({
      color: color,
      roughness: currentFinish.roughness,
      metalness: currentFinish.metalness,
      envMapIntensity: 0.8,
      // Some rim sources (e.g. AI-generated / Meshy exports) use the
      // opposite face winding from our Blender exports. DoubleSide makes
      // the renderer draw both sides regardless of winding direction, so
      // a rim never goes dark/invisible just because of how it was wound.
      side: THREE.DoubleSide
    });
  });
}

// ── ORBIT CONTROLS (manual) ──
function setupOrbit(canvas) {
  let isDragging = false;
  let prevX = 0, prevY = 0;
  let rotX = 0, rotY = 0;
  let targetRotX = 0, targetRotY = 0;
  let autoRotate = true;
  let autoRotateTimer;

  canvas.addEventListener('mousedown', (e) => {
    isDragging = true;
    autoRotate = false;
    clearTimeout(autoRotateTimer);
    prevX = e.clientX;
    prevY = e.clientY;
  });

  window.addEventListener('mousemove', (e) => {
    if (!isDragging) return;
    const dx = e.clientX - prevX;
    const dy = e.clientY - prevY;
    targetRotY += dx * 0.008;
    targetRotX += dy * 0.005;
    targetRotX = Math.max(-Math.PI / 3, Math.min(Math.PI / 3, targetRotX));
    prevX = e.clientX;
    prevY = e.clientY;
  });

  window.addEventListener('mouseup', () => {
    isDragging = false;
    autoRotateTimer = setTimeout(() => { autoRotate = true; }, 3000);
  });

  // Touch
  let pinchStartDist = null;

  function touchDist(touches) {
    const dx = touches[0].clientX - touches[1].clientX;
    const dy = touches[0].clientY - touches[1].clientY;
    return Math.sqrt(dx * dx + dy * dy);
  }

  canvas.addEventListener('touchstart', (e) => {
    autoRotate = false;
    clearTimeout(autoRotateTimer);
    if (e.touches.length === 2) {
      isDragging = false;
      pinchStartDist = touchDist(e.touches);
    } else {
      isDragging = true;
      pinchStartDist = null;
      prevX = e.touches[0].clientX;
      prevY = e.touches[0].clientY;
    }
  });
  canvas.addEventListener('touchmove', (e) => {
    e.preventDefault();
    if (e.touches.length === 2 && pinchStartDist !== null) {
      const newDist = touchDist(e.touches);
      const delta = pinchStartDist - newDist;
      camera.position.z += delta * 0.0015;
      camera.position.z = Math.max(0.1, Math.min(0.8, camera.position.z));
      pinchStartDist = newDist;
      return;
    }
    if (!isDragging) return;
    const dx = e.touches[0].clientX - prevX;
    const dy = e.touches[0].clientY - prevY;
    targetRotY += dx * 0.008;
    targetRotX += dy * 0.005;
    prevX = e.touches[0].clientX;
    prevY = e.touches[0].clientY;
  }, { passive: false });
  canvas.addEventListener('touchend', (e) => {
    if (e.touches.length < 2) pinchStartDist = null;
    if (e.touches.length === 0) {
      isDragging = false;
      autoRotateTimer = setTimeout(() => { autoRotate = true; }, 3000);
    }
  });

  // Zoom
  canvas.addEventListener('wheel', (e) => {
    camera.position.z += e.deltaY * 0.0005;
    camera.position.z = Math.max(0.1, Math.min(0.8, camera.position.z));
  });

  // Expose to animate loop
  window._orbit = {
    get rotX() { return rotX; },
    get rotY() { return rotY; },
    update() {
      if (autoRotate) targetRotY += 0.005;
      rotX += (targetRotX - rotX) * 0.08;
      rotY += (targetRotY - rotY) * 0.08;
      scene.rotation.x = rotX;
      scene.rotation.y = rotY;
    }
  };
}

function animate() {
  animationId = requestAnimationFrame(animate);
  if (window._orbit) window._orbit.update();
  renderer.render(scene, camera);
}

// ── SELECT COLOR ──
function selectColor(index) {
  currentColor = COLORS[index];
  document.querySelectorAll('.color-swatch').forEach((s, i) => {
    s.classList.toggle('active', i === index);
  });
  document.getElementById('selectedColor').textContent = currentColor.name;
  applyMaterial();
}

// ── SELECT FINISH ──
function selectFinish(index) {
  currentFinish = FINISHES[index];
  document.querySelectorAll('.finish-btn').forEach((b, i) => {
    b.classList.toggle('active', i === index);
  });
  document.getElementById('selectedFinish').textContent = currentFinish.name;
  applyMaterial();
}

// ── RESET ──
function resetSelection() {
  selectColor(0);
  selectFinish(0);
}

// ── INIT ──
window.addEventListener('load', initThree);
</script>
</body>
</html>
