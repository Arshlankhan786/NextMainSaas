<!-- Footer -->
<footer class="modern-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="footer-brand">Next Academy</div>
                <p class="footer-description">
                    Empowering students with quality education and practical skills for a successful future in
                    technology.
                </p>
                <div class="social-icons">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <!--<a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>-->
                    <!--<a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>-->
                </div>
            </div>

            <div class="col-lg-2 col-md-4">
                <h4 class="footer-title">Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="courses.php">Courses</a></li>
                    <li><a href="digital-marketing.php">Digital Marketing Course</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="gallery.php">Gallery</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-4">
                <h4 class="footer-title">Popular Courses</h4>
                <ul class="footer-links">
                    <?php
                    // Read from cache file (no DB connection needed)
                    $cache_file = __DIR__ . '/admin/config/footer_cache.php';
                    // Determine the correct relative path (footer.php is in includes/)
                    // but pages in root include it, so __DIR__ is /includes/
                    // We need to go up one level to reach admin/config/
                    if (!file_exists($cache_file)) {
                        $cache_file = dirname(__DIR__) . '/admin/config/footer_cache.php';
                    }
                    $footer_courses = [];

                    if (file_exists($cache_file)) {
                        // Cache exists — use it (no DB connection)
                        $footer_courses = @include($cache_file);
                        if (!is_array($footer_courses)) $footer_courses = [];
                    }

                    // If cache is empty or missing, try DB if connection is available
                    if (empty($footer_courses) && isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
                        $pc_result = $conn->query("SELECT name FROM courses WHERE status = 'Active' ORDER BY created_at DESC LIMIT 5");
                        if ($pc_result) {
                            while ($row = $pc_result->fetch_assoc()) {
                                $footer_courses[] = $row['name'];
                            }
                        }
                        // Rebuild cache for next time
                        if (!empty($footer_courses) && function_exists('rebuildFooterCache')) {
                            rebuildFooterCache();
                        }
                    }

                    if (!empty($footer_courses)):
                        foreach ($footer_courses as $course_name):
                    ?>
                        <li><a href="courses.php"><?php echo htmlspecialchars($course_name); ?></a></li>
                    <?php
                        endforeach;
                    else:
                    ?>
                        <li><a href="courses.php">View Our Courses</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="col-lg-3 col-md-4">
                <h4 class="footer-title">Contact Info</h4>
                <ul class="footer-links">
                    <li>
                        <i class="fas fa-map-marker-alt me-2"></i>
                        Next Academy City Mall-1, FF30, Above ICICI Bank, Navjivan Road,
                        Kalol,Gujarat - 382721,
                        India
                    </li>
                    <li>
                        <i class="fas fa-phone me-2"></i>
                        <a href="tel:+919737949789">+91 97379 49789</a>
                    </li>
                    <li>
                        <i class="fas fa-envelope me-2"></i>
                        <a href="mailto:nextacademy89@gmail.com">nextacademy89@gmail.com</a>
                    </li>
                    <li>
                        <i class="fas fa-clock me-2"></i>
                        Mon - Sat: 9:30AM - 8PM
                    </li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p class="mb-0">
                © <?php echo date('Y'); ?> Next Academy. All rights reserved.
            </p>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- AOS Animation JS -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<!-- Custom JS -->
<script>
    // Initialize AOS
    AOS.init({
        duration: 800,
        once: true,
        offset: 100
    });

    // Navbar scroll effect
    window.addEventListener('scroll', function () {
        const navbar = document.querySelector('.modern-navbar');
        if (navbar) {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        }
    });

    // Close offcanvas when clicking a link
    const offcanvasLinks = document.querySelectorAll('.offcanvas .nav-link');
    const offcanvasElement = document.getElementById('navbarOffcanvas');

    if (offcanvasElement) {
        offcanvasLinks.forEach(link => {
            link.addEventListener('click', () => {
                const offcanvas = bootstrap.Offcanvas.getInstance(offcanvasElement);
                if (offcanvas) {
                    offcanvas.hide();
                }
            });
        });
    }
</script>
</body>

</html>