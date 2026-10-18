<?php
// config/rims.php
// Single source of truth for the 3D rim catalog.
// Used by userSide/user-rimselect.php (the picker) AND userSide/user-3dpreview.php (the viewer),
// so the two can never drift apart again.
//
// 'file'   => GLB filename inside BRC/public/
// 'name'   => display name
// 'image'  => thumbnail inside BRC/public/assets/images/rims/
// 'spokes' => used for the fallback SVG icon if the image is missing
// 'desc'   => short description
return [
  ['file' => 'Wheel_only.glb',       'name' => 'Curved 7-Spoke',  'image' => 'wheel.png',           'spokes' => 7,  'desc' => '7-spoke design with a clean, classic look.'],
  ['file' => '24.glb',               'name' => '24-Spoke Rim',    'image' => '24spoke.png',         'spokes' => 24, 'desc' => 'High spoke-count design for a fuller, custom look.'],
  ['file' => 'curved-6spoke.glb',    'name' => 'Curved 6-Spoke',  'image' => 'curved-6spoke.jpg',   'spokes' => 6,  'desc' => 'Sporty curved spokes for an aggressive stance.'],
  ['file' => 'ysplit-6spoke.glb',    'name' => 'Y-Split 5-Spoke', 'image' => 'ysplit-5spoke.jpg',   'spokes' => 6,  'desc' => 'Split Y-shaped spokes, sport bike styling.'],
  ['file' => 'swirl-7spoke.glb',     'name' => 'Swirl 7-Spoke',   'image' => 'swirl-7spoke.jpg',    'spokes' => 7,  'desc' => 'Pinwheel-style twisted spoke pattern.'],
  ['file' => 'classic-8spoke.glb',   'name' => 'Classic 8-Spoke', 'image' => 'classic-8spoke.jpg',  'spokes' => 8,  'desc' => 'Clean, straight 8-spoke design.'],
  ['file' => 'star-6spoke.glb',      'name' => 'Star 6-Spoke',    'image' => 'star-6spoke.jpg',     'spokes' => 6,  'desc' => 'Bold star pattern with visible lug detailing.'],
  ['file' => 'swept-5spoke.glb',     'name' => 'Swept 5-Spoke',   'image' => 'swept-5spoke.jpg',    'spokes' => 5,  'desc' => 'Wide, sweeping spokes for a bold, modern look.'],
  ['file' => 'racing-8spoke.glb',    'name' => 'Racing 8-Spoke',  'image' => 'racing-8spoke.jpg',   'spokes' => 8,  'desc' => 'Slim, lightweight spokes inspired by track racing wheels.'],
  ['file' => 'forked-3spoke.glb',    'name' => 'Modern 3-Spoke Aggressive racing rim', 'image' => 'forked-3spoke.png', 'spokes' => 3, 'desc' => 'Ideal for custom motorcycle builds, scooter upgrades, or track projects.'],
];
