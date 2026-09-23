<?php
$page_title = "Gallery - Next Academy";
?>
<?php include 'includes/navbar.php'; ?>

<!-- Page Header -->
<section class="page-header-section">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center">
                <h1 class="page-header-title" data-aos="fade-up">Our Gallery</h1>
                <p class="page-header-subtitle" data-aos="fade-up" data-aos-delay="100">
                    Glimpses of learning, growth, and success at Next Academy
                </p>
                <nav aria-label="breadcrumb" data-aos="fade-up" data-aos-delay="200">
                    <ol class="breadcrumb justify-content-center">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Gallery</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<!-- Gallery Filter -->
<section class="gallery-filter-section">
    <div class="container">
        <div class="text-center">
            <div class="gallery-filters" data-aos="fade-up">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="classroom">Classroom</button>
                <button class="filter-btn" data-filter="events">Events</button>
                <button class="filter-btn" data-filter="projects">Projects</button>
                <button class="filter-btn" data-filter="achievements">Achievements</button>
            </div>
        </div>
    </div>
</section>

<!-- Gallery Grid -->
<section class="gallery-grid-section">
    <div class="container">
        <div class="row g-4" id="galleryContainer">
            <!-- Classroom Images -->
            <div class="col-lg-4 col-md-6 gallery-item" data-category="classroom" data-aos="zoom-in" data-aos-delay="100">
                <div class="gallery-card">
                    <img src="assets/images/gallery/classroom-1.jpg" alt="Classroom Session">
                    <div class="gallery-overlay">
                        <div class="gallery-content">
                            <h4>Interactive Learning</h4>
                            <p>Students engaging in hands-on coding session</p>
                            <a href="assets/images/gallery/classroom-1.jpg" class="gallery-zoom" data-lightbox="gallery" data-title="Interactive Learning">
                                <i class="fas fa-search-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 gallery-item" data-category="classroom" data-aos="zoom-in" data-aos-delay="200">
                <div class="gallery-card">
                    <img src="assets/images/gallery/classroom-2.jpg" alt="Practical Training">
                    <div class="gallery-overlay">
                        <div class="gallery-content">
                            <h4>Practical Training</h4>
                            <p>Real-world project development</p>
                            <a href="assets/images/gallery/classroom-2.jpg" class="gallery-zoom" data-lightbox="gallery" data-title="Practical Training">
                                <i class="fas fa-search-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 gallery-item" data-category="classroom" data-aos="zoom-in" data-aos-delay="300">
                <div class="gallery-card">
                    <img src="assets/images/gallery/classroom-3.jpg" alt="Lab Session">
                    <div class="gallery-overlay">
                        <div class="gallery-content">
                            <h4>Modern Lab</h4>
                            <p>State-of-the-art computer lab</p>
                            <a href="assets/images/gallery/classroom-3.jpg" class="gallery-zoom" data-lightbox="gallery" data-title="Modern Lab">
                                <i class="fas fa-search-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Events Images -->
            <div class="col-lg-4 col-md-6 gallery-item" data-category="events" data-aos="zoom-in" data-aos-delay="100">
                <div class="gallery-card">
                    <img src="assets/images/gallery/event-1.jpg" alt="Tech Fest">
                    <div class="gallery-overlay">
                        <div class="gallery-content">
                            <h4>Annual Tech Fest</h4>
                            <p>Students showcasing innovative projects</p>
                            <a href="assets/images/gallery/event-1.jpg" class="gallery-zoom" data-lightbox="gallery" data-title="Annual Tech Fest">
                                <i class="fas fa-search-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 gallery-item" data-category="events" data-aos="zoom-in" data-aos-delay="200">
                <div class="gallery-card">
                    <img src="assets/images/gallery/event-2.jpg" alt="Workshop">
                    <div class="gallery-overlay">
                        <div class="gallery-content">
                            <h4>Industry Workshop</h4>
                            <p>Expert session on latest technologies</p>
                            <a href="assets/images/gallery/event-2.jpg" class="gallery-zoom" data-lightbox="gallery" data-title="Industry Workshop">
                                <i class="fas fa-search-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 gallery-item" data-category="events" data-aos="zoom-in" data-aos-delay="300">
                <div class="gallery-card">
                    <img src="assets/images/gallery/event-3.jpg" alt="Guest Lecture">
                    <div class="gallery-overlay">
                        <div class="gallery-content">
                            <h4>Guest Lecture</h4>
                            <p>Inspiring talk by industry expert</p>
                            <a href="assets/images/gallery/event-3.jpg" class="gallery-zoom" data-lightbox="gallery" data-title="Guest Lecture">
                                <i class="fas fa-search-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Projects Images -->
            <div class="col-lg-4 col-md-6 gallery-item" data-category="projects" data-aos="zoom-in" data-aos-delay="100">
                <div class="gallery-card">
                    <img src="assets/images/gallery/project-1.jpg" alt="Web Project">
                    <div class="gallery-overlay">
                        <div class="gallery-content">
                            <h4>E-Commerce Platform</h4>
                            <p>Full-stack web development project</p>
                            <a href="assets/images/gallery/project-1.jpg" class="gallery-zoom" data-lightbox="gallery" data-title="E-Commerce Platform">
                                <i class="fas fa-search-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 gallery-item" data-category="projects" data-aos="zoom-in" data-aos-delay="200">
                <div class="gallery-card">
                    <img src="assets/images/gallery/project-2.jpg" alt="Mobile App">
                    <div class="gallery-overlay">
                        <div class="gallery-content">
                            <h4>Mobile Application</h4>
                            <p>Cross-platform app development</p>
                            <a href="assets/images/gallery/project-2.jpg" class="gallery-zoom" data-lightbox="gallery" data-title="Mobile Application">
                                <i class="fas fa-search-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 gallery-item" data-category="projects" data-aos="zoom-in" data-aos-delay="300">
                <div class="gallery-card">
                    <img src="assets/images/gallery/project-3.jpg" alt="Design Project">
                    <div class="gallery-overlay">
                        <div class="gallery-content">
                            <h4>UI/UX Design</h4>
                            <p>Creative design portfolio</p>
                            <a href="assets/images/gallery/project-3.jpg" class="gallery-zoom" data-lightbox="gallery" data-title="UI/UX Design">
                                <i class="fas fa-search-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Achievements Images -->
            <div class="col-lg-4 col-md-6 gallery-item" data-category="achievements" data-aos="zoom-in" data-aos-delay="100">
                <div class="gallery-card">
                    <img src="assets/images/gallery/achievement-1.jpg" alt="Graduation">
                    <div class="gallery-overlay">
                        <div class="gallery-content">
                            <h4>Graduation Ceremony</h4>
                            <p>Celebrating student success</p>
                            <a href="assets/images/gallery/achievement-1.jpg" class="gallery-zoom" data-lightbox="gallery" data-title="Graduation Ceremony">
                                <i class="fas fa-search-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 gallery-item" data-category="achievements" data-aos="zoom-in" data-aos-delay="200">
                <div class="gallery-card">
                    <img src="assets/images/gallery/achievement-2.jpg" alt="Award">
                    <div class="gallery-overlay">
                        <div class="gallery-content">
                            <h4>Excellence Award</h4>
                            <p>Recognizing top performers</p>
                            <a href="assets/images/gallery/achievement-2.jpg" class="gallery-zoom" data-lightbox="gallery" data-title="Excellence Award">
                                <i class="fas fa-search-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 gallery-item" data-category="achievements" data-aos="zoom-in" data-aos-delay="300">
                <div class="gallery-card">
                    <img src="assets/images/gallery/achievement-3.jpg" alt="Placement">
                    <div class="gallery-overlay">
                        <div class="gallery-content">
                            <h4>Placement Success</h4>
                            <p>Students joining top companies</p>
                            <a href="assets/images/gallery/achievement-3.jpg" class="gallery-zoom" data-lightbox="gallery" data-title="Placement Success">
                                <i class="fas fa-search-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>



<!-- Lightbox CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">

<!-- Gallery Filter Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterBtns = document.querySelectorAll('.filter-btn');
    const galleryItems = document.querySelectorAll('.gallery-item');
    
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active class from all buttons
            filterBtns.forEach(b => b.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');
            
            const filterValue = this.getAttribute('data-filter');
            
            galleryItems.forEach(item => {
                if (filterValue === 'all' || item.getAttribute('data-category') === filterValue) {
                    item.style.display = 'block';
                    setTimeout(() => {
                        item.style.opacity = '1';
                        item.style.transform = 'scale(1)';
                    }, 10);
                } else {
                    item.style.opacity = '0';
                    item.style.transform = 'scale(0.8)';
                    setTimeout(() => {
                        item.style.display = 'none';
                    }, 300);
                }
            });
        });
    });
});
</script>

<!-- Lightbox JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>

<?php include 'includes/footer.php'; ?>