<?php
// Include database connection
require_once 'admin/config/database.php';

// Fetch active courses with category information
$featured_courses = $conn->query("
    SELECT c.*, cat.name as category_name, 
           (SELECT MIN(fee_amount) FROM course_fees WHERE course_id = c.id) as min_fee,
           (SELECT MAX(duration_months) FROM course_fees WHERE course_id = c.id) as max_duration
    FROM courses c
    JOIN categories cat ON c.category_id = cat.id
    WHERE c.status = 'Active'
    ORDER BY c.created_at DESC
    LIMIT 6
");

// Get statistics
$stats = [];
$result = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'Active'");
$stats['students'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM courses WHERE status = 'Active'");
$stats['courses'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM categories WHERE status = 'Active'");
$stats['categories'] = $result->fetch_assoc()['count'];

$total_enrolled = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$completed = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'Completed'")->fetch_assoc()['count'];
$stats['success_rate'] = $total_enrolled > 0 ? round(($completed / $total_enrolled) * 100) : 95;

// ── Activity Calendar: fetch current month's events ──
$cal_month = date('Y-m');
$cal_month_name = date('F');
$cal_year = date('Y');
$cal_events_q = $conn->query("SELECT * FROM events WHERE DATE_FORMAT(event_date, '%Y-%m') = '$cal_month' AND status != 'Cancelled' ORDER BY event_date ASC");
$cal_events = [];
while ($ce = $cal_events_q->fetch_assoc()) $cal_events[] = $ce;
$cal_event_count = count($cal_events);
$has_calendar_events = $cal_event_count > 0;

$page_title = "Home - Next Academy";
?>
<?php include 'includes/navbar.php'; ?>

<!-- Hero Slider -->
<section class="hero-slider mt-0">
    <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">

        <div class="carousel-inner">

            <!-- Slide 0 - Activity Calendar (DB-driven) -->
            <?php if ($has_calendar_events): ?>
            <div class="carousel-item na-slide na-slide--aug-calendar active" role="img"
                aria-label="Next Academy <?= $cal_month_name ?> Activity Calendar <?= $cal_year ?>">
                <!-- Background -->
                <div class="na-slide__bg na-slide__bg--aug-calendar">
                    <div class="augc-orb augc-orb--1"></div>
                    <div class="augc-orb augc-orb--2"></div>
                    <div class="augc-orb augc-orb--3"></div>
                    <div class="augc-lightray"></div>
                    <div class="augc-particle augc-particle--1"></div>
                    <div class="augc-particle augc-particle--2"></div>
                    <div class="augc-particle augc-particle--3"></div>
                    <div class="augc-particle augc-particle--4"></div>
                </div>

                <!-- Top branding strip -->
                <div class="na-slide__top-strip na-slide__top-strip--aug-calendar">
                    <div class="na-slide__logo-area">
                        <img src="assets/images/slider-logo.png" alt="Next Academy Logo"
                            class="na-slide__logo-img na-slide__logo-img1">
                    </div>
                    <div class="na-slide__audience">
                        <span class="na-slide__audience-label">UPCOMING ACTIVITIES</span>
                        <span class="na-slide__badge na-slide__badge--yellow">MCQ</span>
                        <span class="na-slide__badge na-slide__badge--yellow">PROJECTS</span>
                        <span class="na-slide__audience-amp">&amp;</span>
                        <span class="na-slide__badge na-slide__badge--yellow">REWARDS</span>
                    </div>
                    <div class="na-slide__ai-academy">
                        <span class="na-slide__robot-icon"><img style="width: 50px;" src="assets/images/ai.png"
                                alt="AI"></span>
                        <span class="na-slide__ai-text">First AI Smart<br><strong>Academy in Kalol</strong></span>
                    </div>
                </div>

                <!-- Main content area -->
                <div class="augc-content pt-md-5">
                    <!-- LEFT SIDE -->
                    <div class="augc-left mt-md-5 ps-md-4">
                        <div class="augc-eyebrow">
                            <span class="augc-eyebrow__dot"></span>
                            <span class="augc-eyebrow__text"><?= $cal_month_name ?> <?= $cal_year ?> • Activity Calendar</span>
                        </div>

                        <h2 class="augc-heading">
                            <span class="augc-heading__line">
                                <span class="augc-heading__accent"><?= $cal_month_name ?></span> Activity
                            </span>
                            <span class="augc-heading__line">Calendar</span>
                            <span class="augc-heading__small">Learn <span>•</span> Participate <span>•</span> Enjoy <span>•</span> Grow</span>
                        </h2>

                        <p class="augc-subtitle">
                            A month filled with learning, confidence, creativity, fun and celebration at Next Academy. Join every activity and make <?= $cal_month_name ?> unforgettable!
                        </p>

                        <div class="augc-ctas">
                            <a href="contact.php" class="augc-btn-primary">
                                <i class="fas fa-calendar-alt"></i> View Activities
                            </a>
                        </div>

                        <div class="augc-info-badges">
                            <div class="augc-info-badge augc-info-badge--highlight">
                                <i class="fas fa-list-ol"></i> <?= $cal_event_count ?> Activities
                            </div>
                            <div class="augc-info-badge">
                                <i class="fas fa-calendar"></i> <?= $cal_month_name ?> <?= $cal_year ?>
                            </div>
                            <div class="augc-info-badge">
                                <i class="fas fa-users"></i> 100% Participation
                            </div>
                            <div class="augc-info-badge">
                                <i class="fas fa-award"></i> Rewards & Certificates
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT SIDE -->
                    <div class="augc-right pt-md-3">
                        <!-- Central Glow -->
                        <div class="augc-card-glow"></div>

                        <!-- Calendar Card -->
                        <div class="augc-calendar-card">
                            <div class="augc-calendar-card__header">
                                <div class="augc-calendar-card__month"><?= $cal_year ?></div>
                                <div class="augc-calendar-card__title">
                                    <span class="augc-calendar-card__icon"><i class="fas fa-calendar-alt"></i></span>
                                    <?= strtoupper($cal_month_name) ?> ACTIVITIES
                                </div>
                            </div>

                            <div class="augc-activity-list">
                                <?php foreach ($cal_events as $ce): ?>
                                <div class="augc-activity">
                                    <div class="augc-activity__num"><i class="<?= htmlspecialchars($ce['icon']) ?>"></i></div>
                                    <div class="augc-activity__info">
                                        <div class="augc-activity__name"><?= htmlspecialchars($ce['title']) ?></div>
                                        <div class="augc-activity__sub"><?= htmlspecialchars($ce['description'] ?? '') ?></div>
                                    </div>
                                    <div class="augc-activity__date"><?= strtoupper(date('d M', strtotime($ce['event_date']))) ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Floating Glass Cards (max 5) -->
                        <?php
                        $float_emojis = ['❓', '🎤', '👕', '🎬', '🏅'];
                        $float_max = min($cal_event_count, 5);
                        for ($fi = 0; $fi < $float_max; $fi++): ?>
                        <div class="augc-float-card augc-float-card--<?= $fi + 1 ?>">
                            <span class="augc-float-card__icon"><?= $float_emojis[$fi] ?? '⭐' ?></span>
                            <?= strtoupper(mb_strimwidth(htmlspecialchars($cal_events[$fi]['title']), 0, 12, '')) ?>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Bottom section -->
                <div class="augc-bottom mt-md-5">
                    <!-- Activities Overview Row -->
                    <div class="augc-activities-row mt-md-5">
                        <span class="augc-activities-row__title"><i class="fas fa-calendar-check"></i> This Month</span>
                        <div class="augc-activities-row__items">
                            <?php foreach ($cal_events as $ce): ?>
                            <span class="augc-activities-row__chip"><?= htmlspecialchars($ce['title']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Stats Row -->
                    <div class="augc-stats-row">
                        <div class="augc-stat-card">
                            <div class="augc-stat-icon"><i class="fas fa-list-ol"></i></div>
                            <div class="augc-stat-info">
                                <span class="augc-stat-value"><?= $cal_event_count ?> Activities</span>
                                <span class="augc-stat-label">Throughout <?= $cal_month_name ?></span>
                            </div>
                        </div>
                        <div class="augc-stat-card">
                            <div class="augc-stat-icon"><i class="fas fa-users"></i></div>
                            <div class="augc-stat-info">
                                <span class="augc-stat-value">100% Participation</span>
                                <span class="augc-stat-label">All Students Welcome</span>
                            </div>
                        </div>
                        <div class="augc-stat-card">
                            <div class="augc-stat-icon"><i class="fas fa-trophy"></i></div>
                            <div class="augc-stat-info">
                                <span class="augc-stat-value">Rewards</span>
                                <span class="augc-stat-label">Prizes & Recognition</span>
                            </div>
                        </div>
                        <div class="augc-stat-card">
                            <div class="augc-stat-icon"><i class="fas fa-certificate"></i></div>
                            <div class="augc-stat-info">
                                <span class="augc-stat-value">Certificates</span>
                                <span class="augc-stat-label">On Completion</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Slide 1 - Digital Marketing Pro -->
            <div class="carousel-item na-slide na-slide--fullstack <?= !$has_calendar_events ? 'active' : '' ?>" role="img"
                aria-label="Full Stack Development Pro Course at Next Academy Kalol">
                <!-- Background with grid pattern -->
                <div class="na-slide__bg na-slide__bg--fullstack"></div>

                <!-- Top branding strip -->
                <div class="na-slide__top-strip ">
                    <div class="na-slide__logo-area">
                        <img src="assets/images/slider-logo.png" alt="Next Academy Logo"
                            class="na-slide__logo-img fade-up">
                    </div>
                    <div class="na-slide__audience">
                        <span class="na-slide__audience-label">SPECIALLY CREATED FOR</span>
                        <span class="na-slide__badge na-slide__badge--yellow">12TH</span>
                        <span class="na-slide__badge na-slide__badge--yellow">BBA</span>
                        <span class="na-slide__audience-amp">&</span>
                        <span class="na-slide__badge na-slide__badge--yellow">BCOM</span>
                        <span class="na-slide__audience-label na-slide__audience-label--yellow">STUDENTS</span>
                    </div>
                    <div class="na-slide__ai-academy">
                        <span class="na-slide__robot-icon"><img style="width: 60px;" src="assets/images/ai.png"
                                alt=""></span>
                        <span class="na-slide__ai-text">First AI Smart<br><strong>Academy in Kalol</strong></span>
                    </div>

                </div>
                <!-- Main content area -->
                <div class="na-slide__content">
                    <!-- Left side: Heading -->
                    <div class="na-slide__left">
                        <h2 class="na-slide__heading ps-5">
                            <span class="na-slide__heading-line">Digital</span>
                            <span class="na-slide__heading-line">Marketing <span
                                    class="na-slide__heading-accent na-slide__heading-accent--cyan">Pro</span></span>
                        </h2>
                        <div class="na-slide__with-ai ps-5">
                            <span class="na-slide__with-text">WITH</span>
                            <span class=""><img src="assets/images/ai-m.png" alt="" style="width: 50px;"></span>
                        </div>
                        <div class="na-slide__batches ps-5">
                            <span class="na-slide__batches-title">Batches Available</span>
                            <div class="na-slide__batch-item"><img style="width: 20px;" src="assets/images/morning.png"
                                    alt=""> Morning ✅
                            </div>
                            <div class="na-slide__batch-item"><img style="width: 20px;" src="assets/images/evening.png"
                                    alt=""> Evening ✅
                            </div>
                        </div>
                    </div>

                    <!-- Center: Character -->
                    <div class="na-slide__character">
                        <div class="na-slide__character-glow na-slide__character-glow--cyan"></div>
                        <img src="assets/images/slide-1-man.png" alt="Full Stack Developer Instructor"
                            class="na-slide__character-img">
                    </div>

                    <!-- Right side: Module Card -->
                    <div class="na-slide__right">
                        <div class="na-slide__module-card">
                            <div class="na-slide__module-grid">
                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon">
                                        <!-- <span style="font-weight:900;font-size:1.3rem;"><</span> -->
                                        <img style="width: 100%;height: 100%;object-fit: cover;border-radius: 20px;"
                                            src="assets/images/photoshop.png" alt="">
                                    </div>
                                    <span><strong>Photoshop</strong></span>
                                </div>
                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon"><img
                                            style="width: 100%;height: 100%;object-fit: cover;border-radius: 20px;"
                                            src="assets/images/canva.png" alt="">
                                    </div>
                                    <span><strong>Canva</strong></span>
                                </div>
                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon">
                                        <img style="width: 100%;height: 100%;object-fit: contain;border-radius: 20px;"
                                            src="assets/images/video-marketing.png" />
                                    </div>
                                    <span><strong>Reel</strong><br>Creation</span>
                                </div>
                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon">
                                        <img style="width: 100%;height: 100%;object-fit: contain;border-radius: 20px;"
                                            src="assets/images/meta.png" />
                                    </div>
                                    <span><strong>Meta</strong><br>Campaign</span>
                                </div>
                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon">
                                        <img style="width: 100%;height: 100%;object-fit: contain;border-radius: 20px;"
                                            src="assets/images/instagram.png" />
                                    </div>

                                    <span><strong>Instagram</strong><br>Campaign</span>
                                </div>
                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon">
                                        <img style="width: 100%;height: 100%;object-fit: contain;border-radius: 20px;"
                                            src="assets/images/google-b.png" />
                                    </div>
                                    <span><strong>Google</strong><br>Business</span>
                                </div>
                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon">
                                        <img style="width: 100%;height: 100%;object-fit: contain;border-radius: 20px;"
                                            src="assets/images/google-ads.png" />
                                    </div>
                                    <span><strong>Google</strong><br>Ads</span>
                                </div>
                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon">
                                        <img style="width: 100%;height: 100%;object-fit: contain;border-radius: 20px;"
                                            src="assets/images/google-seo.png" />
                                    </div>
                                    <span><strong>Google</strong><br>SEO</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom section -->
                <div class="na-slide__bottom">
                    <!-- <div class="na-slide__pricing-row">
                        <div class="na-slide__duration">
                            <i class="fas fa-certificate"></i>
                            <span><strong>06 Months</strong><br>Certification Program</span>
                        </div>
                        <div class="na-slide__price">
                            <span class="na-slide__price-current na-slide__price-current--cyan">28500/-</span>
                            <span class="na-slide__price-old">38500</span>
                        </div>
                    </div> -->
                    <div class="na-slide__benefits-strip py-4">
                        <!-- <div class="na-slide__certified-badge">
                            <span class="na-slide__certified-text">CERTIFIED<br>EXPERIENCED<br>COACHING</span>
                            <span class="na-slide__stars">★★★★★</span>
                        </div> -->
                        <div class="na-slide__benefits-list">
                            <span class="fs-4">Live Industrial Training</span>
                            <span class="na-slide__plus">+</span>
                            <span class="fs-4">Real Projects</span>
                            <span class="na-slide__plus">+</span>
                            <span class="fs-4">Job Assistance</span>
                            <span class="na-slide__plus">+</span>
                            <span class="fs-4">Portfolio Development</span>
                        </div>
                        <!-- <div class="na-slide__contact">
                            <div class="na-slide__website">nextacademyindia.com</div>
                            <div class="na-slide__phone"><i class="fas fa-phone-alt"></i> 9898-740-777</div>
                        </div> -->
                    </div>
                </div>
            </div>

            <!-- Slide 2 - Digital Marketing Master -->
            <div class="carousel-item na-slide na-slide--dm-master" role="img"
                aria-label="Digital Marketing Master Course at Next Academy Kalol">
                <!-- Background with orange theme -->
                <div class="na-slide__bg na-slide__bg--dm-master"></div>

                <!-- Top branding strip -->
                <div class="na-slide__top-strip na-slide__top-strip--orange ">
                    <div class="na-slide__logo-area">
                        <img src="assets/images/slider-logo.png" alt="Next Academy Logo" class="na-slide__logo-img">
                    </div>
                    <div class="na-slide__audience">
                        <span class="na-slide__audience-label">SPECIALLY CREATED FOR</span>
                        <span class="na-slide__badge na-slide__badge--yellow">12TH</span>
                        <span class="na-slide__badge na-slide__badge--yellow">BBA</span>
                        <span class="na-slide__audience-amp">&</span>
                        <span class="na-slide__badge na-slide__badge--yellow">BCOM</span>
                        <span class="na-slide__audience-label na-slide__audience-label--yellow">STUDENTS</span>
                    </div>
                    <div class="na-slide__ai-academy">
                        <span class="na-slide__robot-icon"><img style="width: 60px;" src="assets/images/ai.png"
                                alt=""></span>
                        <span class="na-slide__ai-text">First AI Smart<br><strong>Academy in Kalol</strong></span>
                    </div>

                </div>

                <!-- Main content area -->
                <div class="na-slide__content">
                    <!-- Left side: Heading -->
                    <div class="na-slide__left">
                        <h2 class="na-slide__heading ps-5">
                            <span class="na-slide__heading-line">Digital</span>
                            <span class="na-slide__heading-line">Marketing <span
                                    class="na-slide__heading-accent na-slide__heading-accent--orange">Master</span></span>
                        </h2>
                        <div class="na-slide__with-ai ps-5">
                            <span class="na-slide__with-text">WITH</span>
                            <span class=""><img src="assets/images/ai-m.png" alt="" style="width: 50px;"></span>
                        </div>
                        <div class="na-slide__batches ps-5">
                            <span class="na-slide__batches-title">Batches Available</span>
                            <div class="na-slide__batch-item"><img style="width: 20px;" src="assets/images/morning.png"
                                    alt=""> Morning ✅
                            </div>
                            <div class="na-slide__batch-item"><img style="width: 20px;" src="assets/images/evening.png"
                                    alt=""> Evening ✅
                            </div>
                        </div>
                    </div>

                    <!-- Center: Character -->
                    <div class="na-slide__character">
                        <div class="na-slide__character-glow na-slide__character-glow--orange"></div>
                        <img src="assets/images/slide-2-man.png" alt="Digital Marketing Master Instructor"
                            class="na-slide__character-img">
                    </div>

                    <!-- Right side: Module Card (5 columns x 2 rows = 10 icons) -->
                    <div class="na-slide__right">
                        <div class="na-slide__module-card na-slide__module-card--orange">
                            <div class="na-slide__module-grid na-slide__module-grid--5col">
                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon">
                                        <img style="width: 100%;height: 100%;object-fit: contain;border-radius: 20px;"
                                            src="assets/images/photoshop.png" />
                                    </div>
                                    <span><strong>Photoshop</strong></span>
                                </div>
                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon">
                                        <img style="width: 100%;height: 100%;object-fit: contain;border-radius: 20px;"
                                            src="assets/images/canva.png" />
                                    </div>
                                    <span><strong>Canva</strong></span>
                                </div>
                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon">
                                        <img style="width: 100%;height: 100%;object-fit: contain;border-radius: 20px;"
                                            src="assets/images/video-marketing.png" />
                                    </div>
                                    <span><strong>Reel</strong><br>Making</span>
                                </div>
                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon">
                                        <img style="width: 100%;height: 100%;object-fit: contain;border-radius: 20px;"
                                            src="assets/images/meta.png" />
                                    </div>
                                    <span><strong>Meta</strong><br>Campaign</span>
                                </div>

                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon">
                                        <img style="width: 100%;height: 100%;object-fit: contain;border-radius: 20px;"
                                            src="assets/images/youtube.png" />
                                    </div>
                                    <span><strong>Content</strong><br>Creation</span>
                                </div>

                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon">
                                        <img style="width: 100%;height: 100%;object-fit: contain;border-radius: 20px;"
                                            src="assets/images/instagram.png" />

                                    </div>
                                    <span><strong>Instagram</strong><br>Campaign</span>
                                </div>
                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon">
                                        <img style="width: 100%;height: 100%;object-fit: contain;border-radius: 20px;"
                                            src="assets/images/google-b.png" />
                                    </div>
                                    <span><strong>Google</strong><br>Business</span>
                                </div>
                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon">
                                        <img style="width: 100%;height: 100%;object-fit: contain;border-radius: 20px;"
                                            src="assets/images/google-ads.png" />
                                    </div>
                                    <span><strong>Google</strong><br>Ads</span>
                                </div>
                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon">
                                        <img style="width: 100%;height: 100%;object-fit: contain;border-radius: 20px;"
                                            src="assets/images/google-seo.png" />
                                    </div>
                                    <span><strong>Google</strong><br>SEO</span>
                                </div>
                                <div class="na-slide__module-item">
                                    <div class="na-slide__module-icon">
                                        <img style="width: 100%;height: 100%;object-fit: contain;border-radius: 20px;"
                                            src="assets/images/landing.png" />
                                    </div>
                                    <span><strong>LANDING</strong><br>PAGE</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom section -->
                <div class="na-slide__bottom">
                    <!-- <div class="na-slide__pricing-row na-slide__pricing-row--orange">
                        <div class="na-slide__duration">
                            <i class="fas fa-certificate"></i>
                            <span><strong>12 Months</strong><br>Certification Program</span>
                        </div>
                        <div class="na-slide__price">
                            <span class="na-slide__price-current na-slide__price-current--orange">57200/-</span>
                            <span class="na-slide__price-old">64500</span>
                        </div>
                    </div> -->
                    <div class="na-slide__benefits-strip na-slide__benefits-strip--orange py-4">
                        <!-- <div class="na-slide__certified-badge">
                            <span class="na-slide__certified-text">CERTIFIED<br>EXPERIENCED<br>COACHING</span>
                            <span class="na-slide__stars">★★★★★</span>
                        </div> -->
                        <div class="na-slide__benefits-list">
                            <span class="fs-5">Live Industrial Training</span>
                            <span class="na-slide__plus ">+</span>
                            <span class="fs-5">Real Projects</span>
                            <span class="na-slide__plus ">+</span>
                            <span class="fs-5">Job Assistance</span>
                            <span class="na-slide__plus ">+</span>
                            <span class="fs-5">Portfolio Development</span>
                        </div>
                        <!-- <div class="na-slide__contact">
                            <div class="na-slide__website">nextacademyindia.com</div>
                            <div class="na-slide__phone"><i class="fas fa-phone-alt"></i> 9898-740-777</div>
                        </div> -->
                    </div>
                </div>
            </div>

            <!-- Slide 3 -->
            <div class="carousel-item " style="background: url('assets/images/robot.jpg') center/cover;" role="img"
                aria-label="Learn MERN Stack Web Development at Next Academy Kalol">
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <div class="container">
                        <div class="row">
                            <div class="col-lg-8">
                                <h1 class="hero-title">Master MERN Stack Development</h1>
                                <p class="hero-subtitle">Build modern web applications with MongoDB, Express, React, and
                                    Node.js. Learn from industry experts with hands-on projects.</p>
                                <div class="hero-buttons">
                                    <a href="courses.php" class="btn btn-hero btn-hero-primary">Explore Courses</a>
                                    <a href="contact.php" class="btn btn-hero btn-hero-outline">Get Started</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slide 4 -->
            <div class="carousel-item" style="background: url('assets/images/robot2.jpg') center/cover;" role="img"
                aria-label="Backend Development Training - Node.js PHP Python at Next Academy">
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <div class="container">
                        <div class="row">
                            <div class="col-lg-8">
                                <h1 class="hero-title">Backend Development Excellence</h1>
                                <p class="hero-subtitle">Become a backend expert with Node.js, Python, and PHP. Build
                                    scalable APIs and robust server-side applications.</p>
                                <div class="hero-buttons">
                                    <a href="courses.php" class="btn btn-hero btn-hero-primary">View Programs</a>
                                    <a href="about.php" class="btn btn-hero btn-hero-outline">Learn More</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slide 5 - Original -->
            <div class="carousel-item" style="background: url('assets/images/Academy.jpg') center/cover;" role="img"
                aria-label="Next Academy Campus - Best IT Training Institute Kalol Gujarat">
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <div class="container">
                        <div class="row">
                            <div class="col-lg-8">
                                <h1 class="hero-title">Transform Your Career With Next Academy</h1>
                                <p class="hero-subtitle">Join 100+ students who are already learning and building
                                    amazing projects. Start your journey today!</p>
                                <div class="hero-buttons">
                                    <a href="contact.php" class="btn btn-hero btn-hero-primary">Contact Us</a>
                                    <a href="gallery.php" class="btn btn-hero btn-hero-outline">View Gallery</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <div class="container">
        <h2 class="section-title" data-aos="fade-up">Why Choose Next Academy?</h2>
        <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">
            We provide world-class education with industry-relevant curriculum
        </p>

        <div class="row g-4">
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-laptop-code"></i>
                    </div>
                    <h3 class="feature-title">Hands-On Learning</h3>
                    <p class="feature-description">
                        Learn by doing with real-world projects and practical assignments that build your portfolio.
                    </p>
                </div>
            </div>

            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="feature-title">Expert Instructors</h3>
                    <p class="feature-description">
                        Learn from industry professionals with years of experience in software development.
                    </p>
                </div>
            </div>

            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="400">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="feature-title">Flexible Schedule</h3>
                    <p class="feature-description">
                        Choose from morning and evening batches that fit your schedule and lifestyle.
                    </p>
                </div>
            </div>

            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="500">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <h3 class="feature-title">Industry Certification</h3>
                    <p class="feature-description">
                        Earn recognized certificates that boost your resume and career prospects.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Courses Section -->
<section class="courses-section">
    <div class="container">
        <h2 class="section-title" data-aos="fade-up">Featured Courses</h2>
        <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">
            Choose from our wide range of professional courses
        </p>

        <div class="row g-4">
            <?php
            $delay = 200;
            while ($course = $featured_courses->fetch_assoc()):
                $min_fee = $course['min_fee'] ? number_format($course['min_fee'], 0) : '15,000';
                $max_duration = $course['max_duration'] ?: '12';

                // Assign icons based on category
                $icons = [
                    'Web Development' => 'fa-code',
                    'Graphics Design' => 'fa-palette',
                    'Digital Marketing' => 'fa-bullhorn',
                    'Mobile Development' => 'fa-mobile-alt'
                ];
                $icon = $icons[$course['category_name']] ?? 'fa-book';
                ?>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                    <div class="course-card">
                        <div class="course-image">
                            <i class="fas <?php echo $icon; ?> course-icon"></i>
                            <span class="course-badge"><?php echo htmlspecialchars($course['category_name']); ?></span>
                        </div>
                        <div class="course-body">
                            <div class="course-category"><?php echo htmlspecialchars($course['category_name']); ?></div>
                            <h3 class="course-title"><?php echo htmlspecialchars($course['name']); ?></h3>
                            <p class="course-description">
                                <?php echo htmlspecialchars(substr($course['description'] ?: 'Professional course with industry-standard curriculum and hands-on projects.', 0, 100)); ?>...
                            </p>
                            <div class="course-meta">
                                <div class="course-duration">
                                    <i class="fas fa-clock me-1"></i>
                                    Up to <?php echo $max_duration; ?> months
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <?php
                $delay += 100;
            endwhile;
            ?>
        </div>

        <div class="text-center mt-5" data-aos="fade-up">
            <a href="courses.php" class="btn btn-hero btn-hero-primary">
                View All Courses <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['students']; ?>+</div>
                    <div class="stat-label">Active Students</div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['courses']; ?>+</div>
                    <div class="stat-label">Professional Courses</div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['categories'] * 5; ?>+</div>
                    <div class="stat-label">Expert Instructors</div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="400">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['success_rate']; ?>%</div>
                    <div class="stat-label">Success Rate</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <h2 class="cta-title" data-aos="fade-up">Ready to Start Learning?</h2>
        <p class="cta-description" data-aos="fade-up" data-aos-delay="100">
            Join thousands of students who have transformed their careers with Next Academy
        </p>
        <div data-aos="fade-up" data-aos-delay="200">
            <a href="contact.php" class="btn btn-hero me-3" style="background: var(--primary-purple); color: white;">
                <i class="fas fa-phone me-2"></i> Contact Us
            </a>
            <a href="courses.php" class="btn btn-hero" style="background: var(--primary-purple); color: white;">
                <i class="fas fa-book me-2"></i> Browse Courses
            </a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>