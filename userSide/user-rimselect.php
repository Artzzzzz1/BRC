<?php
session_start();
require_once '../config/auth.php';
require_once '../config/db.php';
requireUser();
$activePage = '3dpreview';

// ── RIM CATALOG ──
// Lives in config/rims.php so the picker and the 3D viewer always agree.
$RIMS = require '../config/rims.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Choose a Rim</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="user-dashboard.css">
  <style>
    .rim-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 20px;
    }
    .rim-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 12px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: box-shadow 0.2s, transform 0.2s;
    }
    .rim-card:hover {
      box-shadow: 0 4px 16px rgba(0,0,0,0.08);
      transform: translateY(-2px);
    }
    .rim-thumb {
      background: var(--bg);
      border-bottom: 1px solid var(--border);
      aspect-ratio: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }
    .rim-thumb svg { width: 62%; height: 62%; }
    .rim-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
    .rim-info {
      padding: 18px 20px 20px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      flex: 1;
    }
    .rim-info h3 {
      font-family: 'Montserrat', sans-serif;
      font-size: 1rem;
      font-weight: 700;
      color: var(--text);
    }
    .rim-spokes {
      font-family: 'Montserrat', sans-serif;
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--red);
    }
    .rim-desc {
      font-family: 'Montserrat', sans-serif;
      font-size: 0.8rem;
      color: var(--muted);
      flex: 1;
    }
    .btn-select-rim {
      margin-top: 10px;
      width: 100%;
      justify-content: center;
    }
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
      <h1>Choose Your Rim</h1>
      <p>Pick a rim design to preview colors and finishes on it</p>
    </div>
  </div>

  <div class="content">
    <div class="rim-grid">
      <?php foreach ($RIMS as $rim):
        $spokeCount = (int)$rim['spokes'];
        $angleStep = 360 / max($spokeCount, 1);
        $spokeLines = '';
        for ($i = 0; $i < $spokeCount; $i++) {
          $angle = deg2rad($i * $angleStep);
          $x2 = 50 + 38 * cos($angle);
          $y2 = 50 + 38 * sin($angle);
          $spokeLines .= '<line x1="50" y1="50" x2="' . round($x2, 2) . '" y2="' . round($y2, 2) . '" stroke="#c9ccd2" stroke-width="2"/>';
        }
        $imagePath = !empty($rim['image']) ? '../public/assets/images/rims/' . rawurlencode($rim['image']) : null;
      ?>
      <div class="rim-card">
        <div class="rim-thumb">
          <?php if ($imagePath): ?>
            <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($rim['name']) ?>" loading="lazy">
          <?php else: ?>
            <svg viewBox="0 0 100 100">
              <circle cx="50" cy="50" r="46" fill="none" stroke="#d5d8de" stroke-width="2"/>
              <?= $spokeLines ?>
              <circle cx="50" cy="50" r="38" fill="none" stroke="#c9ccd2" stroke-width="1.5"/>
              <circle cx="50" cy="50" r="9" fill="var(--white)" stroke="var(--red)" stroke-width="1.5"/>
            </svg>
          <?php endif; ?>
        </div>
        <div class="rim-info">
          <h3><?= htmlspecialchars($rim['name']) ?></h3>
          <div class="rim-spokes"><?= $spokeCount ?>-Spoke Design</div>
          <div class="rim-desc"><?= htmlspecialchars($rim['desc']) ?></div>
          <a class="btn btn-red btn-select-rim"
             href="user-3dpreview.php?rim=<?= urlencode($rim['file']) ?>&name=<?= urlencode($rim['name']) ?>">
            Select This Rim →
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</body>
</html>
