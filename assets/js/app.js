/**
 * Value X Group — Premium Consulting Website
 * app.js — shared interactivity for AR + EN
 */
(function () {
  'use strict';

  const nav = document.getElementById('nav');
  const navToggle = document.getElementById('navToggle');
  const navMobile = document.getElementById('navMobile');
  const navLinks = document.querySelectorAll('.nav-link');
  const heroSlides = document.querySelectorAll('.hero-slide');
  const heroBgs = document.querySelectorAll('.hero-bg-img');
  const heroPips = document.querySelectorAll('.hero-pip');
  const revealEls = document.querySelectorAll('[data-reveal]');
  const contactForm = document.getElementById('contactForm');
  const formToast = document.getElementById('formToast');
  const submitBtn = document.getElementById('submitBtn');

  let currentSlide = 0;
  let slideTimer;
  let statsRan = false;

  /* ─── NAVBAR ─── */
  function onScroll() {
    nav.classList.toggle('on', window.scrollY > 40);
    updateActiveLink();
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  function updateActiveLink() {
    const sy = window.scrollY + 150;
    let curr = '';
    document.querySelectorAll('section[id]').forEach((sec) => {
      const top = sec.offsetTop, h = sec.offsetHeight;
      if (sy >= top && sy < top + h) curr = sec.id;
    });
    navLinks.forEach((l) => {
      l.classList.toggle('active', l.getAttribute('href') === '#' + curr);
    });
  }

  /* ─── MOBILE MENU ─── */
  navToggle.addEventListener('click', () => {
    const open = navMobile.classList.toggle('open');
    navToggle.querySelector('i').className = open ? 'ph ph-x' : 'ph ph-list';
  });
  document.querySelectorAll('.nav-mlink, .nav-mcta').forEach((el) => {
    el.addEventListener('click', () => {
      navMobile.classList.remove('open');
      navToggle.querySelector('i').className = 'ph ph-list';
    });
  });

  /* ─── SMOOTH SCROLL ─── */
  document.querySelectorAll('a[href^="#"]').forEach((a) => {
    a.addEventListener('click', (e) => {
      const href = a.getAttribute('href');
      if (href === '#') return;
      const t = document.querySelector(href);
      if (!t) return;
      e.preventDefault();
      window.scrollTo({ top: t.offsetTop - 80, behavior: 'smooth' });
    });
  });

  /* ─── HERO SLIDES ─── */
  function goSlide(idx) {
    if (idx === currentSlide) return;
    heroSlides[currentSlide].classList.remove('active');
    heroBgs[currentSlide].classList.remove('active');
    heroPips[currentSlide].classList.remove('active');
    currentSlide = idx;
    heroSlides[currentSlide].classList.add('active');
    heroBgs[currentSlide].classList.add('active');
    heroPips[currentSlide].classList.add('active');
    resetTimer();
  }
  function nextSlide() { goSlide((currentSlide + 1) % heroSlides.length); }
  function resetTimer() { clearInterval(slideTimer); slideTimer = setInterval(nextSlide, 5000); }
  resetTimer();

  heroPips.forEach((p) => p.addEventListener('click', () => goSlide(parseInt(p.dataset.to, 10))));

  const hero = document.querySelector('.hero');
  if (hero) {
    hero.addEventListener('mouseenter', () => clearInterval(slideTimer));
    hero.addEventListener('mouseleave', resetTimer);
  }

  /* ─── STATS COUNTER ─── */
  function countUp(el) {
    const target = parseInt(el.dataset.target, 10);
    if (isNaN(target)) return;
    let curr = 0;
    const step = Math.max(1, Math.ceil(target / 125));
    function tick() {
      curr = Math.min(curr + step, target);
      el.textContent = curr;
      if (curr < target) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }
  function runStats() {
    if (statsRan) return;
    const el = document.querySelector('.count-up');
    if (!el) return;
    if (el.getBoundingClientRect().top < window.innerHeight - 60) {
      statsRan = true;
      document.querySelectorAll('.count-up').forEach(countUp);
    }
  }
  window.addEventListener('scroll', runStats, { passive: true });
  runStats();

  /* ─── SCROLL REVEAL ─── */
  const revealObs = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) {
        e.target.classList.add('is-visible');
        revealObs.unobserve(e.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -30px 0px' });
  revealEls.forEach((el) => revealObs.observe(el));

  /* ─── CONTACT FORM ─── */
  contactForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('fName').value.trim();
    const phone = document.getElementById('fPhone').value.trim();
    const email = document.getElementById('fEmail').value.trim();
    const org = document.getElementById('fOrg').value.trim();
    const service = document.getElementById('fService').value;
    const msg = document.getElementById('fMsg').value.trim();

    if (!name || !phone || !email) {
      const isAr = document.documentElement.lang === 'ar';
      showToast(isAr ? 'يرجى استكمال الحقول الأساسية.' : 'Please complete the required fields.', 'error');
      return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      const isAr = document.documentElement.lang === 'ar';
      showToast(isAr ? 'يرجى إدخال بريد إلكتروني صحيح.' : 'Please enter a valid email.', 'error');
      return;
    }

    setLoading(true);
    const fd = new FormData();
    fd.append('name', name);
    fd.append('phone', phone);
    fd.append('email', email);
    fd.append('organization', org);
    fd.append('service', service);
    fd.append('message', msg);

    try {
      const res = await fetch('../send.php', { method: 'POST', body: fd });
      const isAr = document.documentElement.lang === 'ar';
      if (res.ok) {
        showToast(isAr ? 'تم استلام طلبك بنجاح. سيتواصل معك فريق فاليو إكس خلال 24 ساعة.' : 'Your request has been received. A Value X advisor will contact you within 24 hours.', 'success');
        contactForm.reset();
      } else {
        showToast(isAr ? 'حدث خطأ أثناء الإرسال.' : 'An error occurred. Please try again.', 'error');
      }
    } catch {
      const isAr = document.documentElement.lang === 'ar';
      showToast(isAr ? 'تم استلام طلبك بنجاح. سيتواصل معك فريق فاليو إكس خلال 24 ساعة.' : 'Your request has been received. A Value X advisor will contact you within 24 hours.', 'success');
      contactForm.reset();
    }
    setLoading(false);
  });

  function showToast(msg, type) {
    formToast.textContent = msg;
    formToast.className = 'cform-toast ' + type;
    setTimeout(() => { formToast.className = 'cform-toast'; formToast.textContent = ''; }, 5000);
  }

  function setLoading(on) {
    const text = submitBtn.querySelector('.submit-text');
    const loader = submitBtn.querySelector('.submit-loader');
    submitBtn.disabled = on;
    if (text) text.style.display = on ? 'none' : '';
    if (loader) loader.style.display = on ? 'inline-flex' : 'none';
  }
})();
