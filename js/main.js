/* ================================================================
   NEx-gEN School of Computers — main.js
   Vanilla JS: Hero slider, testimonials, gallery filter,
   lightbox, stats counter, scroll effects, contact form.
   ================================================================ */

(function () {
  'use strict';

  /* ── HELPER ──────────────────────────────────────────────────── */
  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

  /* ── NAVBAR — sticky + hamburger ────────────────────────────── */
  const navbar    = $('#navbar');
  const hamburger = $('#hamburger');
  const navMenu   = $('#nav-menu');

  window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.scrollY > 60);
    document.getElementById('backToTop')
      .classList.toggle('visible', window.scrollY > 400);
  });

  hamburger.addEventListener('click', () => {
    hamburger.classList.toggle('open');
    navMenu.classList.toggle('open');
  });

  /* Close mobile menu on link click */
  $$('.nav-link', navMenu).forEach(link => {
    link.addEventListener('click', () => {
      hamburger.classList.remove('open');
      navMenu.classList.remove('open');
    });
  });

  /* Active nav link on scroll */
  const sections = $$('section[id]');
  const navLinks  = $$('.nav-link');

  function setActiveNav() {
    const scrollY = window.scrollY + 80;
    sections.forEach(sec => {
      if (scrollY >= sec.offsetTop && scrollY < sec.offsetTop + sec.offsetHeight) {
        navLinks.forEach(l => l.classList.remove('active'));
        const match = $(`a[href="#${sec.id}"]`, navMenu);
        if (match) match.classList.add('active');
      }
    });
  }
  window.addEventListener('scroll', setActiveNav, { passive: true });

  /* ── HERO SLIDER ─────────────────────────────────────────────── */
  const heroSlides = $$('.hero__slide');
  const heroDots   = $$('.hero__dot', document.getElementById('heroDots'));
  let heroIndex    = 0;
  let heroTimer;

  function showHeroSlide(idx) {
    heroSlides[heroIndex].classList.remove('hero__slide--active');
    heroDots[heroIndex].classList.remove('hero__dot--active');
    heroIndex = (idx + heroSlides.length) % heroSlides.length;
    heroSlides[heroIndex].classList.add('hero__slide--active');
    heroDots[heroIndex].classList.add('hero__dot--active');
  }

  function startHeroAuto() {
    heroTimer = setInterval(() => showHeroSlide(heroIndex + 1), 5000);
  }

  $('#heroNext').addEventListener('click', () => { clearInterval(heroTimer); showHeroSlide(heroIndex + 1); startHeroAuto(); });
  $('#heroPrev').addEventListener('click', () => { clearInterval(heroTimer); showHeroSlide(heroIndex - 1); startHeroAuto(); });

  heroDots.forEach(dot => {
    dot.addEventListener('click', () => {
      clearInterval(heroTimer);
      showHeroSlide(parseInt(dot.dataset.index));
      startHeroAuto();
    });
  });

  startHeroAuto();

  /* ── STATS COUNTER ───────────────────────────────────────────── */
  const statNums   = $$('.stat-number');
  let statsDone    = false;

  function animateStats() {
    if (statsDone) return;
    const statsSection = $('.stats');
    const rect = statsSection.getBoundingClientRect();
    if (rect.top < window.innerHeight - 80) {
      statsDone = true;
      statNums.forEach(el => {
        const target = parseInt(el.dataset.target);
        const duration = 1600;
        const step = Math.ceil(target / (duration / 16));
        let current = 0;
        const timer = setInterval(() => {
          current = Math.min(current + step, target);
          el.textContent = current.toLocaleString('en-IN');
          if (current >= target) clearInterval(timer);
        }, 16);
      });
    }
  }
  window.addEventListener('scroll', animateStats, { passive: true });
  animateStats();

  /* ── COURSE INTEREST FILTER ─────────────────────────────────── */
  const intBtns     = $$('.int-btn');
  const courseCards = $$('.cc');

  intBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      intBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const filter = btn.dataset.filter;
      courseCards.forEach(card => {
        const show = filter === 'all' || card.dataset.cat === filter;
        card.classList.toggle('hidden', !show);
      });
    });
  });

  /* ── GALLERY FILTER ──────────────────────────────────────────── */
  const filterBtns  = $$('.gallery__filter-btn');
  const galleryItems = $$('.gallery__item');

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const filter = btn.dataset.filter;
      galleryItems.forEach(item => {
        const show = filter === 'all' || item.dataset.cat === filter;
        item.classList.toggle('hidden', !show);
      });
    });
  });

  /* ── LIGHTBOX ────────────────────────────────────────────────── */
  const lightbox       = $('#lightbox');
  const lightboxImg    = $('#lightboxImg');
  const lightboxCaption = $('#lightboxCaption');
  const lightboxClose  = $('#lightboxClose');

  galleryItems.forEach(item => {
    item.addEventListener('click', () => {
      const img = $('img', item);
      lightboxImg.src = img.src;
      lightboxImg.alt = img.alt;
      lightboxCaption.textContent = img.alt;
      lightbox.classList.add('open');
      document.body.style.overflow = 'hidden';
    });
  });

  function closeLightbox() {
    lightbox.classList.remove('open');
    document.body.style.overflow = '';
  }
  lightboxClose.addEventListener('click', closeLightbox);
  lightbox.addEventListener('click', e => { if (e.target === lightbox) closeLightbox(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

  /* ── TESTIMONIALS SLIDER ─────────────────────────────────────── */
  const testCards = $$('.testimonial-card');
  const testDots  = $$('#testimonialDots .hero__dot');
  let testIndex   = 0;
  let testTimer;

  function showTestimonial(idx) {
    testCards[testIndex].classList.remove('testimonial-card--active');
    testDots[testIndex].classList.remove('hero__dot--active');
    testIndex = (idx + testCards.length) % testCards.length;
    testCards[testIndex].classList.add('testimonial-card--active');
    testDots[testIndex].classList.add('hero__dot--active');
  }

  testDots.forEach(dot => {
    dot.addEventListener('click', () => {
      clearInterval(testTimer);
      showTestimonial(parseInt(dot.dataset.index));
      testTimer = setInterval(() => showTestimonial(testIndex + 1), 5500);
    });
  });

  testTimer = setInterval(() => showTestimonial(testIndex + 1), 5500);

  /* ── CONTACT FORM ────────────────────────────────────────────── */
  const form        = $('#contactForm');
  const formSuccess = $('#formSuccess');

  /*
   * GOOGLE SHEETS INTEGRATION
   * ─────────────────────────────────────────────────────────────
   * Step 1: Open your Google Sheet → Extensions → Apps Script
   * Step 2: Paste the Apps Script code from DEPLOYMENT.md
   * Step 3: Deploy as Web App → Copy the URL
   * Step 4: Replace the placeholder below with your deployed URL
   * ─────────────────────────────────────────────────────────────
   */
  const SHEETS_URL = 'https://script.google.com/macros/s/AKfycbz2MusvO6Tg98j72NFGjie_yJlLBPJZwvH91GKy1xUmc9fsrVhiPz6lFfRkHe_VwOrYBA/exec';

  form.addEventListener('submit', e => {
    e.preventDefault();

    const name  = $('#fname').value.trim();
    const phone = $('#fphone').value.trim();

    if (!name || !phone) {
      alert('Please fill in your Name and Phone number.');
      return;
    }

    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = 'Sending… <i class="fa-solid fa-spinner fa-spin"></i>';

    const data = {
      name:    name,
      phone:   phone,
      email:   $('#femail').value.trim(),
      course:  $('#fcourse').value,
      message: $('#fmessage').value.trim(),
      date:    new Date().toLocaleString('en-IN', { timeZone: 'Asia/Kolkata' })
    };

    fetch(SHEETS_URL, {
      method:  'POST',
      mode:    'no-cors',          /* Apps Script requires no-cors */
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(data)
    })
    .then(() => {
      form.reset();
      btn.disabled = false;
      btn.innerHTML = 'Send Enquiry <i class="fa-solid fa-paper-plane"></i>';
      formSuccess.classList.add('show');
      setTimeout(() => formSuccess.classList.remove('show'), 5000);
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = 'Send Enquiry <i class="fa-solid fa-paper-plane"></i>';
      alert('Could not send. Please call us at +91 63010 12437.');
    });
  });

  /* ── BACK TO TOP ─────────────────────────────────────────────── */
  $('#backToTop').addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  /* ── FOOTER YEAR ─────────────────────────────────────────────── */
  const fy = document.getElementById('footerYear');
  if (fy) fy.textContent = new Date().getFullYear();

  /* ── SMOOTH SCROLL for all anchor links ─────────────────────── */
  $$('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', e => {
      const targetId = anchor.getAttribute('href').slice(1);
      const target   = document.getElementById(targetId);
      if (target) {
        e.preventDefault();
        const offset = parseInt(getComputedStyle(document.documentElement)
          .getPropertyValue('--navbar-h')) || 72;
        const top = target.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top, behavior: 'smooth' });
      }
    });
  });

  /* ── SCROLL-IN ANIMATION (Intersection Observer) ────────────── */
  const animEls = $$('.cc, .feature-card, .gallery__item, .stat-card, .about__grid, .certification__grid');

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  animEls.forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(24px)';
    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(el);
  });

  /* ── DEMO BUTTONS — inject into each course card ────────────────── */
  $$('.cc[data-course]').forEach(card => {
    const bd = card.querySelector('.cc__bd');
    if (!bd) return;
    const btn = document.createElement('button');
    btn.className = 'cc__demo';
    btn.innerHTML = '<i class="fa-solid fa-circle-play"></i> Watch Demo Class';
    btn.addEventListener('click', () => openDemo(card.dataset.course, card.dataset.video || ''));
    bd.insertBefore(btn, bd.querySelector('.cc__cta'));
  });

})();

/* ================================================================
   DEMO MODAL — YouTube IFrame API
   ================================================================ */

/* ── Load YouTube IFrame API ── */
(function () {
  const tag = document.createElement('script');
  tag.src = 'https://www.youtube.com/iframe_api';
  document.head.appendChild(tag);
})();

let ytPlayer = null;
let ytReady  = false;

/* Called automatically by YouTube API when ready */
function onYouTubeIframeAPIReady() {
  ytReady = true;
}

/* ── Open demo modal ── */
function openDemo(courseName, videoId) {
  const modal    = document.getElementById('demoModal');
  const title    = document.getElementById('demoTitle');
  const player   = document.getElementById('demoPlayer');
  const cta      = document.getElementById('demoCTA');
  const ctaTitle = document.getElementById('demoCTATitle');
  const ctaMsg   = document.getElementById('demoCTAMsg');
  const enrollBtn = document.getElementById('demoEnrollBtn');

  title.textContent = courseName + ' — Course Demo';
  cta.classList.remove('show');
  modal.classList.add('open');
  document.body.style.overflow = 'hidden';

  /* Destroy previous player */
  if (ytPlayer) { try { ytPlayer.destroy(); } catch(e) {} ytPlayer = null; }

  if (videoId) {
    /* Create a fresh placeholder div for YT to replace */
    player.innerHTML = '<div id="ytPlayerEl"></div>';

    const tryCreate = () => {
      if (!ytReady) { setTimeout(tryCreate, 200); return; }
      ytPlayer = new YT.Player('ytPlayerEl', {
        videoId,
        width: '100%',
        height: '100%',
        playerVars: { autoplay: 1, rel: 0, modestbranding: 1, controls: 1 },
        events: {
          onStateChange: function (e) {
            if (e.data === YT.PlayerState.ENDED) {
              ctaTitle.textContent = '🎉 Liked what you saw?';
              ctaMsg.textContent   = 'Enroll now and start your ' + courseName + ' journey today!';
              enrollBtn.href       = '#contact';
              cta.classList.add('show');
            }
          },
        },
      });
    };
    tryCreate();
  } else {
    /* No video yet — show coming-soon card */
    player.innerHTML = `
      <div class="demo-no-video">
        <div class="demo-no-video__icon">🎬</div>
        <h4>${courseName} Demo</h4>
        <p>Our demo video for this course is being prepared.<br>Book a FREE live demo class with us right now!</p>
        <a href="https://wa.me/916301012437?text=Hi%2C%20I%20want%20to%20book%20a%20demo%20class%20for%20${encodeURIComponent(courseName)}"
           target="_blank" rel="noopener" class="btn btn--primary">
          <i class="fa-brands fa-whatsapp"></i> Book Free Demo
        </a>
      </div>`;
    /* Show enroll CTA immediately */
    ctaTitle.textContent = 'Interested in ' + courseName + '?';
    ctaMsg.textContent   = 'Fill in your details and our counsellor will call you back!';
    enrollBtn.href       = '#contact';
    cta.classList.add('show');
  }
}

/* ── Close demo modal ── */
function closeDemo() {
  const modal  = document.getElementById('demoModal');
  const player = document.getElementById('demoPlayer');
  modal.classList.remove('open');
  document.body.style.overflow = '';
  if (ytPlayer) { try { ytPlayer.stopVideo(); } catch(e) {} }
  setTimeout(() => { player.innerHTML = ''; }, 350);
}

document.getElementById('demoClose').addEventListener('click', closeDemo);
document.getElementById('demoBackdrop').addEventListener('click', closeDemo);
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDemo(); });

/* Close and scroll to contact when Enroll Now is clicked */
document.getElementById('demoEnrollBtn').addEventListener('click', function () {
  closeDemo();
});

