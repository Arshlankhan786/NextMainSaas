/* ═══════════════════════════════════════════════════════
   NEXT ACADEMY — DIGITAL MARKETING v2 JS
   Scroll reveals, scroll typography, FAQ, skill hover
   ═══════════════════════════════════════════════════════ */

(function () {
    'use strict';

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ─── Scroll Reveal ─── */
    function initReveal() {
        var els = document.querySelectorAll('.dm-r');
        if (!els.length) return;

        if (reducedMotion) {
            els.forEach(function (el) { el.classList.add('is-v'); });
            return;
        }

        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    e.target.classList.add('is-v');
                    obs.unobserve(e.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        els.forEach(function (el) { obs.observe(el); });
    }

    /* ─── Scroll Typography ─── */
    function initScrollTypo() {
        var lines = document.querySelectorAll('.dm-scroll__line');
        if (!lines.length) return;

        if (reducedMotion) {
            lines.forEach(function (l) { l.classList.add('is-v'); });
            return;
        }

        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    e.target.classList.add('is-v');
                    obs.unobserve(e.target);
                }
            });
        }, { threshold: 0.3, rootMargin: '0px 0px -60px 0px' });

        lines.forEach(function (l) { obs.observe(l); });
    }

    /* ─── FAQ ─── */
    function initFAQ() {
        var items = document.querySelectorAll('.dm-faq__item');
        items.forEach(function (item) {
            var q = item.querySelector('.dm-faq__q');
            if (!q) return;
            q.addEventListener('click', function () {
                var open = item.classList.contains('is-open');
                items.forEach(function (other) {
                    if (other !== item) other.classList.remove('is-open');
                });
                item.classList.toggle('is-open', !open);
            });
        });
    }

    /* ─── Skill hover (mobile tap) ─── */
    function initSkills() {
        var skills = document.querySelectorAll('.dm-skill');
        if (!skills.length) return;

        // Mobile: tap to toggle active state
        if (!window.matchMedia('(hover: hover)').matches) {
            skills.forEach(function (sk) {
                sk.addEventListener('click', function () {
                    var isActive = sk.classList.contains('is-active');
                    skills.forEach(function (other) { other.classList.remove('is-active'); });
                    if (!isActive) sk.classList.add('is-active');
                });
            });
        }
    }

    /* ─── Smooth scroll for anchor links ─── */
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                var target = document.querySelector(a.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    }

    /* ─── Init ─── */
    document.addEventListener('DOMContentLoaded', function () {
        initReveal();
        initScrollTypo();
        initFAQ();
        initSkills();
        initSmoothScroll();
    });
})();
