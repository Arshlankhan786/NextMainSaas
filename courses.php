<?php
// Include database connection
require_once 'admin/config/database.php';

// Get all categories
$categories = $conn->query("SELECT * FROM categories WHERE status = 'Active' ORDER BY name");

// Get selected category from URL
$selected_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Fetch courses based on category filter
if ($selected_category > 0) {
    $courses_query = $conn->query("
        SELECT c.*, cat.name as category_name,
               (SELECT MAX(duration_months) FROM course_fees WHERE course_id = c.id) as max_duration
        FROM courses c
        JOIN categories cat ON c.category_id = cat.id
        WHERE c.status = 'Active' AND c.category_id = $selected_category
        ORDER BY c.created_at DESC
    ");
} else {
    $courses_query = $conn->query("
        SELECT c.*, cat.name as category_name,
               (SELECT MAX(duration_months) FROM course_fees WHERE course_id = c.id) as max_duration
        FROM courses c
        JOIN categories cat ON c.category_id = cat.id
        WHERE c.status = 'Active'
        ORDER BY c.created_at DESC
    ");
}

$page_title = "Courses - Next Academy";
?>
<?php include 'includes/navbar.php'; ?>

    <!-- Page Header -->
    <section class="page-header-section">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h1 class="page-header-title" data-aos="fade-up">Our Courses</h1>
                    <p class="page-header-subtitle" data-aos="fade-up" data-aos-delay="100">
                        Choose from our comprehensive range of professional training programs
                    </p>
                    <nav aria-label="breadcrumb" data-aos="fade-up" data-aos-delay="200">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Courses</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </section>

    <!-- Category Filter -->
    <section class="category-filter-section">
        <div class="container">
            <div class="category-filter" data-aos="fade-up">
                <a href="courses.php" class="category-filter-btn <?php echo $selected_category == 0 ? 'active' : ''; ?>">
                    <i class="fas fa-th-large me-2"></i> All Courses
                </a>
                <?php 
                $categories->data_seek(0);
                while ($category = $categories->fetch_assoc()): 
                ?>
                <a href="courses.php?category=<?php echo $category['id']; ?>" 
                   class="category-filter-btn <?php echo $selected_category == $category['id'] ? 'active' : ''; ?>">
                    <i class="fas fa-folder me-2"></i> <?php echo htmlspecialchars($category['name']); ?>
                </a>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- Courses Grid -->
    <section class="courses-grid-section">
        <div class="container">
            <?php if ($courses_query->num_rows > 0): ?>
            <div class="row g-4">
                <?php 
                $delay = 100;
                $icons = [
                    'Web Development' => 'fa-code',
                    'Graphics Design' => 'fa-palette',
                    'Digital Marketing' => 'fa-bullhorn',
                    'Mobile Development' => 'fa-mobile-alt',
                    'Data Science' => 'fa-chart-line',
                    'UI/UX Design' => 'fa-pencil-ruler'
                ];
                
                while ($course = $courses_query->fetch_assoc()): 
                    $max_duration = $course['max_duration'] ?: '12';
                    $icon = $icons[$course['category_name']] ?? 'fa-book';
                ?>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                    <div class="course-card-modern">
                        <div class="course-image-modern">
                            <i class="fas <?php echo $icon; ?> course-icon-modern"></i>
                            <span class="course-badge-modern"><?php echo htmlspecialchars($course['category_name']); ?></span>
                        </div>
                        <div class="course-body-modern">
                            <div class="course-category-modern">
                                <?php echo htmlspecialchars($course['category_name']); ?>
                            </div>
                            <h3 class="course-title-modern">
                                <?php echo htmlspecialchars($course['name']); ?>
                            </h3>
                            <p class="course-description-modern">
                                <?php 
                                $description = $course['description'] ?: 'Professional course with industry-standard curriculum, hands-on projects, and expert guidance to help you succeed.';
                                echo htmlspecialchars(substr($description, 0, 120)); 
                                ?>...
                            </p>
                            
                            <div class="course-meta-modern">
                                <div class="course-duration-modern">
                                    <i class="fas fa-clock me-2"></i>
                                    Up to <?php echo $max_duration; ?> months
                                </div>
                                <div class="course-level-modern">
                                    <i class="fas fa-signal me-2"></i>
                                    All Levels
                                </div>
                            </div>
                            
                            <a href="contact.php" class="btn-explore-more">
                                Explore More <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php 
                $delay = ($delay + 50) % 400;
                endwhile; 
                ?>
            </div>
            <?php else: ?>
            <div class="empty-state" data-aos="fade-up">
                <i class="fas fa-inbox empty-icon"></i>
                <h3>No Courses Found</h3>
                <p>We don't have any courses in this category yet. Please check back soon!</p>
                <a href="courses.php" class="btn btn-hero btn-hero-primary mt-3">
                    View All Courses
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section-alt" style="background: var(--primary-purple)">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8" data-aos="fade-right">
                    <h2 class="cta-title-alt">Can't Find What You're Looking For?</h2>
                    <p class="cta-description-alt">
                        Contact us to learn more about custom training programs or upcoming courses.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end" data-aos="fade-left">
                    <a href="contact.php" class="btn btn-hero btn-hero-outline">
                        <i class="fas fa-phone me-2"></i> Contact Us
                    </a>
                </div>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>