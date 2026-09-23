<?php
require_once 'admin/config/database.php';

// Get statistics
$stats = [];
$result = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'Active'");
$stats['students'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'Completed'");
$stats['graduates'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM courses WHERE status = 'Active'");
$stats['courses'] = $result->fetch_assoc()['count'];

$foundation_year = 2020;
$stats['experience'] = date('Y') - $foundation_year;

$categories = $conn->query("SELECT * FROM categories WHERE status = 'Active' ORDER BY name LIMIT 4");

$page_title = "About Us - Next Academy";
?>
<?php include 'includes/navbar.php'; ?>

<!-- Page Header -->
<section class="page-header-section">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center">
                <h1 class="page-header-title" data-aos="fade-up">About Next Academy</h1>
                <p class="page-header-subtitle" data-aos="fade-up" data-aos-delay="100">
                    Empowering students with quality education and practical skills
                </p>
                <nav aria-label="breadcrumb" data-aos="fade-up" data-aos-delay="200">
                    <ol class="breadcrumb justify-content-center">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">About</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<!-- About Story Section -->
<section class="about-story-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6" data-aos="fade-right">
                <div class="about-image-wrapper">
                    <div class="about-image-main">
                        <img src="assets/images/about-main.jpg" alt="Next Academy Campus - Best IT Training Institute in Kalol Gujarat" class="img-fluid rounded-4" loading="lazy">
                    </div>
                    <div class="about-image-badge">
                        <div class="badge-content">
                            <h3><?php echo $stats['experience']; ?>+</h3>
                            <p>Years of Excellence</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <div class="about-content">
                    <span class="section-label">Our Story</span>
                    <h2 class="section-title-left">Building Future Tech Leaders Since <?php echo $foundation_year; ?></h2>
                    <p class="about-text">
                        Next Academy is a premier skill development institution dedicated to transforming aspiring students into industry-ready professionals. We believe in the power of practical, hands-on education that bridges the gap between academic learning and real-world application.
                    </p>
                    <p class="about-text">
                        Our mission is simple: to provide high-quality, affordable, and accessible technology education that empowers students to achieve their career goals. With expert instructors, modern curriculum, and a student-first approach, we've helped hundreds of students launch successful careers in tech.
                    </p>
                    <div class="about-features-list">
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Industry-relevant curriculum</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Expert faculty with real-world experience</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Hands-on project-based learning</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Flexible batch timings</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Counter -->
<section class="stats-counter-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="100">
                <div class="stat-counter-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="stat-number" data-count="<?php echo $stats['students']; ?>">0</h3>
                    <p class="stat-label">Active Students</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="200">
                <div class="stat-counter-card">
                    <div class="stat-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3 class="stat-number" data-count="<?php echo $stats['graduates']; ?>">0</h3>
                    <p class="stat-label">Graduates</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="300">
                <div class="stat-counter-card">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3 class="stat-number" data-count="<?php echo $stats['courses']; ?>">0</h3>
                    <p class="stat-label">Professional Courses</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="400">
                <div class="stat-counter-card">
                    <div class="stat-icon">
                        <i class="fas fa-award"></i>
                    </div>
                    <h3 class="stat-number" data-count="95">0</h3>
                    <p class="stat-label">Success Rate %</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Our Mission & Vision -->
<section class="mission-vision-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-6" data-aos="fade-up">
                <div class="mission-card">
                    <div class="mission-icon">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <h3>Our Mission</h3>
                    <p>To provide accessible, high-quality technology education that empowers students with practical skills, industry knowledge, and the confidence to excel in their chosen careers. We strive to bridge the gap between education and employment through hands-on training and real-world projects.</p>
                </div>
            </div>
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
                <div class="vision-card">
                    <div class="vision-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3>Our Vision</h3>
                    <p>To be the leading skill development institution in Gujarat, recognized for transforming students into industry-ready professionals. We envision a future where every student has access to quality education and the opportunity to build a successful career in technology.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Our Programs -->
<section class="programs-section">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-label" data-aos="fade-up">Our Programs</span>
            <h2 class="section-title" data-aos="fade-up" data-aos-delay="100">What We Teach</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="200">
                Industry-focused programs designed for career success
            </p>
        </div>
        <div class="row g-4">
            <?php 
            $delay = 100;
            while ($category = $categories->fetch_assoc()): 
            ?>
            <div class="col-lg-3 col-md-6" data-aos="flip-left" data-aos-delay="<?php echo $delay; ?>">
                <div class="program-card">
                    <div class="program-icon">
                        <i class="fas fa-laptop-code"></i>
                    </div>
                    <h4><?php echo htmlspecialchars($category['name']); ?></h4>
                    <p><?php echo htmlspecialchars($category['description'] ?: 'Professional training program'); ?></p>
                </div>
            </div>
            <?php 
            $delay += 100;
            endwhile; 
            ?>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="why-choose-section">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-label" data-aos="fade-up">Why Choose Us</span>
            <h2 class="section-title" data-aos="fade-up" data-aos-delay="100">What Makes Us Different</h2>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="why-card">
                    <div class="why-number">01</div>
                    <h4>Expert Instructors</h4>
                    <p>Learn from industry professionals with years of practical experience in their respective fields.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="why-card">
                    <div class="why-number">02</div>
                    <h4>Practical Training</h4>
                    <p>Focus on hands-on learning through real-world projects that build your professional portfolio.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="why-card">
                    <div class="why-number">03</div>
                    <h4>Flexible Batches</h4>
                    <p>Choose from morning and evening batches that fit your schedule and lifestyle.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="why-card">
                    <div class="why-number">04</div>
                    <h4>Affordable Fees</h4>
                    <p>Quality education at competitive prices with flexible payment options.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="why-card">
                    <div class="why-number">05</div>
                    <h4>Modern Curriculum</h4>
                    <p>Industry-relevant course content updated regularly to match market demands.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="why-card">
                    <div class="why-number">06</div>
                    <h4>Career Support</h4>
                    <p>Guidance and support to help you launch and grow your career in technology.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section" style="background: var(--primary-purple)">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8" data-aos="fade-right">
                <h2 class="cta-title-alt ">Ready to Start Your Learning Journey?</h2>
                <p class="cta-description-alt">Join hundreds of students who have transformed their careers with Next Academy</p>
            </div>
            <div class="col-lg-4 text-lg-end" data-aos="fade-left">
                <a href="contact.php" class="btn btn-hero btn-hero-outline ">
                    <i class="fas fa-phone me-2"></i> Contact Us
                </a>
            </div>
        </div>
    </div>
</section>

<script>
// Counter Animation
function animateCounter() {
    const counters = document.querySelectorAll('.stat-number');
    const speed = 200;

    counters.forEach(counter => {
        const updateCount = () => {
            const target = +counter.getAttribute('data-count');
            const count = +counter.innerText;
            const inc = target / speed;

            if (count < target) {
                counter.innerText = Math.ceil(count + inc);
                setTimeout(updateCount, 1);
            } else {
                counter.innerText = target + '+';
            }
        };
        updateCount();
    });
}

// Trigger counter when in view
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCounter();
            observer.disconnect();
        }
    });
});

observer.observe(document.querySelector('.stats-counter-section'));
</script>

<?php include 'includes/footer.php'; ?>