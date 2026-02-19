<?php
// templates/sections/contact.php
// Clean Contact Section - No Gradients

// Get contact information from settings
$contact_email = getSetting('contact_email', 'hello@example.com');
$contact_phone = getSetting('contact_phone', '+1 234 567 890');
$contact_address = getSetting('address', 'New York, NY');
$success_message = isset($_SESSION['contact_success']) ? $_SESSION['contact_success'] : null;
unset($_SESSION['contact_success']);
?>

<!-- Contact Section -->
<section class="contact-section" id="contact">
    <div class="container">
        <div class="section-header">
            <h2>Get In Touch</h2>
            <p>Have a project in mind? Let's work together</p>
        </div>
        
        <div class="contact-wrapper">
            <!-- Contact Information -->
            <div class="contact-info">
                <h3>Let's Connect</h3>
                <p>I'm always interested in hearing about new projects and opportunities.</p>
                
                <div class="info-items">
                    <!-- Email -->
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="info-content">
                            <h4>Email</h4>
                            <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>">
                                <?php echo htmlspecialchars($contact_email); ?>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Phone -->
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <div class="info-content">
                            <h4>Phone</h4>
                            <a href="tel:<?php echo htmlspecialchars($contact_phone); ?>">
                                <?php echo htmlspecialchars($contact_phone); ?>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Location -->
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="info-content">
                            <h4>Location</h4>
                            <p><?php echo htmlspecialchars($contact_address); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Social Links -->
                <div class="social-links">
                    <h4>Follow Me</h4>
                    <div class="social-icons">
                        <?php if (getSetting('github_url')): ?>
                        <a href="<?php echo getSetting('github_url'); ?>" target="_blank" class="social-icon">
                            <i class="fab fa-github"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (getSetting('linkedin_url')): ?>
                        <a href="<?php echo getSetting('linkedin_url'); ?>" target="_blank" class="social-icon">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (getSetting('twitter_url')): ?>
                        <a href="<?php echo getSetting('twitter_url'); ?>" target="_blank" class="social-icon">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Contact Form -->
            <div class="contact-form">
                <h3>Send a Message</h3>
                
                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Thank you! Your message has been sent successfully.
                </div>
                <?php endif; ?>
                
                <form action="<?php echo BASE_URL; ?>/contact-handler.php" method="POST" id="contactForm">
                    <input type="hidden" name="csrf_token" value="<?php echo function_exists('Auth::generateCSRF') ? Auth::generateCSRF() : ''; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Your Name *</label>
                            <input type="text" id="name" name="name" required placeholder="John Doe">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Your Email *</label>
                            <input type="email" id="email" name="email" required placeholder="john@example.com">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" placeholder="Project Inquiry">
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Your Message *</label>
                        <textarea id="message" name="message" rows="6" required placeholder="Tell me about your project..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i>
                        Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<style>
/* Contact Section - Clean Design */
.contact-section {
    padding: 80px 0;
    background-color: #ffffff;
    border-top: 1px solid #eaeef2;
}

.section-header {
    text-align: center;
    margin-bottom: 50px;
}

.section-header h2 {
    font-size: 2.5rem;
    color: #1e293b;
    margin-bottom: 10px;
    font-weight: 600;
}

.section-header p {
    font-size: 1.1rem;
    color: #64748b;
}

/* Contact Wrapper */
.contact-wrapper {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 50px;
    max-width: 1200px;
    margin: 0 auto;
    background: #f8fafc;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
}

/* Contact Info Side */
.contact-info {
    background: #ffffff;
    padding: 40px;
    border-right: 1px solid #e2e8f0;
}

.contact-info h3 {
    font-size: 1.5rem;
    color: #1e293b;
    margin-bottom: 15px;
    font-weight: 600;
}

.contact-info > p {
    color: #64748b;
    margin-bottom: 30px;
    line-height: 1.6;
}

/* Info Items */
.info-items {
    margin-bottom: 30px;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 25px;
}

.info-icon {
    width: 45px;
    height: 45px;
    background: #f1f5f9;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    font-size: 1.2rem;
    flex-shrink: 0;
    border: 1px solid #e2e8f0;
}

.info-content h4 {
    font-size: 1rem;
    color: #64748b;
    margin-bottom: 5px;
    font-weight: 500;
}

.info-content a,
.info-content p {
    color: #1e293b;
    text-decoration: none;
    font-size: 1.1rem;
    font-weight: 500;
    transition: color 0.3s ease;
}

.info-content a:hover {
    color: #2563eb;
}

/* Social Links */
.social-links h4 {
    font-size: 1rem;
    color: #64748b;
    margin-bottom: 15px;
    font-weight: 500;
}

.social-icons {
    display: flex;
    gap: 12px;
}

.social-icon {
    width: 45px;
    height: 45px;
    background: #f1f5f9;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #475569;
    font-size: 1.2rem;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
}

.social-icon:hover {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
    transform: translateY(-3px);
}

/* Contact Form Side */
.contact-form {
    padding: 40px;
    background: #ffffff;
}

.contact-form h3 {
    font-size: 1.5rem;
    color: #1e293b;
    margin-bottom: 25px;
    font-weight: 600;
}

/* Form Groups */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #475569;
    font-weight: 500;
    font-size: 0.95rem;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px 16px;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: #ffffff;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: #94a3b8;
}

/* Submit Button */
.btn-submit {
    background: #2563eb;
    color: white;
    border: none;
    padding: 14px 30px;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    border: 1.5px solid #2563eb;
    width: 100%;
    justify-content: center;
}

.btn-submit:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.15);
}

.btn-submit i {
    font-size: 1.1rem;
}

/* Alert Message */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideDown 0.5s ease;
}

.alert-success {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.alert i {
    font-size: 1.2rem;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 992px) {
    .contact-wrapper {
        grid-template-columns: 1fr;
    }
    
    .contact-info {
        border-right: none;
        border-bottom: 1px solid #e2e8f0;
    }
}

@media (max-width: 768px) {
    .contact-section {
        padding: 60px 0;
    }
    
    .section-header h2 {
        font-size: 2rem;
    }
    
    .contact-info,
    .contact-form {
        padding: 30px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    
    .info-item {
        flex-direction: column;
        text-align: center;
    }
    
    .info-icon {
        margin: 0 auto;
    }
    
    .social-icons {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .contact-info,
    .contact-form {
        padding: 20px;
    }
    
    .btn-submit {
        padding: 12px 20px;
    }
}
</style>

<script>
// Contact Form Handling
document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.getElementById('contactForm');
    
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Simple validation
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const message = document.getElementById('message').value.trim();
            
            if (!name || !email || !message) {
                alert('Please fill in all required fields');
                return;
            }
            
            if (!isValidEmail(email)) {
                alert('Please enter a valid email address');
                return;
            }
            
            // Submit form
            this.submit();
        });
    }
});

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}
</script>