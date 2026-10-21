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
    /* The label/hint sit on top of the backdrop, so they follow its brightness */
    .viewer-wrap:not(.dark-bg) .viewer-label { color: #1f2024; opacity: 0.6; }
    .viewer-wrap:not(.dark-bg) .viewer-hint  { color: rgba(0,0,0,0.45); }
    .bg-toggle {
      position: absolute; top: 16px; right: 16px; z-index: 5;
      display: flex; gap: 2px; padding: 3px; border-radius: 999px;
      background: rgba(20,20,22,0.55); backdrop-filter: blur(6px);
    }
    .bg-toggle button {
      border: 0; background: transparent; color: rgba(255,255,255,0.7);
      font-family: 'Montserrat', sans-serif; font-size: 0.68rem; font-weight: 700;
      padding: 5px 11px; border-radius: 999px; cursor: pointer;
    }
    .bg-toggle button.active { background: #CC0000; color: #fff; }
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
      grid-template-columns: repeat(3, 1fr);
      gap: 10px 8px;
    }
    .color-swatch {
      aspect-ratio: 1.5;
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
      line-height: 1.25;
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

    /* ── Shop color categories + price ── */
    .cat-tabs {
      display: flex; gap: 6px; overflow-x: auto; padding-bottom: 8px; margin-bottom: 6px;
      scrollbar-width: thin; scrollbar-color: #333 transparent;
    }
    .cat-tab {
      flex: 0 0 auto; padding: 7px 13px; border-radius: 999px;
      border: 1.5px solid #2a2a2a; background: #1a1a1a;
      color: rgba(255,255,255,0.65); font-family: 'Montserrat', sans-serif;
      font-size: 0.72rem; font-weight: 700; cursor: pointer; transition: all 0.15s;
    }
    .cat-tab:hover { border-color: #444; color: #fff; }
    .cat-tab.active { border-color: #CC0000; background: rgba(204,0,0,0.12); color: #fff; }
    .cat-note {
      font-size: 0.7rem; color: rgba(255,255,255,0.4); font-family: 'Montserrat', sans-serif;
      font-weight: 500; margin-bottom: 12px;
    }
    .preview-note {
      font-size: 0.74rem; line-height: 1.5; color: rgba(255,255,255,0.55);
      font-family: 'Montserrat', sans-serif; font-weight: 500;
      background: #161616; border: 1px solid #262626; border-radius: 8px; padding: 12px 14px;
    }
    .preview-note strong { color: #fff; }

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
      <div class="bg-toggle" id="bgToggle" title="Background">
        <button type="button" data-mode="auto" class="active">Auto</button>
        <button type="button" data-mode="light">Light</button>
        <button type="button" data-mode="dark">Dark</button>
      </div>
      <div class="viewer-hint">
        <span class="hint-desktop">🖱 Drag to rotate &nbsp;·&nbsp; Scroll to zoom</span>
        <span class="hint-mobile" style="display:none;">👆 Drag to rotate &nbsp;·&nbsp; Pinch to zoom</span>
      </div>
    </div>

    <!-- ── CONTROL PANEL ── -->
    <div class="control-panel">
      <div class="panel-head">
        <h2><?= htmlspecialchars($rimName) ?></h2>
        <p>Pick one of the shop's colors to preview it on this rim</p>
      </div>

      <!-- Selected Summary -->
      <div class="selected-preview">
        <div class="selected-label">Current Selection</div>
        <div class="selected-value" id="selectionText">
          <span id="selectedColor">Silver</span> — <span id="selectedFinish">Glossy</span>
        </div>
      </div>

      <!-- Shop colors -->
      <div class="panel-section">
        <div class="panel-section-label">Shop Colors</div>
        <div class="cat-tabs" id="catTabs"></div>
        <div class="cat-note" id="catNote"></div>
        <div class="color-grid" id="colorGrid">
          <!-- Injected by JS -->
        </div>
      </div>

      <!-- Preview-only note -->
      <div class="panel-section">
        <div class="preview-note">
          <strong>For preview only.</strong> These colors just show how the rim could look.
          Please visit the shop to confirm which colors are in stock and the final price.
        </div>
      </div>

      <!-- Footer -->
      <div class="panel-footer">
        <a href="user-appointment.php" class="btn-book" id="bookBtn">Book This Color →</a>
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
const RIM_NAME = <?= json_encode($RIMS[$rimFile], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

// ── SHOP COLOR CATALOG (from the shop's price list) ──
// svc = which appointment service the category books as. These texts must match the
// <option> text in user-appointment.php exactly.
const SERVICE_TEXT = {
  gloss:    'Powder Coating \u2014 Gloss Finish',
  matte:    'Powder Coating \u2014 Matte Finish',
  metallic: 'Powder Coating \u2014 Metallic Finish',
  custom:   'Powder Coating \u2014 Custom Color'
};
const CATEGORIES = [
  { id: 'regular',     name: 'Regular',     svc: 'gloss',    note: 'Solid colors' },
  { id: 'pastel',      name: 'Pastel',      svc: 'gloss',    note: 'Soft, light tones' },
  { id: 'special',     name: 'Special',     svc: 'metallic', note: 'Metallic and special colors' },
  { id: 'candy',       name: 'Candy',       svc: 'metallic', note: 'Deep, see-through candy and chrome colors' },
  { id: 'chameleon',   name: 'Chameleon',   svc: 'metallic', note: 'The color shifts as you look from different angles' },
  { id: 'illusion',    name: 'Illusion',    svc: 'metallic', note: 'Two-tone color-shift effect' },
  { id: 'prism',       name: 'Prism',       svc: 'metallic', note: 'Strong color-shift effect' },
  { id: 'hammertone',  name: 'Hammertone',  svc: 'custom',   note: 'Hammered-metal texture' },
  { id: 'fluorescent', name: 'Fluorescent', svc: 'custom',   note: 'Bright, glowing tones' },
  { id: 'lollipop',    name: 'Lollipop',    svc: 'custom',   note: 'Bright candy-gloss colors' }
];
const FIN_LABEL = {
  gloss: 'Glossy', matte: 'Matte', satin: 'Satin', pearl: 'Pearl', metallic: 'Metallic',
  candy: 'Candy gloss', chrome: 'Chrome metallic', shift: 'Color-shift', neon: 'Fluorescent',
  hammer: 'Hammered texture', glow: 'Glow in the dark'
};
// [category, name, hex, finish, shift-color]. Hex values are on-screen approximations,
// not exact paint matches.
const COLORS = [
  ['regular','Glossy White','#F6F6F3','gloss'], ['regular','Pearl White','#EFEDE4','pearl'],
  ['regular','Aquamarine Blue','#3C9FC4','gloss'], ['regular','Turquoise Blue','#1FB3C0','gloss'],
  ['regular','Matte Black','#151515','matte'], ['regular','Sahara Black','#202020','satin'],
  ['regular','Glossy Black','#0B0B0C','gloss'], ['regular','Glossy Telemagenta','#D4147A','gloss'],

  ['pastel','Pastel Green','#A8DDB5','gloss'], ['pastel','Pastel Turquoise','#8EDCD3','gloss'],
  ['pastel','Blue Lilac','#B9B4E6','gloss'], ['pastel','Water Blue','#9FD0EE','gloss'],

  ['special','Special Gold','#C9A227','metallic'], ['special','Special Violet','#6B3FA0','metallic'],
  ['special','Dark Bronze','#5C4125','metallic'], ['special','Golden Metallic','#D4A63A','metallic'],
  ['special','Bronze Golden Metallic','#A57C2E','metallic'], ['special','Black Shine Silver','#3A3C40','metallic'],
  ['special','Metallic Bronze','#8A5A2B','metallic'], ['special','Ral Orange','#E2561B','gloss'],
  ['special','Ferrari Red','#C0121F','gloss'], ['special','Special Silver','#BDBFC3','metallic'],
  ['special','Super Flash Silver','#D5D8DC','metallic'], ['special','Galaxy Black','#17181D','metallic'],
  ['special','Champagne Gold','#D9C28F','metallic'], ['special','Sparkling Silver','#C9CBD0','metallic'],
  ['special','Gun Metal','#565B61','metallic'], ['special','RCB White','#F4F4F0','gloss'],

  ['candy','Candy Red','#B00020','candy'], ['candy','Candy Purple','#6A1B9A','candy'],
  ['candy','Candy Orange Red','#D8381A','candy'], ['candy','Chrome Metallic Blue','#1E5FC4','chrome'],
  ['candy','Chrome Green','#1E9E4A','chrome'], ['candy','Candy Chrome Pink','#E0529C','chrome'],
  ['candy','Candy Teal','#0E8F8A','candy'], ['candy','Coke Red','#7B0C16','candy'],

  ['chameleon','Chameleon Pearl White','#EDEBF2','shift','#B7D4F5'],
  ['chameleon','Chameleon Blue','#1E4FA8','shift','#7A34C0'],
  ['chameleon','Chameleon Green','#1F8A55','shift','#2A6FB0'],
  ['chameleon','Chameleon Black','#15151A','shift','#4A33A0'],

  ['illusion','Illusion Red','#C21830','shift','#F0902E'], ['illusion','Illusion Magenta','#C2187A','shift','#7A3CD0'],
  ['illusion','Illusion Shocker Green','#3FD13A','shift','#C9F028'], ['illusion','Illusion Purple','#6A2FB0','shift','#2F6AD0'],
  ['illusion','Illusion Violet','#7B3FD0','shift','#D04FA8'], ['illusion','Illusion Green','#1F9A4A','shift','#C0D030'],
  ['illusion','Illusion Gold','#D6A21E','shift','#E0602A'], ['illusion','Illusion Pink','#E2509A','shift','#B070E0'],
  ['illusion','Illusion Shocker Yellow','#F2D91A','shift','#7AD02A'], ['illusion','Illusion Teal Blue','#12A3A8','shift','#3060C8'],
  ['illusion','Illusion Blue','#2358C8','shift','#2FB0D0'], ['illusion','Glow in the Dark','#D8F5C8','glow'],

  ['prism','Prism Blue','#2850C8','shift','#8A30D0'], ['prism','Prism Violet','#7A3AD0','shift','#E040A0'],
  ['prism','Prism Green','#20A050','shift','#30B0D0'],

  ['hammertone','Hammertone Candy Red','#A5122A','hammer'], ['hammertone','Hammertone Silver','#A9ACB0','hammer'],
  ['hammertone','Hammertone Chrome Silver','#C8CBCF','hammer'], ['hammertone','Hammertone Green','#2F7A45','hammer'],
  ['hammertone','Hammertone Blue','#2B5CA8','hammer'], ['hammertone','Hammertone Copper','#B4693A','hammer'],
  ['hammertone','Hammertone Yellow','#D8B52A','hammer'],

  ['fluorescent','Fluorescent Green','#39FF14','neon'], ['fluorescent','Fluorescent Yellow Green','#C8FF1A','neon'],
  ['fluorescent','Fluorescent Yellow','#FFF21A','neon'],

  ['lollipop','Lollipop Red','#E0102A','candy'], ['lollipop','Lollipop Blue','#1E6BE0','candy'],
  ['lollipop','Lollipop Purple','#8A2BE0','candy'], ['lollipop','Lollipop Yellow','#FFD21A','candy']
].map(([cat, name, hex, fin, alt]) => ({ cat, name, hex, fin, alt: alt || null }));

const catById = (id) => CATEGORIES.find(c => c.id === id);

let currentCat = 'regular';
let currentColor = COLORS.find(c => c.name === 'Glossy Black');
let rimMeshes = [];
let scene, camera, renderer, animationId;
let modelRadius = 0.2;   // bounding-sphere radius of the scaled rim (updated after load)
let maxZoomZ = 0.8;      // furthest the user can zoom out (updated by fitCamera)

// Place the camera on the Z axis, looking at the rim's center, far enough back
// that the whole rim stays in frame at ANY viewer aspect ratio (phones are
// narrow, so horizontal FOV is the limiting side there) even while it spins.
function fitCamera() {
  const wrap = document.getElementById('viewerWrap');
  const w = Math.max(wrap.clientWidth, 1), h = Math.max(wrap.clientHeight, 1);
  camera.aspect = w / h;
  const vFov = camera.fov * Math.PI / 180;
  const hFov = 2 * Math.atan(Math.tan(vFov / 2) * camera.aspect);
  const limiting = Math.min(vFov, hFov);
  const dist = (modelRadius / Math.sin(limiting / 2)) * 1.15;
  camera.position.set(0, 0, dist);
  camera.lookAt(0, 0, 0);
  camera.updateProjectionMatrix();
  maxZoomZ = Math.max(0.8, dist * 1.6);
}


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
  if (bgIsDark) {
    grad.addColorStop(0, '#4B4E57'); // Charcoal spotlight (makes white/silver/pastel rims stand out)
    grad.addColorStop(1, '#17181B'); // Near-black edges
  } else {
    grad.addColorStop(0, '#F4F4F5'); // Soft off-white center (makes black/dark rims stand out)
    grad.addColorStop(1, '#E4E4E7'); // Slightly darker gray edges
  }
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
  applyBackground();   // sets scene.background for the current color / mode

  // Camera
  camera = new THREE.PerspectiveCamera(45, wrap.clientWidth / wrap.clientHeight, 0.01, 100);
  camera.position.set(0, 0, 0.55); // real distance is set by fitCamera()

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

  // Resize — runs on window resize AND whenever the viewer box itself changes
  // size (mobile address bar, orientation change, layout settling after load).
  const onResize = () => {
    renderer.setSize(wrap.clientWidth, wrap.clientHeight);
    fitCamera();
    // Rebuild the vignette at the new aspect ratio, otherwise it goes
    // stale/stretched again after a resize.
    if (scene.background && scene.background.dispose) scene.background.dispose();
    scene.background = createVignetteTexture();
  };
  window.addEventListener('resize', onResize);
  if (window.ResizeObserver) new ResizeObserver(onResize).observe(wrap);
  fitCamera();

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
    modelRadius = (size.length() * scale) / 2;
    fitCamera();

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

// Look of each finish. Only properties that exist on MeshPhysicalMaterial in three r128.
const FIN_PARAMS = {
  gloss:    { roughness: 0.12, metalness: 0.12, clearcoat: 1.0, clearcoatRoughness: 0.04 },
  pearl:    { roughness: 0.20, metalness: 0.30, clearcoat: 1.0, clearcoatRoughness: 0.06 },
  matte:    { roughness: 0.88, metalness: 0.00, clearcoat: 0.0, clearcoatRoughness: 0.5 },
  satin:    { roughness: 0.50, metalness: 0.10, clearcoat: 0.25, clearcoatRoughness: 0.5 },
  metallic: { roughness: 0.30, metalness: 0.90, clearcoat: 0.7, clearcoatRoughness: 0.10 },
  candy:    { roughness: 0.14, metalness: 0.70, clearcoat: 1.0, clearcoatRoughness: 0.03 },
  chrome:   { roughness: 0.08, metalness: 0.95, clearcoat: 1.0, clearcoatRoughness: 0.02 },
  shift:    { roughness: 0.18, metalness: 0.65, clearcoat: 1.0, clearcoatRoughness: 0.05 },
  neon:     { roughness: 0.35, metalness: 0.00, clearcoat: 0.5, clearcoatRoughness: 0.2 },
  hammer:   { roughness: 0.42, metalness: 0.80, clearcoat: 0.0, clearcoatRoughness: 0.5 },
  glow:     { roughness: 0.40, metalness: 0.00, clearcoat: 0.5, clearcoatRoughness: 0.2 }
};

// Small procedural dent pattern for the Hammertone finish
let _hammerBump = null;
function hammerBump() {
  if (_hammerBump) return _hammerBump;
  const cv = document.createElement('canvas');
  cv.width = cv.height = 128;
  const g = cv.getContext('2d');
  g.fillStyle = '#808080';
  g.fillRect(0, 0, 128, 128);
  for (let i = 0; i < 90; i++) {
    const x = Math.random() * 128, y = Math.random() * 128, r = 6 + Math.random() * 8;
    const grad = g.createRadialGradient(x, y, 0, x, y, r);
    grad.addColorStop(0, '#303030');
    grad.addColorStop(0.7, '#9a9a9a');
    grad.addColorStop(1, '#808080');
    g.fillStyle = grad;
    g.beginPath(); g.arc(x, y, r, 0, Math.PI * 2); g.fill();
  }
  const t = new THREE.CanvasTexture(cv);
  t.wrapS = t.wrapT = THREE.RepeatWrapping;
  t.repeat.set(6, 6);
  _hammerBump = t;
  return t;
}

function buildMaterial(c, mesh) {
  const p = Object.assign(
    {
      color: new THREE.Color(c.hex),
      envMapIntensity: 0.9,
      // Some rim sources (e.g. AI-generated / Meshy exports) use the opposite
      // face winding. DoubleSide keeps a rim from going dark/invisible.
      side: THREE.DoubleSide
    },
    FIN_PARAMS[c.fin] || FIN_PARAMS.gloss
  );
  if (c.fin === 'neon') { p.emissive = new THREE.Color(c.hex); p.emissiveIntensity = 0.5; }
  if (c.fin === 'glow') { p.emissive = new THREE.Color('#7CFF8A'); p.emissiveIntensity = 0.55; }
  if (c.fin === 'hammer' && mesh.geometry && mesh.geometry.attributes && mesh.geometry.attributes.uv) {
    p.bumpMap = hammerBump();
    p.bumpScale = 2;
  }
  const mat = new THREE.MeshPhysicalMaterial(p);

  // Color-shift paints (Chameleon / Illusion / Prism): blend toward a second color
  // at grazing view angles, so the color changes as the rim rotates.
  if (c.fin === 'shift' && c.alt) {
    const amt = c.cat === 'illusion' ? 0.6 : 0.8;
    mat.onBeforeCompile = (shader) => {
      shader.uniforms.uShiftColor = { value: new THREE.Color(c.alt) };
      shader.uniforms.uShiftAmt = { value: amt };
      shader.fragmentShader = shader.fragmentShader
        .replace('#include <common>', '#include <common>\nuniform vec3 uShiftColor;\nuniform float uShiftAmt;')
        .replace('#include <dithering_fragment>',
          '#include <dithering_fragment>\n' +
          '  float shiftF = pow(1.0 - clamp(abs(dot(normalize(vNormal), normalize(vViewPosition))), 0.0, 1.0), 1.6);\n' +
          '  gl_FragColor.rgb = mix(gl_FragColor.rgb, uShiftColor, clamp(shiftF * uShiftAmt, 0.0, 1.0));');
    };
    mat.customProgramCacheKey = () => 'brc-shift';
  }
  return mat;
}

function applyMaterial() {
  rimMeshes.forEach(mesh => {
    if (mesh.material && mesh.material.dispose) mesh.material.dispose();
    mesh.material = buildMaterial(currentColor, mesh);
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
      camera.position.z = Math.max(0.1, Math.min(maxZoomZ, camera.position.z));
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
    camera.position.z = Math.max(0.1, Math.min(maxZoomZ, camera.position.z));
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

// ── ADAPTIVE BACKDROP ──
// No single background suits every paint: white/silver/pastel/yellow rims vanish on a light
// one, black rims vanish on a dark one. In Auto mode the backdrop flips with the picked color.
let bgMode = 'auto';      // 'auto' | 'light' | 'dark'
let bgIsDark = false;
function isLightPaint(hex) {
  const r = parseInt(hex.slice(1, 3), 16) / 255;
  const g = parseInt(hex.slice(3, 5), 16) / 255;
  const b = parseInt(hex.slice(5, 7), 16) / 255;
  return (0.299 * r + 0.587 * g + 0.114 * b) > 0.58;   // perceived brightness
}
function applyBackground() {
  bgIsDark = bgMode === 'dark' || (bgMode === 'auto' && isLightPaint(currentColor.hex));
  const wrap = document.getElementById('viewerWrap');
  if (wrap) wrap.classList.toggle('dark-bg', bgIsDark);
  if (scene) {
    if (scene.background && scene.background.dispose) scene.background.dispose();
    scene.background = createVignetteTexture();
  }
}
document.querySelectorAll('#bgToggle button').forEach(btn => {
  btn.addEventListener('click', () => {
    bgMode = btn.dataset.mode;
    document.querySelectorAll('#bgToggle button').forEach(b => b.classList.toggle('active', b === btn));
    applyBackground();
  });
});

// ── CATEGORY TABS + COLOR SWATCHES ──
function swatchStyle(c) {
  switch (c.fin) {
    case 'metallic':
    case 'chrome':
      return `background:linear-gradient(135deg,rgba(255,255,255,.55) 0%,rgba(255,255,255,0) 35%,rgba(0,0,0,.25) 100%),${c.hex}`;
    case 'candy':
      return `background:radial-gradient(circle at 30% 25%,rgba(255,255,255,.55),rgba(255,255,255,0) 45%),${c.hex}`;
    case 'shift':
      return `background:linear-gradient(135deg,${c.hex} 0%,${c.hex} 35%,${c.alt} 100%)`;
    case 'neon':
      return `background:${c.hex};box-shadow:0 0 14px ${c.hex}99`;
    case 'glow':
      return `background:${c.hex};box-shadow:0 0 14px #7CFF8A99`;
    case 'hammer':
      return `background:radial-gradient(circle at 30% 30%,rgba(255,255,255,.28) 0 6%,transparent 7%) 0 0/10px 10px,radial-gradient(circle at 70% 70%,rgba(0,0,0,.28) 0 6%,transparent 7%) 0 0/10px 10px,${c.hex}`;
    default:
      return `background:${c.hex}`;
  }
}

function renderTabs() {
  const el = document.getElementById('catTabs');
  el.innerHTML = '';
  CATEGORIES.forEach(cat => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'cat-tab' + (cat.id === currentCat ? ' active' : '');
    b.textContent = cat.name;
    b.onclick = () => { currentCat = cat.id; renderTabs(); renderSwatches(); };
    el.appendChild(b);
  });
  const active = el.querySelector('.cat-tab.active');
  if (active && active.scrollIntoView) active.scrollIntoView({ block: 'nearest', inline: 'nearest' });
}

function renderSwatches() {
  const grid = document.getElementById('colorGrid');
  grid.innerHTML = '';
  document.getElementById('catNote').textContent = catById(currentCat).note;
  COLORS.forEach((c, i) => {
    if (c.cat !== currentCat) return;
    const item = document.createElement('div');
    item.className = 'color-item';
    item.innerHTML =
      `<div class="color-swatch ${c === currentColor ? 'active' : ''}" style="${swatchStyle(c)}" title="${c.name}">` +
      `<span class="swatch-check">\u2713</span></div><div class="color-name">${c.name}</div>`;
    item.querySelector('.color-swatch').onclick = () => selectColor(i);
    grid.appendChild(item);
  });
}

// ── SELECT COLOR ──
function selectColor(index) {
  currentColor = COLORS[index];
  currentCat = currentColor.cat;
  renderTabs();
  renderSwatches();
  document.getElementById('selectedColor').textContent = currentColor.name;
  document.getElementById('selectedFinish').textContent =
    catById(currentColor.cat).name + ' \u00b7 ' + (FIN_LABEL[currentColor.fin] || '');
  applyMaterial();
  applyBackground();
  updateBookLink();
}

// ── RESET ──
function resetSelection() {
  selectColor(COLORS.findIndex(c => c.name === 'Glossy Black'));
}

// ── SERVICE for the appointment form ──
function serviceFor(c) {
  if (c.fin === 'matte') return SERVICE_TEXT.matte;
  const cat = catById(c.cat);
  if (cat.svc === 'metallic' && ['gloss', 'pearl', 'satin'].includes(c.fin)) return SERVICE_TEXT.gloss;
  return SERVICE_TEXT[cat.svc];
}

// ── BOOK THIS COLOR → pre-fills the appointment form ──
function updateBookLink() {
  const btn = document.getElementById('bookBtn');
  if (!btn || !currentColor) return;
  const params = new URLSearchParams({
    service:      serviceFor(currentColor),
    color_finish: currentColor.name,
    rim:          RIM_NAME
  });
  btn.href = 'user-appointment.php?' + params.toString();
}

// ── INIT ──
selectColor(COLORS.indexOf(currentColor));   // builds tabs/swatches/price/link (no rim loaded yet, so no 3D work)
window.addEventListener('load', initThree);
</script>
</body>
</html>
