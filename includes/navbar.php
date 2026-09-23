<?php
/* ═══════════════════════════════════════════════════════
   NEXT ACADEMY — SEO ENGINE
   Dynamic meta, OG, JSON-LD, canonical, keywords
   ═══════════════════════════════════════════════════════ */

// --- Page-specific SEO config ---
$current_file = basename($_SERVER['PHP_SELF']);
$site_name = 'Next Academy';
$site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$canonical = $site_url . $_SERVER['REQUEST_URI'];
$og_image = $site_url . '../assets/images/Next-logo.png';
$site_phone = '+91 97379 49789';
$site_email = 'nextacademy89@gmail.com';
$site_address = 'City Mall-2, SF14, Navjivan Bazar Road, Kalol, Gujarat 382721, India';

// Base keywords used on every page
$base_keywords = 'IT courses, web development course, full stack development, React course, PHP course, programming classes, coding institute, software development training, online coding courses, best IT institute, IT training in India, web development course in Kalol, web development course in Gujarat, coding classes in Kalol, computer class Kalol, Next Academy, Next Academy IT Courses, Next Academy Web Development, Next Academy Kalol';

// Per-page SEO data
$seo = [];
switch ($current_file) {
    case 'index.php':
        $seo['title'] = 'Best IT Courses & Web Development Training | Next Academy Kalol';
        $seo['description'] = 'Next Academy — Best coding institute in Kalol, Gujarat. Learn web development, MERN stack, React, PHP, full stack development with hands-on projects. Industry-ready IT training with placement assistance.';
        $seo['keywords'] = $base_keywords . ', best coding institute, MERN stack course, Node.js training, learn programming Kalol';
        break;
    case 'courses.php':
        $seo['title'] = 'Professional IT Courses — Web Development, React, PHP | Next Academy';
        $seo['description'] = 'Explore professional IT courses at Next Academy: Full Stack Web Development, MERN Stack, React.js, PHP, Digital Marketing, Graphic Design. Flexible batches, affordable fees, expert instructors.';
        $seo['keywords'] = $base_keywords . ', MERN stack course, frontend development, backend development, JavaScript course, HTML CSS course, website development training';
        break;
    case 'about.php':
        $seo['title'] = 'About Next Academy — Best IT Training Institute in Kalol, Gujarat';
        $seo['description'] = 'Next Academy is a premier IT skill development institute in Kalol, Gujarat. Expert instructors, practical training, industry certifications, and career support for aspiring developers.';
        $seo['keywords'] = $base_keywords . ', about Next Academy, IT institute Gujarat, coding school, programming training center, learn to code India';
        break;
    case 'contact.php':
        $seo['title'] = 'Contact Next Academy — Enroll for IT Courses in Kalol, Gujarat';
        $seo['description'] = 'Contact Next Academy for IT course inquiries, demo classes, and enrollment. Visit us at Kalol, Gujarat or call +91 97379 49789. Free demo classes available!';
        $seo['keywords'] = $base_keywords . ', contact Next Academy, IT course enrollment, demo class, admission, coding institute contact';
        break;
    case 'digital-marketing.php':
        $seo['title'] = 'Digital Marketing Course in Kalol | AI-Powered Training | Next Academy';
        $seo['description'] = 'Learn Digital Marketing with AI at Next Academy, Kalol. Practical training in SEO, Social Media, Meta Campaigns, Google Business, Canva, CapCut & more. Expert, Pro & Advance plans from ₹18,500. Morning & evening batches available.';
        $seo['keywords'] = $base_keywords . ', digital marketing course Kalol, digital marketing classes Kalol, digital marketing training Kalol, AI digital marketing, SEO course Kalol, social media marketing course, Meta Ads course, Google Ads Kalol';
        break;
    case 'gallery.php':
        $seo['title'] = 'Gallery — Next Academy Campus, Events & Student Projects';
        $seo['description'] = 'View photos of Next Academy campus, student projects, events and classroom sessions. See our state-of-the-art IT training facility in Kalol, Gujarat.';
        $seo['keywords'] = $base_keywords . ', Next Academy gallery, campus photos, student projects, IT institute photos';
        break;
    default:
        $seo['title'] = isset($page_title) ? $page_title : 'Next Academy — Best IT Courses & Coding Institute in Kalol';
        $seo['description'] = 'Next Academy offers best IT courses including web development, full stack, React, PHP training in Kalol, Gujarat. Hands-on learning with expert instructors.';
        $seo['keywords'] = $base_keywords;
        break;
}
// Allow page to override
if (isset($page_meta_title))
    $seo['title'] = $page_meta_title;
if (isset($page_meta_description))
    $seo['description'] = $page_meta_description;
if (isset($page_meta_keywords))
    $seo['keywords'] = $page_meta_keywords;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- ══ PRIMARY META ══ -->
    <title><?php echo htmlspecialchars($seo['title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($seo['keywords']); ?>">
    <meta name="author" content="Next Academy">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical); ?>">

    <!-- ══ OPEN GRAPH (Facebook / LinkedIn) ══ -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo $site_name; ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($seo['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonical); ?>">
    <meta property="og:image" content="<?php echo $og_image; ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="en_IN">

    <!-- ══ TWITTER CARD ══ -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($seo['title']); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <meta name="twitter:image" content="<?php echo $og_image; ?>">

    <!-- ══ GEO / LOCATION META ══ -->
    <meta name="geo.region" content="IN-GJ">
    <meta name="geo.placename" content="Kalol, Gujarat">
    <meta name="geo.position" content="23.2414;72.5008">
    <meta name="ICBM" content="23.2414, 72.5008">

    <!-- ══ JSON-LD STRUCTURED DATA ══ -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "EducationalOrganization",
        "name": "Next Academy",
        "alternateName": "Next Academy Kalol",
        "url": "<?php echo $site_url; ?>",
        "logo": "<?php echo $og_image; ?>",
        "image": "<?php echo $og_image; ?>",
        "description": "Next Academy is the best IT training institute in Kalol, Gujarat offering professional courses in Web Development, MERN Stack, React.js, PHP, Digital Marketing, and Graphic Design with hands-on project-based learning.",
        "telephone": "<?php echo $site_phone; ?>",
        "email": "<?php echo $site_email; ?>",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "City Mall-2, SF14, Navjivan Bazar Road",
            "addressLocality": "Kalol",
            "addressRegion": "Gujarat",
            "postalCode": "382721",
            "addressCountry": "IN"
        },
        "geo": {
            "@type": "GeoCoordinates",
            "latitude": 23.2414,
            "longitude": 72.5008
        },
        "openingHours": "Mo-Sa 09:30-20:00",
        "priceRange": "₹₹",
        "sameAs": [],
        "hasOfferCatalog": {
            "@type": "OfferCatalog",
            "name": "IT Training Courses",
            "itemListElement": [
                {
                    "@type": "Course",
                    "name": "Full Stack Web Development",
                    "description": "Complete full stack web development course covering HTML, CSS, JavaScript, React, Node.js, Express, MongoDB",
                    "provider": { "@type": "Organization", "name": "Next Academy" },
                    "educationalLevel": "Beginner to Advanced",
                    "courseMode": "Offline"
                },
                {
                    "@type": "Course",
                    "name": "MERN Stack Development",
                    "description": "Master MongoDB, Express.js, React.js and Node.js with real-world projects",
                    "provider": { "@type": "Organization", "name": "Next Academy" },
                    "educationalLevel": "Intermediate",
                    "courseMode": "Offline"
                },
                {
                    "@type": "Course",
                    "name": "PHP & MySQL Development",
                    "description": "Learn PHP backend development with MySQL database management and real project work",
                    "provider": { "@type": "Organization", "name": "Next Academy" },
                    "educationalLevel": "Beginner to Intermediate",
                    "courseMode": "Offline"
                },
                {
                    "@type": "Course",
                    "name": "React.js Frontend Development",
                    "description": "Build modern, fast single-page applications with React.js, Redux, and Next.js",
                    "provider": { "@type": "Organization", "name": "Next Academy" },
                    "educationalLevel": "Intermediate",
                    "courseMode": "Offline"
                }
            ]
        },
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.8",
            "reviewCount": "150",
            "bestRating": "5"
        }
    }
    </script>

    <?php if ($current_file === 'index.php'): ?>
        <!-- WebSite schema for sitelinks search box -->
        <script type="application/ld+json">
                                    {
                                        "@context": "https://schema.org",
                                        "@type": "WebSite",
                                        "name": "Next Academy",
                                        "url": "<?php echo $site_url; ?>",
                                        "potentialAction": {
                                            "@type": "SearchAction",
                                            "target": "<?php echo $site_url; ?>/courses.php?q={search_term_string}",
                                            "query-input": "required name=search_term_string"
                                        }
                                    }
                                    </script>
    <?php endif; ?>

    <?php if ($current_file === 'contact.php'): ?>
        <!-- FAQ Schema for Contact page -->
        <script type="application/ld+json">
                                    {
                                        "@context": "https://schema.org",
                                        "@type": "FAQPage",
                                        "mainEntity": [
                                            {
                                                "@type": "Question",
                                                "name": "What are the course timings at Next Academy?",
                                                "acceptedAnswer": {
                                                    "@type": "Answer",
                                                    "text": "We offer flexible batch timings including morning (9 AM - 12 PM) and evening (5 PM - 8 PM) batches. Weekend batches are also available for working professionals."
                                                }
                                            },
                                            {
                                                "@type": "Question",
                                                "name": "Does Next Academy provide placement assistance?",
                                                "acceptedAnswer": {
                                                    "@type": "Answer",
                                                    "text": "Yes, we provide comprehensive placement assistance including resume building, interview preparation, and connecting you with our network of hiring partners."
                                                }
                                            },
                                            {
                                                "@type": "Question",
                                                "name": "Can I get a free demo class at Next Academy?",
                                                "acceptedAnswer": {
                                                    "@type": "Answer",
                                                    "text": "Absolutely! We offer free demo classes for all our courses. Contact us at +91 97379 49789 to schedule your demo class at a convenient time."
                                                }
                                            }
                                        ]
                                    }
                                    </script>
    <?php endif; ?>

    <!-- ══ PRECONNECT FOR PERFORMANCE ══ -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">

 
    <link rel="stylesheet" href="assets/css/august-calendar-slide.css">

    <!-- Favicon -->
    <link rel="icon" type="icon" href="favicon.ico">

    <!-- ══ ADDITIONAL SEO: Preload hero image for LCP ══ -->
    <?php if ($current_file === 'index.php'): ?>
        <link rel="preload" as="image" href="assets/images/slider-logo.png">
        <link rel="preload" as="image" href="assets/images/robot.jpg">
    <?php endif; ?>
</head>

<body>

    <?php if ($current_file === 'index.php'): ?>
        <?php include __DIR__ . '/splash-screen.php'; ?>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════
         FLOATING ACTION BUTTON (FAB) NAVIGATION
         ══════════════════════════════════════════════ -->
    <nav class="fab-nav" id="fabNav" aria-label="Main navigation">
        <!-- Backdrop overlay when menu is open -->
        <div class="fab-nav__backdrop" id="fabBackdrop"></div>

        <!-- Menu items (appear vertically upward) -->
        <div class="fab-nav__menu" id="fabMenu">
            <a href="https://wa.me/919898740777?text=Hi%20Next%20Academy!%20I'm%20interested%20in%20your%20courses."
                class="fab-nav__item fab-nav__item--whatsapp" data-tooltip="WhatsApp" target="_blank" rel="noopener">
                <i class="fab fa-whatsapp"></i>
            </a>
            <a href="contact.php" class="fab-nav__item" data-tooltip="Contact">
                <i class="fas fa-phone-alt"></i>
            </a>
            <a href="about.php" class="fab-nav__item" data-tooltip="About Us">
                <i class="fas fa-user-graduate"></i>
            </a>

            <a href="gallery.php" class="fab-nav__item" data-tooltip="Gallery">
                <i class="fas fa-images"></i>
            </a>
            <a href="courses.php" class="fab-nav__item" data-tooltip="Courses">
                <i class="fas fa-graduation-cap"></i>
            </a>
            <a href="index.php" class="fab-nav__item" data-tooltip="Home">
                <i class="fas fa-home"></i>
            </a>
            <a href="auth/login.php" class="fab-nav__item fab-nav__item--login" data-tooltip="Login">
                <i class="fas fa-sign-in-alt"></i>
            </a>
        </div>

        <!-- Main FAB toggle button -->
        <button class="fab-nav__toggle" id="fabToggle" aria-label="Toggle navigation menu" aria-expanded="false">
            <span class="fab-nav__icon">
                <span class="fab-nav__bar"></span>
                <span class="fab-nav__bar"></span>
                <span class="fab-nav__bar"></span>
            </span>
        </button>
    </nav>

    <!-- FAB Navigation Styles -->
    <style>
        /* ═══ FAB Navigation ═══ */
        .fab-nav {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 11;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .navbarz {
            z-index: 9999999999;
        }

        /* Backdrop */
        .fab-nav__backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.35s ease, visibility 0.35s ease;
            z-index: -1;
        }

        .fab-nav.is-open .fab-nav__backdrop {
            opacity: 1;
            visibility: visible;
        }

        /* Main Toggle Button */
        .fab-nav__toggle {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(135deg, #7c3aed, #5b21b6);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow:
                0 6px 24px rgba(124, 58, 237, 0.45),
                0 2px 8px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1),
                box-shadow 0.3s ease,
                background 0.3s ease;
            position: relative;
            z-index: 10;
        }

        .fab-nav__toggle:hover {
            transform: scale(1.1);
            box-shadow:
                0 8px 30px rgba(124, 58, 237, 0.55),
                0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .fab-nav__toggle:active {
            transform: scale(0.95);
        }

        /* Hamburger Icon */
        .fab-nav__icon {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 18px;
            position: relative;
        }

        .fab-nav__bar {
            display: block;
            width: 22px;
            height: 2.5px;
            background: white;
            border-radius: 2px;
            position: absolute;
            transition: all 0.35s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        }

        .fab-nav__bar:nth-child(1) {
            top: 0;
        }

        .fab-nav__bar:nth-child(2) {
            top: 50%;
            transform: translateY(-50%);
        }

        .fab-nav__bar:nth-child(3) {
            bottom: 0;
        }

        /* X animation */
        .fab-nav.is-open .fab-nav__bar:nth-child(1) {
            top: 50%;
            transform: translateY(-50%) rotate(45deg);
        }

        .fab-nav.is-open .fab-nav__bar:nth-child(2) {
            opacity: 0;
            transform: translateY(-50%) scaleX(0);
        }

        .fab-nav.is-open .fab-nav__bar:nth-child(3) {
            bottom: auto;
            top: 50%;
            transform: translateY(-50%) rotate(-45deg);
        }

        .fab-nav.is-open .fab-nav__toggle {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow:
                0 6px 24px rgba(239, 68, 68, 0.45),
                0 2px 8px rgba(0, 0, 0, 0.15);
        }

        /* Menu Container */
        .fab-nav__menu {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            margin-bottom: 16px;
            pointer-events: none;
        }

        .fab-nav.is-open .fab-nav__menu {
            pointer-events: auto;
        }

        /* Menu Items */
        .fab-nav__item {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            text-decoration: none;
            box-shadow:
                0 4px 16px rgba(0, 0, 0, 0.12),
                0 1px 4px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1),
                opacity 0.3s ease,
                box-shadow 0.3s ease,
                background 0.3s ease,
                color 0.3s ease;
            opacity: 0;
            transform: scale(0.3) translateY(20px);
            position: relative;
        }

        .fab-nav__item:hover {
            transform: scale(1.15) !important;
            box-shadow:
                0 6px 24px rgba(124, 58, 237, 0.3),
                0 2px 8px rgba(0, 0, 0, 0.12);
            background: #7c3aed;
            color: white;
        }

        /* WhatsApp special */
        .fab-nav__item--whatsapp {
            background: #25D366;
            color: white;
        }

        .fab-nav__item--whatsapp:hover {
            background: #128C7E;
            color: white;
        }

        /* Login special */
        .fab-nav__item--login {
            background: linear-gradient(135deg, #7c3aed, #5b21b6);
            color: white;
        }

        .fab-nav__item--login:hover {
            background: linear-gradient(135deg, #6d28d9, #4c1d95);
            color: white;
        }

        /* Staggered show animation */
        .fab-nav.is-open .fab-nav__item {
            opacity: 1;
            transform: scale(1) translateY(0);
        }

        .fab-nav.is-open .fab-nav__item:nth-child(1) {
            transition-delay: 0.04s;
        }

        .fab-nav.is-open .fab-nav__item:nth-child(2) {
            transition-delay: 0.08s;
        }

        .fab-nav.is-open .fab-nav__item:nth-child(3) {
            transition-delay: 0.12s;
        }

        .fab-nav.is-open .fab-nav__item:nth-child(4) {
            transition-delay: 0.16s;
        }

        .fab-nav.is-open .fab-nav__item:nth-child(5) {
            transition-delay: 0.20s;
        }

        .fab-nav.is-open .fab-nav__item:nth-child(6) {
            transition-delay: 0.24s;
        }

        .fab-nav.is-open .fab-nav__item:nth-child(7) {
            transition-delay: 0.28s;
        }

        .fab-nav.is-open .fab-nav__item:nth-child(8) {
            transition-delay: 0.32s;
        }

        /* Close stagger (reverse) */
        .fab-nav:not(.is-open) .fab-nav__item:nth-child(8) {
            transition-delay: 0.02s;
        }

        .fab-nav:not(.is-open) .fab-nav__item:nth-child(7) {
            transition-delay: 0.04s;
        }

        .fab-nav:not(.is-open) .fab-nav__item:nth-child(6) {
            transition-delay: 0.06s;
        }

        .fab-nav:not(.is-open) .fab-nav__item:nth-child(5) {
            transition-delay: 0.08s;
        }

        .fab-nav:not(.is-open) .fab-nav__item:nth-child(4) {
            transition-delay: 0.10s;
        }

        .fab-nav:not(.is-open) .fab-nav__item:nth-child(3) {
            transition-delay: 0.12s;
        }

        .fab-nav:not(.is-open) .fab-nav__item:nth-child(2) {
            transition-delay: 0.14s;
        }

        .fab-nav:not(.is-open) .fab-nav__item:nth-child(1) {
            transition-delay: 0.16s;
        }

        /* Tooltips (Desktop) */
        .fab-nav__item::before {
            content: attr(data-tooltip);
            position: absolute;
            right: calc(100% + 14px);
            top: 50%;
            transform: translateY(-50%) translateX(8px);
            background: #1e293b;
            color: white;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s ease;
            pointer-events: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .fab-nav__item::after {
            content: '';
            position: absolute;
            right: calc(100% + 6px);
            top: 50%;
            transform: translateY(-50%);
            border: 5px solid transparent;
            border-left-color: #1e293b;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
            pointer-events: none;
        }

        .fab-nav__item:hover::before {
            opacity: 1;
            visibility: visible;
            transform: translateY(-50%) translateX(0);
        }

        .fab-nav__item:hover::after {
            opacity: 1;
            visibility: visible;
        }

        /* ═══ FAB Responsive ═══ */
        @media (max-width: 768px) {
            .fab-nav {
                bottom: 20px;
                right: 20px;
            }

            .fab-nav__toggle {
                width: 56px;
                height: 56px;
            }

            .fab-nav__item {
                width: 46px;
                height: 46px;
                font-size: 1.05rem;
            }

            .fab-nav__menu {
                gap: 12px;
                margin-bottom: 14px;
            }

            /* Hide tooltips on touch devices */
            .fab-nav__item::before,
            .fab-nav__item::after {
                display: none;
            }
        }

        @media (max-width: 400px) {
            .fab-nav {
                bottom: 16px;
                right: 16px;
            }

            .fab-nav__toggle {
                width: 52px;
                height: 52px;
            }

            .fab-nav__item {
                width: 42px;
                height: 42px;
                font-size: 0.95rem;
            }

            .fab-nav__menu {
                gap: 10px;
                margin-bottom: 12px;
            }
        }

        /* Pulse animation for attention */
        @keyframes fab-pulse {
            0% {
                box-shadow: 0 6px 24px rgba(124, 58, 237, 0.45), 0 0 0 0 rgba(124, 58, 237, 0.4);
            }

            70% {
                box-shadow: 0 6px 24px rgba(124, 58, 237, 0.45), 0 0 0 15px rgba(124, 58, 237, 0);
            }

            100% {
                box-shadow: 0 6px 24px rgba(124, 58, 237, 0.45), 0 0 0 0 rgba(124, 58, 237, 0);
            }
        }

        .fab-nav:not(.is-open) .fab-nav__toggle {
            animation: fab-pulse 2.5s infinite;
        }

        .fab-nav.is-open .fab-nav__toggle {
            animation: none;
        }
    </style>

    <!-- FAB Navigation JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const fabNav = document.getElementById('fabNav');
            const fabToggle = document.getElementById('fabToggle');
            const fabBackdrop = document.getElementById('fabBackdrop');

            // Toggle menu
            fabToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                fabNav.classList.toggle('is-open');
                this.setAttribute('aria-expanded', fabNav.classList.contains('is-open'));
            });

            // Close on backdrop click
            fabBackdrop.addEventListener('click', function () {
                fabNav.classList.remove('is-open');
                fabToggle.setAttribute('aria-expanded', 'false');
            });

            // Close on Escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && fabNav.classList.contains('is-open')) {
                    fabNav.classList.remove('is-open');
                    fabToggle.setAttribute('aria-expanded', 'false');
                }
            });

            // Close after clicking a menu item (except WhatsApp which opens new tab)
            document.querySelectorAll('.fab-nav__item:not([target="_blank"])').forEach(function (item) {
                item.addEventListener('click', function () {
                    fabNav.classList.remove('is-open');
                    fabToggle.setAttribute('aria-expanded', 'false');
                });
            });
        });
    </script>