<?php
/**
 * Next Academy — Premium Cinematic Splash Screen
 * Shown once per session on the homepage only
 */
?>
<style id="splashStyles">
    /* ════════════════════════════════════════════════════════
   SPLASH SCREEN — Cinematic Brand Reveal
   GPU-Accelerated • 60 FPS • 4-Second Experience
   ════════════════════════════════════════════════════════ */

    .splash {
        position: fixed;
        inset: 0;
        z-index: 999999;
        background: #030308;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        perspective: 1200px;
    }

    /* Holographic scanline overlay */
    .splash::after {
        content: '';
        position: absolute;
        inset: 0;
        background: repeating-linear-gradient(0deg,
                transparent 0px,
                transparent 2px,
                rgba(255, 255, 255, 0.012) 2px,
                rgba(255, 255, 255, 0.012) 4px);
        pointer-events: none;
        z-index: 50;
    }

    /* ═══ Canvas Particle Layer ═══ */
    .splash__canvas {
        position: absolute;
        inset: 0;
        z-index: 2;
        will-change: contents;
    }

    /* ═══ Animated Digital Grid ═══ */
    .splash__grid {
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(rgba(106, 76, 255, 0.035) 1px, transparent 1px),
            linear-gradient(90deg, rgba(106, 76, 255, 0.035) 1px, transparent 1px);
        background-size: 60px 60px;
        opacity: 0;
        animation: spGridFade 0.8s ease 0.2s forwards, spGridMove 25s linear 0.2s infinite;
        z-index: 1;
    }

    /* ═══ Ambient Floating Orbs ═══ */
    .splash__orb {
        position: absolute;
        border-radius: 50%;
        filter: blur(60px);
        opacity: 0;
        z-index: 1;
        will-change: transform, opacity;
    }

    .splash__orb--1 {
        width: 350px;
        height: 350px;
        background: rgba(106, 76, 255, 0.1);
        top: 15%;
        left: 8%;
        animation: spOrbFloat 7s ease-in-out infinite, spFadeIn 1.5s ease 0.3s forwards;
    }

    .splash__orb--2 {
        width: 280px;
        height: 280px;
        background: rgba(255, 215, 0, 0.05);
        bottom: 15%;
        right: 10%;
        animation: spOrbFloat 8s ease-in-out 0.5s infinite, spFadeIn 1.5s ease 0.6s forwards;
    }

    .splash__orb--3 {
        width: 220px;
        height: 220px;
        background: rgba(147, 51, 234, 0.08);
        top: 55%;
        right: 25%;
        animation: spOrbFloat 6s ease-in-out 1s infinite, spFadeIn 1.5s ease 0.9s forwards;
    }

    /* ═══ Central Energy Beam ═══ */
    .splash__beam {
        position: absolute;
        left: 50%;
        top: 0;
        width: 2px;
        height: 0;
        transform: translateX(-50%);
        background: linear-gradient(to bottom,
                transparent 0%,
                rgba(106, 76, 255, 0.6) 15%,
                rgba(255, 215, 0, 0.35) 50%,
                rgba(106, 76, 255, 0.6) 85%,
                transparent 100%);
        box-shadow:
            0 0 15px rgba(106, 76, 255, 0.4),
            0 0 40px rgba(106, 76, 255, 0.2),
            0 0 80px rgba(106, 76, 255, 0.08);
        animation: spBeam 2s ease-in-out 0.4s forwards;
        z-index: 3;
        will-change: height, opacity;
    }

    /* Wide ambient glow around beam */
    .splash__beam::after {
        content: '';
        position: absolute;
        left: 50%;
        top: 0;
        width: 150px;
        height: 100%;
        transform: translateX(-50%);
        background: radial-gradient(ellipse at center,
                rgba(106, 76, 255, 0.06) 0%,
                transparent 70%);
    }

    /* ═══ Radial Light Burst ═══ */
    .splash__burst {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 250px;
        height: 250px;
        transform: translate(-50%, -55%) scale(0);
        border-radius: 50%;
        background: radial-gradient(circle,
                rgba(255, 215, 0, 0.25) 0%,
                rgba(106, 76, 255, 0.12) 35%,
                transparent 70%);
        opacity: 0;
        animation: spBurst 1s ease-out 2.3s forwards;
        z-index: 4;
        pointer-events: none;
    }

    /* ═══ Logo Stage — 3D Perspective Container ═══ */
    .splash__logo-stage {
        position: relative;
        z-index: 10;
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-top: -30px;
        perspective: 1200px;
    }

    /* Logo wrapper with 3D entrance transform */
    .splash__logo-wrap {
        position: relative;
        opacity: 0;
        transform: scale(0.2) rotateX(-20deg) translateZ(-400px);
        animation: spLogoEntrance 1.3s cubic-bezier(0.16, 1, 0.3, 1) 1.3s forwards;
        transform-style: preserve-3d;
        will-change: transform, opacity, filter;
    }

    /* Logo image */
    .splash__logo {
        display: block;
        max-width: 380px;
        width: 75vw;
        height: auto;
        filter:
            drop-shadow(0 0 25px rgba(106, 76, 255, 0.4)) drop-shadow(0 0 50px rgba(106, 76, 255, 0.15));
        position: relative;
        z-index: 2;
    }

    /* Pulsing glow ring behind logo */
    .splash__glow-ring {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 130%;
        height: 140%;
        transform: translate(-50%, -50%) scale(0.5);
        border-radius: 50%;
        background: radial-gradient(ellipse at center,
                rgba(106, 76, 255, 0.12) 0%,
                rgba(255, 215, 0, 0.04) 45%,
                transparent 70%);
        opacity: 0;
        animation: spGlowRing 1.5s ease-out 1.8s forwards;
        z-index: 1;
    }

    /* Golden light sweep across logo */
    .splash__light-sweep {
        position: absolute;
        top: -15%;
        left: -120%;
        width: 70%;
        height: 130%;
        background: linear-gradient(105deg,
                transparent 0%,
                transparent 30%,
                rgba(255, 215, 0, 0.2) 42%,
                rgba(255, 255, 255, 0.35) 50%,
                rgba(255, 215, 0, 0.2) 58%,
                transparent 70%,
                transparent 100%);
        transform: skewX(-15deg);
        opacity: 0;
        animation: spLightSweep 0.9s ease-in-out 2.5s forwards;
        z-index: 3;
        pointer-events: none;
    }

    /* ═══ Tagline: DESIGN • CODING • MULTIMEDIA ═══ */
    .splash__tagline {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-top: 35px;
        position: relative;
        z-index: 10;
        opacity: 0;
        transform: translateY(18px);
        animation: spTaglineIn 0.7s ease-out 2.6s forwards;
    }

    .splash__tag {
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        font-weight: 700;
        letter-spacing: 4.5px;
        color: rgba(255, 255, 255, 0.3);
        text-transform: uppercase;
        transition: color 0.5s ease, text-shadow 0.5s ease;
    }

    .splash__tag.is-glowing {
        color: #FFD700;
        text-shadow:
            0 0 8px rgba(255, 215, 0, 0.6),
            0 0 25px rgba(255, 215, 0, 0.25);
    }

    .splash__tag-dot {
        color: rgba(106, 76, 255, 0.5);
        font-size: 0.5rem;
        user-select: none;
    }

    /* ═══ Premium Loading Bar ═══ */
    .splash__loader {
        position: absolute;
        bottom: 11%;
        left: 50%;
        transform: translateX(-50%);
        width: 280px;
        z-index: 10;
        opacity: 0;
        animation: spFadeIn 0.5s ease 0.3s forwards;
    }

    .splash__loader-track {
        position: relative;
        height: 3px;
        background: rgba(255, 255, 255, 0.06);
        border-radius: 6px;
        overflow: hidden;
        border: 1px solid rgba(106, 76, 255, 0.12);
        box-shadow:
            0 0 20px rgba(106, 76, 255, 0.08),
            inset 0 0 8px rgba(0, 0, 0, 0.4);
    }

    .splash__loader-fill {
        position: absolute;
        top: 0;
        left: 0;
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, #6A4CFF, #9333ea, #c084fc, #FFD700);
        background-size: 200% 100%;
        border-radius: 6px;
        box-shadow:
            0 0 8px rgba(106, 76, 255, 0.5),
            0 0 25px rgba(106, 76, 255, 0.15);
        animation: spLoaderShimmer 2s linear infinite;
        transition: width 0.08s linear;
    }

    /* Glowing tip on the fill bar */
    .splash__loader-fill::after {
        content: '';
        position: absolute;
        right: -1px;
        top: -3px;
        width: 8px;
        height: 9px;
        background: #FFD700;
        border-radius: 50%;
        box-shadow:
            0 0 6px rgba(255, 215, 0, 0.9),
            0 0 18px rgba(255, 215, 0, 0.4);
        opacity: 0.9;
    }

    .splash__loader-text {
        text-align: center;
        margin-top: 14px;
        font-family: 'Inter', sans-serif;
        font-size: 0.72rem;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.3);
        letter-spacing: 3.5px;
    }

    /* ═══ KEYFRAME ANIMATIONS ═══ */

    @keyframes spGridFade {
        to {
            opacity: 1;
        }
    }

    @keyframes spGridMove {
        to {
            background-position: 60px 60px;
        }
    }

    @keyframes spFadeIn {
        to {
            opacity: 1;
        }
    }

    @keyframes spOrbFloat {

        0%,
        100% {
            transform: translateY(0) translateX(0);
        }

        33% {
            transform: translateY(-25px) translateX(12px);
        }

        66% {
            transform: translateY(15px) translateX(-18px);
        }
    }

    @keyframes spBeam {
        0% {
            height: 0;
            opacity: 0;
        }

        25% {
            height: 100vh;
            opacity: 1;
        }

        65% {
            height: 100vh;
            opacity: 0.7;
        }

        100% {
            height: 100vh;
            opacity: 0;
        }
    }

    @keyframes spLogoEntrance {
        0% {
            opacity: 0;
            transform: scale(0.2) rotateX(-20deg) translateZ(-400px);
            filter: blur(20px) brightness(2.5);
        }

        35% {
            opacity: 0.5;
            filter: blur(8px) brightness(1.8);
        }

        65% {
            opacity: 0.85;
            transform: scale(0.85) rotateX(-3deg) translateZ(-40px);
            filter: blur(2px) brightness(1.2);
        }

        100% {
            opacity: 1;
            transform: scale(1) rotateX(0deg) translateZ(0px);
            filter: blur(0px) brightness(1);
        }
    }

    @keyframes spGlowRing {
        0% {
            opacity: 0;
            transform: translate(-50%, -50%) scale(0.5);
        }

        60% {
            opacity: 0.9;
        }

        100% {
            opacity: 0.6;
            transform: translate(-50%, -50%) scale(1);
        }
    }

    /* @keyframes spLightSweep {
        0% {
            left: -120%;
            opacity: 1;
        }

        100% {
            left: 200%;
            opacity: 0.6;
        }
    } */

    @keyframes spBurst {
        0% {
            transform: translate(-50%, -55%) scale(0);
            opacity: 0.5;
        }

        100% {
            transform: translate(-50%, -55%) scale(5);
            opacity: 0;
        }
    }

    @keyframes spTaglineIn {
        0% {
            opacity: 0;
            transform: translateY(18px);
        }

        100% {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes spLoaderShimmer {
        0% {
            background-position: 0% 50%;
        }

        100% {
            background-position: 200% 50%;
        }
    }

    @keyframes spLogoFloat {

        0%,
        100% {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        50% {
            opacity: 1;
            transform: translateY(-10px) scale(1.01);
        }
    }

    @keyframes spFinalPulse {
        0% {
            opacity: 1;
            transform: scale(1);
            filter: brightness(1);
        }

        40% {
            opacity: 1;
            transform: scale(1.07);
            filter: brightness(1.4) drop-shadow(0 0 40px rgba(255, 215, 0, 0.5));
        }

        100% {
            opacity: 1;
            transform: scale(1);
            filter: brightness(1);
        }
    }

    @keyframes spGlowPulse {

        0%,
        100% {
            opacity: 0.5;
            transform: translate(-50%, -50%) scale(1);
        }

        50% {
            opacity: 0.8;
            transform: translate(-50%, -50%) scale(1.08);
        }
    }

    /* Splash exit */
    .splash--exiting {
        animation: spExit 0.7s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    }

    @keyframes spExit {
        0% {
            opacity: 1;
            transform: scale(1);
        }

        100% {
            opacity: 0;
            transform: scale(1.03);
            visibility: hidden;
        }
    }

    /* ═══ RESPONSIVE ═══ */

    @media (max-width: 768px) {
        .splash__logo {
            max-width: 280px;
        }

        .splash__tagline {
            gap: 10px;
            margin-top: 26px;
        }

        .splash__tag {
            font-size: 0.65rem;
            letter-spacing: 3px;
        }

        .splash__loader {
            width: 220px;
            bottom: 14%;
        }

        .splash__logo-stage {
            margin-top: -15px;
        }

        .splash__burst {
            width: 180px;
            height: 180px;
        }
    }

    @media (max-width: 480px) {
        .splash__logo {
            max-width: 220px;
            width: 70vw;
        }

        .splash__tagline {
            gap: 7px;
            margin-top: 22px;
        }

        .splash__tag {
            font-size: 0.55rem;
            letter-spacing: 2px;
        }

        .splash__tag-dot {
            font-size: 0.4rem;
        }

        .splash__loader {
            width: 180px;
            bottom: 16%;
        }

        .splash__loader-text {
            font-size: 0.65rem;
            letter-spacing: 2.5px;
        }

        .splash__burst {
            width: 140px;
            height: 140px;
        }
    }

    /* ═══ Reduced Motion ═══ */
    @media (prefers-reduced-motion: reduce) {

        .splash__canvas,
        .splash__beam,
        .splash__burst,
        .splash__orb,
        .splash__grid {
            display: none !important;
        }

        .splash__logo-wrap {
            animation-duration: 0.5s !important;
            animation-delay: 0.2s !important;
        }

        .splash__light-sweep {
            animation: none !important;
            display: none !important;
        }
    }
</style>

<!-- ════════════════════════════════════════
     SPLASH SCREEN HTML
     ════════════════════════════════════════ -->
<div id="splashScreen" class="splash" role="presentation" aria-hidden="true">
    <!-- Canvas for particle system -->
    <canvas id="splashCanvas" class="splash__canvas"></canvas>

    <!-- Animated digital grid -->
    <div class="splash__grid"></div>

    <!-- Ambient floating orbs -->
    <div class="splash__orb splash__orb--1"></div>
    <div class="splash__orb splash__orb--2"></div>
    <div class="splash__orb splash__orb--3"></div>

    <!-- Central energy beam -->
    <div class="splash__beam"></div>

    <!-- Radial light burst -->
    <div class="splash__burst"></div>

    <!-- Logo stage with 3D perspective -->
    <div class="splash__logo-stage">
        <div class="splash__logo-wrap" id="spLogoWrap">
            <img src="assets/images/slider-logo.png" alt="Next Academy" class="splash__logo" id="spLogo" />
            <div class="splash__glow-ring" id="spGlowRing"></div>
            <div class="splash__light-sweep"></div>
        </div>

        <!-- Tagline -->
        <div class="splash__tagline">
            <span class="splash__tag" id="spTag1">DESIGN</span>
            <span class="splash__tag-dot">•</span>
            <span class="splash__tag" id="spTag2">CODING</span>
            <span class="splash__tag-dot">•</span>
            <span class="splash__tag" id="spTag3">MULTIMEDIA</span>
        </div>
    </div>

    <!-- Premium loading bar -->
    <div class="splash__loader">
        <div class="splash__loader-track">
            <div class="splash__loader-fill" id="spFill"></div>
        </div>
        <div class="splash__loader-text">
            <span id="spPercent">0</span>%
        </div>
    </div>
</div>

<script>
    (function () {
        'use strict';

        // ══ SESSION CHECK — Show splash only once per session ══
        if (sessionStorage.getItem('na_splash')) {
            var el = document.getElementById('splashScreen');
            if (el) el.remove();
            return;
        }

        // Reduced-motion shortcut
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.body.style.overflow = 'hidden';
            setTimeout(function () {
                var splash = document.getElementById('splashScreen');
                if (splash) {
                    splash.classList.add('splash--exiting');
                    setTimeout(function () {
                        document.body.style.overflow = '';
                        splash.remove();
                    }, 700);
                }
                sessionStorage.setItem('na_splash', '1');
            }, 1500);
            return;
        }

        // Lock body scroll
        document.body.style.overflow = 'hidden';

        // ══ CONFIGURATION ══
        var DURATION = 4000;
        var isMobile = window.innerWidth < 768;
        var PARTICLE_COUNT = isMobile ? 55 : 110;

        // ══ DOM ELEMENTS ══
        var splash = document.getElementById('splashScreen');
        var canvas = document.getElementById('splashCanvas');
        var ctx = canvas.getContext('2d');
        var fillEl = document.getElementById('spFill');
        var percentEl = document.getElementById('spPercent');
        var logoWrap = document.getElementById('spLogoWrap');
        var glowRing = document.getElementById('spGlowRing');
        var tags = [
            document.getElementById('spTag1'),
            document.getElementById('spTag2'),
            document.getElementById('spTag3')
        ];

        // ══ CANVAS SETUP ══
        var cW, cH;
        function resizeCanvas() {
            cW = canvas.width = window.innerWidth;
            cH = canvas.height = window.innerHeight;
        }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        // ══ PARTICLE COLORS ══
        var palette = [
            { r: 106, g: 76, b: 255 },  // electric purple
            { r: 124, g: 58, b: 237 },  // violet
            { r: 147, g: 51, b: 234 },  // deep violet
            { r: 255, g: 215, b: 0 },  // gold
            { r: 196, g: 181, b: 253 },  // light lavender
            { r: 255, g: 255, b: 255 },  // white
        ];

        // ══ CREATE PARTICLES ══
        var particles = [];
        for (var i = 0; i < PARTICLE_COUNT; i++) {
            var c = palette[Math.floor(Math.random() * palette.length)];
            particles.push({
                x: Math.random() * cW,
                y: Math.random() * cH,
                sz: Math.random() * 2.2 + 0.4,
                c: c,
                vx: (Math.random() - 0.5) * 1.4,
                vy: (Math.random() - 0.5) * 1.4,
                op: 0,
                maxOp: Math.random() * 0.55 + 0.2,
                ang: Math.random() * Math.PI * 2,
                oR: Math.random() * 200 + 50,
                oS: (Math.random() * 0.4 + 0.3) * (Math.random() < 0.5 ? 1 : -1) * 0.01,
                trail: []
            });
        }

        // ══ TIMING HELPERS ══
        var startTime = performance.now();
        var animId;
        var lastPct = -1;
        var floatingStarted = false;
        var pulseStarted = false;
        var glowPulseStarted = false;
        var tagStates = [false, false, false];
        var exiting = false;

        function easeInOutQuart(t) {
            return t < 0.5 ? 8 * t * t * t * t : 1 - Math.pow(-2 * t + 2, 4) / 2;
        }

        // ══ MAIN ANIMATION LOOP ══
        function animate(now) {
            var elapsed = now - startTime;
            var cx = cW / 2;
            var cy = cH / 2 - 30;

            // ── Clear canvas ──
            ctx.clearRect(0, 0, cW, cH);

            // ── Determine phase ──
            var phase, progress;
            if (elapsed < 500) { phase = 0; progress = elapsed / 500; }
            else if (elapsed < 1500) { phase = 1; progress = (elapsed - 500) / 1000; }
            else if (elapsed < 2500) { phase = 2; progress = (elapsed - 1500) / 1000; }
            else if (elapsed < 3500) { phase = 3; progress = (elapsed - 2500) / 1000; }
            else { phase = 4; progress = Math.min((elapsed - 3500) / 500, 1); }

            // ── Update & draw particles ──
            for (var i = 0; i < particles.length; i++) {
                var p = particles[i];

                if (phase === 0) {
                    // Float + fade in
                    p.op = Math.min(p.maxOp, p.op + 0.006);
                    p.x += p.vx * 0.35;
                    p.y += p.vy * 0.35;
                    if (p.x < 0) p.x += cW;
                    if (p.x > cW) p.x -= cW;
                    if (p.y < 0) p.y += cH;
                    if (p.y > cH) p.y -= cH;
                } else if (phase === 1) {
                    // Converge toward center with trails
                    p.op = p.maxOp;
                    var dx = cx - p.x;
                    var dy = cy - p.y;
                    var ease = 0.006 + progress * 0.04;
                    p.x += dx * ease;
                    p.y += dy * ease;
                    p.trail.push({ x: p.x, y: p.y });
                    if (p.trail.length > 10) p.trail.shift();
                } else if (phase === 2) {
                    // Orbit around logo
                    p.ang += p.oS;
                    p.oR *= 0.997;
                    p.x = cx + Math.cos(p.ang) * p.oR;
                    p.y = cy + Math.sin(p.ang) * p.oR * 0.55;
                    p.op = p.maxOp * (1 - progress * 0.35);
                    p.trail = [];
                } else if (phase === 3) {
                    // Slow orbit + fade
                    p.ang += p.oS * 0.4;
                    p.x = cx + Math.cos(p.ang) * p.oR;
                    p.y = cy + Math.sin(p.ang) * p.oR * 0.55;
                    p.op *= 0.993;
                } else {
                    // Phase 4: rapid fade
                    p.op *= 0.9;
                }

                // Draw trail (phase 1 only)
                if (p.trail.length > 2) {
                    for (var t = 0; t < p.trail.length - 1; t++) {
                        var trailOp = (t / p.trail.length) * p.op * 0.25;
                        ctx.beginPath();
                        ctx.moveTo(p.trail[t].x, p.trail[t].y);
                        ctx.lineTo(p.trail[t + 1].x, p.trail[t + 1].y);
                        ctx.strokeStyle = 'rgba(' + p.c.r + ',' + p.c.g + ',' + p.c.b + ',' + trailOp + ')';
                        ctx.lineWidth = p.sz * 0.4;
                        ctx.stroke();
                    }
                }

                if (p.op > 0.01) {
                    // Outer glow
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.sz * 4, 0, Math.PI * 2);
                    ctx.fillStyle = 'rgba(' + p.c.r + ',' + p.c.g + ',' + p.c.b + ',' + (p.op * 0.08) + ')';
                    ctx.fill();

                    // Core particle
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.sz, 0, Math.PI * 2);
                    ctx.fillStyle = 'rgba(' + p.c.r + ',' + p.c.g + ',' + p.c.b + ',' + p.op + ')';
                    ctx.fill();
                }
            }

            // ── Connection lines (desktop only, phases 1-2) ──
            if (!isMobile && (phase === 1 || phase === 2)) {
                ctx.lineWidth = 0.4;
                for (var a = 0; a < particles.length; a += 2) {
                    for (var b = a + 1; b < particles.length; b++) {
                        var ddx = particles[a].x - particles[b].x;
                        var ddy = particles[a].y - particles[b].y;
                        var dSq = ddx * ddx + ddy * ddy;
                        if (dSq < 7000) {
                            var dist = Math.sqrt(dSq);
                            var lineOp = (1 - dist / 84) * 0.1 * Math.min(particles[a].op, particles[b].op);
                            ctx.beginPath();
                            ctx.moveTo(particles[a].x, particles[a].y);
                            ctx.lineTo(particles[b].x, particles[b].y);
                            ctx.strokeStyle = 'rgba(106,76,255,' + lineOp + ')';
                            ctx.stroke();
                        }
                    }
                }
            }

            // ── Update loading bar ──
            var rawT = Math.min(elapsed / DURATION, 1);
            var easedT = easeInOutQuart(rawT);
            var pct = Math.min(Math.round(easedT * 100), 100);
            if (pct !== lastPct) {
                lastPct = pct;
                percentEl.textContent = pct;
                fillEl.style.width = pct + '%';
            }

            // ── Tagline glow sequence ──
            if (elapsed >= 2700 && !tagStates[0]) { tagStates[0] = true; tags[0].classList.add('is-glowing'); }
            if (elapsed >= 2950 && !tagStates[1]) { tagStates[1] = true; tags[1].classList.add('is-glowing'); }
            if (elapsed >= 3200 && !tagStates[2]) { tagStates[2] = true; tags[2].classList.add('is-glowing'); }

            // ── Logo floating after entrance completes ──
            if (elapsed >= 2700 && !floatingStarted) {
                floatingStarted = true;
                logoWrap.style.opacity = '1';
                logoWrap.style.animation = 'spLogoFloat 3s ease-in-out infinite';
            }

            // ── Glow ring continuous pulse ──
            if (elapsed >= 3200 && !glowPulseStarted) {
                glowPulseStarted = true;
                glowRing.style.animation = 'spGlowPulse 2s ease-in-out infinite';
            }

            // ── Final pulse ──
            if (elapsed >= 3400 && !pulseStarted) {
                pulseStarted = true;
                logoWrap.style.opacity = '1';
                logoWrap.style.animation = 'spFinalPulse 0.5s ease-in-out forwards';
            }

            // ── EXIT ──
            if (elapsed >= 3700 && !exiting) {
                exiting = true;
                splash.classList.add('splash--exiting');

                setTimeout(function () {
                    document.body.style.overflow = '';
                    splash.remove();
                    cancelAnimationFrame(animId);
                    window.removeEventListener('resize', resizeCanvas);
                    // Clean up references
                    canvas = null;
                    ctx = null;
                    particles = null;
                }, 700);

                sessionStorage.setItem('na_splash', '1');
                return;
            }

            animId = requestAnimationFrame(animate);
        }

        // ══ START ANIMATION ══
        animId = requestAnimationFrame(animate);
    })();
</script>