<?php
require_once 'admin/config/database.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $course_interest = sanitize($_POST['course_interest']);
    $message = sanitize($_POST['message']);
    
    // Insert into inquiries table
    $stmt = $conn->prepare("INSERT INTO inquiries (name, email, phone, course_interest, message, status, inquiry_date) VALUES (?, ?, ?, ?, ?, 'New', NOW())");
    $stmt->bind_param("sssss", $name, $email, $phone, $course_interest, $message);
    
    if ($stmt->execute()) {
        $success_message = "Thank you for contacting us! We'll get back to you soon.";
    } else {
        $error_message = "Sorry, there was an error. Please try again or call us directly.";
    }
    $stmt->close();
}

// Get courses for dropdown
$courses = $conn->query("SELECT name FROM courses WHERE status = 'Active' ORDER BY name");

$page_title = "Contact Us - Next Academy";
?>
<?php include 'includes/navbar.php'; ?>

<!-- Page Header -->
<section class="page-header-section">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center">
                <h1 class="page-header-title" data-aos="fade-up">Contact Us</h1>
                <p class="page-header-subtitle" data-aos="fade-up" data-aos-delay="100">
                    Get in touch with us - We'd love to hear from you
                </p>
                <nav aria-label="breadcrumb" data-aos="fade-up" data-aos-delay="200">
                    <ol class="breadcrumb justify-content-center">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Contact</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<!-- Contact Info Cards -->
<section class="contact-info-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="contact-info-card">
                    <div class="contact-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h4>Visit Us</h4>
                    <p> Next Academy City Mall-1, FF30, Above ICICI Bank, Navjivan Road, Kalol,Gujarat - 382721, India</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="contact-info-card">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h4>Call Us</h4>
                    <p>+91 97379 49789</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="contact-info-card">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h4>Email Us</h4>
                    <p>nextacademy89@gmail.com</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Form & Map Section -->
<section class="contact-form-section">
    <div class="container">
        <div class="row g-5">
            <!-- Contact Form -->
            <div class="col-lg-6" data-aos="fade-right">
                <div class="contact-form-wrapper">
                    <h2 class="section-title-left">Send Us a Message</h2>
                    <p class="mb-4">Fill out the form below and we'll get back to you as soon as possible</p>
                    
                    <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="contact-form">
                        <div class="mb-3">
                            <!--<label class="form-label">Full Name *</label>-->
                            <input type="text" class="form-control" name="name" required placeholder="Enter your name">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <!--<label class="form-label">Email *</label>-->
                                <input type="email" class="form-control" name="email" required placeholder="your@email.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <!--<label class="form-label">Phone *</label>-->
                                <input type="tel" class="form-control" name="phone" required placeholder="+91 98765 43210">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Course Interest</label>
                            <select class="form-select" name="course_interest">
                                <option value="">Select a course</option>
                                <?php while ($course = $courses->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($course['name']); ?>">
                                    <?php echo htmlspecialchars($course['name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Message *</label>
                            <textarea class="form-control" name="message" rows="5" required placeholder="Tell us about your requirements..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-hero btn-hero-primary w-100">
                            <i class="fas fa-paper-plane me-2"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Map & Additional Info -->
            <div class="col-lg-6" data-aos="fade-left">
                <div class="map-wrapper">
                    <iframe 
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3666.0473213362334!2d72.5008324746646!3d23.24136500812921!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x395c25aa9ecc4bef%3A0x5cfe4d1832b5f56e!2sNext%20Academy%20%7C%20Coding%2C%20AI%2C%20Digital%20Marketing%2C%20Web%20Development%20%26%20Graphic%20Design%20Class%20%7C%20Best%20Computer%20Class%20in%20Kalol!5e0!3m2!1sen!2sin!4v1770360483186!5m2!1sen!2sin" 
                        width="100%" 
                        height="400" 
                        style="border:0; border-radius: 15px;" 
                        allowfullscreen="" 
                        loading="lazy">
                    </iframe>
                    
                    
                </div>
                
                <div class="working-hours-card mt-4">
                    <h4><i class="fas fa-clock me-2"></i> Working Hours</h4>
                    <div class="hours-list">
                        <div class="hours-item">
                            <span class="day">Monday - Saturday</span>
                            <span class="time">9:30 AM - 8:00 PM</span>
                        </div>
                        <div class="hours-item">
                            <span class="day">Sunday</span>
                            <span class="time">Closed</span>
                        </div>
                    </div>
                </div>
                
                <div class="social-connect mt-4">
                    <h4>Connect With Us</h4>
                    <div class="social-links">
                        <a href="#" class="social-link facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-link instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="social-link whatsapp">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <a href="#" class="social-link youtube">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="faq-section">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-label" data-aos="fade-up">FAQ</span>
            <h2 class="section-title" data-aos="fade-up" data-aos-delay="100">Frequently Asked Questions</h2>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item" data-aos="fade-up" data-aos-delay="100">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                What are the course timings?
                            </button>
                        </h2>
                        
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                We offer flexible batch timings including morning (9 AM - 12 PM) and evening (5 PM - 8 PM) batches. Weekend batches are also available for working professionals.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item" data-aos="fade-up" data-aos-delay="200">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                Do you provide placement assistance?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes, we provide comprehensive placement assistance including resume building, interview preparation, and connecting you with our network of hiring partners.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item" data-aos="fade-up" data-aos-delay="300">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                What is the fee structure?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Our fees vary by course and duration. We offer flexible payment options and installment plans. Please contact us for detailed fee information for specific courses.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item" data-aos="fade-up" data-aos-delay="400">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                Can I get a demo class?
                            </button>
                        </h2>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Absolutely! We offer free demo classes for all our courses. Contact us to schedule your demo class at a convenient time.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>