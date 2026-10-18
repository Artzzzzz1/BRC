<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Motorcycle Rim Powder Coating</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    .hero-tag {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(204,0,0,0.15);
      border: 1px solid rgba(204,0,0,0.4);
      border-radius: 20px;
      padding: 6px 16px;
      font-size: 0.78rem;
      font-weight: 600;
      color: #ff6b6b;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      margin-bottom: 20px;
    }
    .hero-tag span {
      width: 6px; height: 6px;
      background: #ff6b6b;
      border-radius: 50%;
      animation: pulse 1.5s ease-in-out infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50%       { opacity: 0.4; transform: scale(0.8); }
    }
    .service-tag {
      display: inline-block;
      font-size: 0.7rem;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 4px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 10px;
    }
    .tag-main   { background: #fff0f0; color: var(--red); }
    .tag-other  { background: #f0f4ff; color: #2563eb; }
    .tag-borrow { background: #f5f0ff; color: #7c3aed; }

    .process-section {
      padding: 80px 48px;
      background: var(--black);
      color: white;
      text-align: center;
    }
    .process-section h2 {
      font-size: 1.7rem;
      font-weight: 700;
      margin-bottom: 12px;
    }
    .process-section > p {
      color: #aaa;
      font-size: 0.9rem;
      margin-bottom: 48px;
    }
    .process-steps {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 0;
      max-width: 900px;
      margin: 0 auto;
      position: relative;
    }
    .process-steps::before {
      content: '';
      position: absolute;
      top: 24px;
      left: 10%;
      right: 10%;
      height: 2px;
      background: rgba(204,0,0,0.3);
    }
    .process-step {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 12px;
      position: relative;
      z-index: 1;
    }
    .process-num {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: var(--red);
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.2rem;
      color: white;
      letter-spacing: 0.04em;
    }
    .process-step h4 {
      font-size: 0.82rem;
      font-weight: 700;
    }
    .process-step p {
      font-size: 0.72rem;
      color: #888;
      line-height: 1.5;
    }

    @media (max-width: 900px) {
      .process-steps { grid-template-columns: repeat(3, 1fr); gap: 28px; }
      .process-steps::before { display: none; }
    }
    @media (max-width: 600px) {
      .process-section { padding: 60px 20px; }
      .process-steps { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

  <!-- NAVBAR -->
  <nav>
    <a href="index.php" class="nav-brand"><img src="assets/images/logo-wide.jpg" alt="BRC Bossride Customs" class="nav-logo-wide"></a>
    <ul class="nav-links">
      <li><a href="index.php" class="active">Home</a></li>
      <li><a href="about.html">About Us</a></li>
      <li><a href="services.html">Our Services</a></li>
      <li><a href="contact.html">Contact Us</a></li>
      <li><a href="faqs.html">FAQs</a></li>
    </ul>
    <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
      <span></span><span></span><span></span>
    </button>
    <a href="login.php" class="btn-login desktop-only">Login/Register</a>
  </nav>
  <div class="mobile-nav" id="mobileNav">
    <a href="index.php" class="active">Home</a>
    <a href="about.html">About Us</a>
    <a href="services.html">Our Services</a>
    <a href="contact.html">Contact Us</a>
    <a href="faqs.html">FAQs</a>
    <a href="login.php" class="btn-login">Login / Register</a>
  </div>


  <!-- HERO -->
  <section class="hero">
    <div class="hero-tag"><span></span> Motorcycle Specialists</div>
    <h1>Give Your Motorcycle Rims a Fresh New Look</h1>
    <p>Professional Powder Coating & Rim Services — Calamba, Laguna</p>
    <div class="hero-btns">
      <a href="login.php" class="btn-primary">Book Now</a>
      <a href="login.php" class="btn-outline">Try 3D Preview</a>
    </div>
  </section>

  <!-- WHY CHOOSE BRC -->
  <section class="why">
    <h2>Why Choose BRC Calamba?</h2>
    <div class="features">

      <div class="feature-card">
        <div class="icon-circle">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <h3>Easy Online Booking</h3>
        <p>Schedule your motorcycle rim service appointment in just a few clicks</p>
      </div>

      <div class="feature-card">
        <div class="icon-circle">
          <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        </div>
        <h3>Real-Time Job Tracking</h3>
        <p>Monitor your rim's powder coating progress step by step</p>
      </div>

      <div class="feature-card">
        <div class="icon-circle">
          <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
        <h3>3D Color Preview</h3>
        <p>Visualize powder coat colors on your motorcycle rims before booking</p>
      </div>

      <div class="feature-card">
        <div class="icon-circle">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <h3>Live Queue Status</h3>
        <p>Check your real-time queue position and estimated wait time</p>
      </div>

    </div>
  </section>

  <!-- POWDER COATING PROCESS -->
  <section class="process-section">
    <h2>Our Powder Coating Process</h2>
    <p>Every rim goes through our proven 5-step process for a durable, long-lasting finish</p>
    <div class="process-steps">
      <div class="process-step">
        <div class="process-num">1</div>
        <h4>Rim Received</h4>
        <p>We inspect and log your motorcycle rims</p>
      </div>
      <div class="process-step">
        <div class="process-num">2</div>
        <h4>Stripping</h4>
        <p>Old coating and rust are fully removed</p>
      </div>
      <div class="process-step">
        <div class="process-num">3</div>
        <h4>Powder Coating</h4>
        <p>Electrostatic powder is applied evenly</p>
      </div>
      <div class="process-step">
        <div class="process-num">4</div>
        <h4>Curing</h4>
        <p>Baked in oven for a hard, durable finish</p>
      </div>
      <div class="process-step">
        <div class="process-num">5</div>
        <h4>Ready for Pickup</h4>
        <p>Quality checked and returned to you</p>
      </div>
    </div>
  </section>

  <!-- OUR SERVICES -->
  <section class="services">
    <h2>Our Services</h2>
    <div class="service-cards">

      <div class="service-card">
        <span class="service-tag tag-main">Main Service</span>
        <h3>Powder Coating</h3>
        <p>Professional powder coating for motorcycle rims — available in matte, gloss, metallic, and custom colors. Durable and long-lasting finish.</p>
        <a href="login.php">Book Now →</a>
      </div>

      <div class="service-card">
        <span class="service-tag tag-other">Motorcycle Repair & Maintenance</span>
        <h3>Motorcycle Repair & Maintenance</h3>
        <p>We offer basic motorcycle repair and maintenance to ensure safety and proper performance before and after customization</p>
        <a href="login.php">Book Now →</a>
      </div>

      <div class="service-card">
        <span class="service-tag tag-borrow">Loaner Program</span>
        <h3>Rim Borrowing</h3>
        <p>Still in queue at closing time? Leave your rims with us and ride home on our loaner rims. Return within 7 days when your rims are ready.</p>
        <a href="login.php">Learn More →</a>
      </div>

    </div>
  </section>

  <!-- CTA -->
  <section class="cta">
    <h2>Ready to Upgrade Your Motorcycle Rims?</h2>
    <p>Join our growing list of satisfied riders who trust BRC Calamba</p>
    <a href="login.php" class="btn-primary">Get Started Today</a>
  </section>

  <script>
    const toggle = document.getElementById('navToggle');
    const mobileNav = document.getElementById('mobileNav');
    toggle.addEventListener('click', () => {
      mobileNav.classList.toggle('open');
    });
    document.addEventListener('click', (e) => {
      if (!toggle.contains(e.target) && !mobileNav.contains(e.target)) {
        mobileNav.classList.remove('open');
      }
    });
  </script>
</body>
</html>
