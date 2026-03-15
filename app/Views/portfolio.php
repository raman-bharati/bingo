<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc($site['name']) ?> | <?= esc($site['role']) ?></title>
  <meta name="description" content="Single-page portfolio for <?= esc($site['name']) ?>, featuring projects, experience, and a direct link to a featured game.">
  <meta name="author" content="<?= esc($site['name']) ?>">
  <link rel="canonical" href="<?= base_url('/') ?>">
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?= esc($site['name']) ?> | <?= esc($site['role']) ?>">
  <meta property="og:description" content="Explore projects, experience, and the featured game built by <?= esc($site['name']) ?>.">
  <meta property="og:url" content="<?= base_url('/') ?>">
  <meta property="og:site_name" content="<?= esc($site['name']) ?> Portfolio">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= esc($site['name']) ?> | <?= esc($site['role']) ?>">
  <meta name="twitter:description" content="Explore projects, experience, and the featured game built by <?= esc($site['name']) ?>.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/portfolio.css?v=<?= file_exists(ROOTPATH . 'public/portfolio.css') ? (string) filemtime(ROOTPATH . 'public/portfolio.css') : '0' ?>">
</head>
<body>
  <div class="portfolio-shell">
    <header class="hero">
      <div class="hero__copy">
        <p class="eyebrow">Portfolio starter</p>
        <h1><?= esc($site['name']) ?></h1>
        <p class="hero__role"><?= esc($site['role']) ?> based in <?= esc($site['location']) ?></p>
        <p class="hero__tagline"><?= esc($site['tagline']) ?></p>
        <div class="hero__actions">
          <a class="button button--primary" href="<?= esc($site['gameUrl']) ?>" target="_blank" rel="noopener noreferrer">Play my game</a>
          <a class="button button--secondary" href="#projects">View projects</a>
        </div>
      </div>
      <aside class="hero__panel" aria-label="Quick profile details">
        <p class="panel__label">Quick facts</p>
        <dl class="facts">
          <div>
            <dt>Focus</dt>
            <dd>Web apps, APIs, and polished product delivery</dd>
          </div>
          <div>
            <dt>Availability</dt>
            <dd>Open for freelance, product, or full-time roles</dd>
          </div>
          <div>
            <dt>Contact</dt>
            <dd><a href="mailto:<?= esc($site['email']) ?>"><?= esc($site['email']) ?></a></dd>
          </div>
        </dl>
      </aside>
    </header>

    <main class="portfolio-content">
      <!-- Replace the placeholder copy below with your own personal introduction. -->
      <section class="section" id="about">
        <div class="section__heading">
          <p class="section__eyebrow">About</p>
          <h2>A concise snapshot of who you are and how you work.</h2>
        </div>
        <div class="section__body section__body--single">
          <p><?= esc($about) ?></p>
        </div>
      </section>

      <section class="section" id="skills">
        <div class="section__heading">
          <p class="section__eyebrow">Skills</p>
          <h2>Core tools and strengths.</h2>
        </div>
        <div class="chip-grid" aria-label="Skills list">
          <?php foreach ($skills as $skill): ?>
            <span class="chip"><?= esc($skill) ?></span>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- Keep one card for the game so visitors can reach it quickly. -->
      <section class="section" id="projects">
        <div class="section__heading">
          <p class="section__eyebrow">Projects</p>
          <h2>Selected work with room for case studies or live demos.</h2>
        </div>
        <div class="card-grid">
          <?php foreach ($projects as $project): ?>
            <article class="card">
              <p class="card__eyebrow">Project</p>
              <h3><?= esc($project['title']) ?></h3>
              <p><?= esc($project['summary']) ?></p>
              <a class="card__link" href="<?= esc($project['link']) ?>" target="_blank" rel="noopener noreferrer"><?= esc($project['linkLabel']) ?></a>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="section section--split" id="experience">
        <div class="section__heading">
          <p class="section__eyebrow">Experience</p>
          <h2>Professional highlights.</h2>
        </div>
        <div class="timeline">
          <?php foreach ($experience as $item): ?>
            <article class="timeline__item">
              <p class="timeline__period"><?= esc($item['period']) ?></p>
              <h3><?= esc($item['title']) ?></h3>
              <p><?= esc($item['description']) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="section section--split" id="education">
        <div class="section__heading">
          <p class="section__eyebrow">Education</p>
          <h2>Academic background and certifications.</h2>
        </div>
        <div class="timeline">
          <?php foreach ($education as $item): ?>
            <article class="timeline__item">
              <p class="timeline__period"><?= esc($item['period']) ?></p>
              <h3><?= esc($item['title']) ?></h3>
              <p><?= esc($item['description']) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="section" id="contact">
        <div class="section__heading">
          <p class="section__eyebrow">Contact</p>
          <h2>Make it easy for people to reach you.</h2>
        </div>
        <div class="contact-grid">
          <a class="contact-card" href="<?= esc($site['githubUrl']) ?>" target="_blank" rel="noopener noreferrer">
            <span class="contact-card__label">GitHub</span>
            <span class="contact-card__value"><?= esc($site['githubUrl']) ?></span>
          </a>
          <a class="contact-card" href="<?= esc($site['linkedinUrl']) ?>" target="_blank" rel="noopener noreferrer">
            <span class="contact-card__label">LinkedIn</span>
            <span class="contact-card__value"><?= esc($site['linkedinUrl']) ?></span>
          </a>
          <a class="contact-card" href="mailto:<?= esc($site['email']) ?>">
            <span class="contact-card__label">Email</span>
            <span class="contact-card__value"><?= esc($site['email']) ?></span>
          </a>
          <a class="contact-card" href="<?= esc($site['resumeUrl']) ?>" target="_blank" rel="noopener noreferrer">
            <span class="contact-card__label">Resume</span>
            <span class="contact-card__value">Replace with your resume URL</span>
          </a>
        </div>
      </section>
    </main>
  </div>
</body>
</html>