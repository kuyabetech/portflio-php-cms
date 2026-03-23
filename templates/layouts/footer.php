    </main>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <!-- Newsletter Section -->
            <div class="footer-newsletter">
                <h3>Subscribe to My Newsletter</h3>
                <p>Get the latest updates on projects, blog posts, and tech insights delivered to your inbox.</p>
                <form class="newsletter-form" id="newsletterForm">
                    <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''; ?>">
                    <div class="newsletter-input-group">
                        <input type="email" name="email" placeholder="Your email address" required>
                        <button type="submit" class="newsletter-btn">
                            <i class="fas fa-paper-plane"></i> Subscribe
                        </button>
                    </div>
                    <div id="newsletterMessage" class="newsletter-message"></div>
                </form>
            </div>
            
            <!-- Main Footer Content -->
            <div class="footer-content">
                <div class="footer-brand">
                    <h3><?php 
                        $siteName = function_exists('getSetting') ? getSetting('site_name') : null;
                        echo htmlspecialchars($siteName ?: (defined('SITE_NAME') ? SITE_NAME : 'KVerify Digital Solutions')); 
                    ?></h3>
                    <p><?php 
                        $siteDesc = function_exists('getSetting') ? getSetting('site_description') : null;
                        echo htmlspecialchars($siteDesc ?: 'Portfolio & Blog - Web Developer & Designer'); 
                    ?></p>
                    
                    <div class="social-links">
                        <?php 
                        $github = function_exists('getSetting') ? getSetting('github_url') : null;
                        if ($github): 
                        ?>
                        <a href="<?php echo htmlspecialchars($github); ?>" target="_blank" rel="noopener noreferrer" aria-label="GitHub">
                            <i class="fab fa-github"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php 
                        $linkedin = function_exists('getSetting') ? getSetting('linkedin_url') : null;
                        if ($linkedin): 
                        ?>
                        <a href="<?php echo htmlspecialchars($linkedin); ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php 
                        $twitter = function_exists('getSetting') ? getSetting('twitter_url') : null;
                        if ($twitter): 
                        ?>
                        <a href="<?php echo htmlspecialchars($twitter); ?>" target="_blank" rel="noopener noreferrer" aria-label="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <?php endif; ?>
                        
                        <!-- Fallback social links if no settings -->
                        <?php if (!$github && !$linkedin && !$twitter): ?>
                        <a href="#" class="social-placeholder" title="GitHub">
                            <i class="fab fa-github"></i>
                        </a>
                        <a href="#" class="social-placeholder" title="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="social-placeholder" title="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="footer-links">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/">Home</a></li>
                        <li><a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/#skills">Skills</a></li>
                        <li><a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/project.php">Projects</a></li>
                        <li><a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/blog.php">Blog</a></li>
                        <li><a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/#testimonials">Testimonials</a></li>
                        <li><a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/contact">Contact</a></li>
                    </ul>
                </div>
                
                <div class="footer-contact">
                    <h4>Contact Info</h4>
                    <?php 
                    $contactEmail = function_exists('getSetting') ? getSetting('contact_email') : null;
                    $contactPhone = function_exists('getSetting') ? getSetting('contact_phone') : null;
                    $address = function_exists('getSetting') ? getSetting('address') : null;
                    $workingHours = function_exists('getSetting') ? getSetting('working_hours') : null;
                    ?>
                    
                    <p>
                        <i class="fas fa-envelope"></i>
                        <?php if ($contactEmail): ?>
                        <a href="mailto:<?php echo htmlspecialchars($contactEmail); ?>">
                            <?php echo htmlspecialchars($contactEmail); ?>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">email@example.com</span>
                        <?php endif; ?>
                    </p>
                    
                    <p>
                        <i class="fas fa-phone"></i>
                        <?php if ($contactPhone): ?>
                        <a href="tel:<?php echo htmlspecialchars($contactPhone); ?>">
                            <?php echo htmlspecialchars($contactPhone); ?>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">+1 234 567 890</span>
                        <?php endif; ?>
                    </p>
                    
                    <p>
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($address ?: 'New York, NY'); ?>
                    </p>
                    
                    <p>
                        <i class="fas fa-clock"></i>
                        <?php echo htmlspecialchars($workingHours ?: 'Mon-Fri 9AM-6PM'); ?>
                    </p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php 
                    $siteName = function_exists('getSetting') ? getSetting('site_name') : null;
                    echo htmlspecialchars($siteName ?: (defined('SITE_NAME') ? SITE_NAME : 'KVerify Digital Solutions')); 
                ?>. All rights reserved.</p>
                <p class="footer-credit">
                    Built with <i class="fas fa-heart" style="color: #ef4444;"></i> by 
                    <a href="<?php echo defined('BASE_URL') ? BASE_URL : '#'; ?>">
                        <?php 
                        $author = function_exists('getSetting') ? getSetting('author_name') : null;
                        echo htmlspecialchars($author ?: 'KVerify'); 
                        ?>
                    </a>
                </p>
            </div>
        </div>
    </footer>
    
    <!-- Back to top button -->
    <button id="backToTop" class="back-to-top" title="Back to Top" aria-label="Scroll to top">
        <i class="fas fa-arrow-up"></i>
    </button>
    
    <!-- Scripts -->
    <script src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/assets/js/main.js"></script>
    <script src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/assets/js/validation.js"></script>
    
    <!-- Newsletter Subscription Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const newsletterForm = document.getElementById('newsletterForm');
        if (newsletterForm) {
            newsletterForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const messageDiv = document.getElementById('newsletterMessage');
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subscribing...';
                messageDiv.style.display = 'none';
                
                try {
                    const response = await fetch('<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/subscribe.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    messageDiv.style.display = 'block';
                    messageDiv.className = 'newsletter-message ' + (data.success ? 'success' : 'error');
                    messageDiv.textContent = data.message;
                    
                    if (data.success) {
                        this.reset();
                        setTimeout(() => {
                            messageDiv.style.display = 'none';
                        }, 5000);
                    }
                } catch (error) {
                    messageDiv.style.display = 'block';
                    messageDiv.className = 'newsletter-message error';
                    messageDiv.textContent = 'Network error. Please try again.';
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        }
        
        // Back to top button functionality
        const backToTop = document.getElementById('backToTop');
        if (backToTop) {
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    backToTop.classList.add('show');
                } else {
                    backToTop.classList.remove('show');
                }
            });
            
            backToTop.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        }
        
        // Cookie consent
        const cookieConsent = document.getElementById('cookieConsent');
        if (cookieConsent && !localStorage.getItem('cookiesAccepted')) {
            cookieConsent.style.display = 'block';
        }
    });
    
    function acceptCookies() {
        localStorage.setItem('cookiesAccepted', 'true');
        document.getElementById('cookieConsent').style.display = 'none';
    }
    
    function declineCookies() {
        localStorage.setItem('cookiesAccepted', 'false');
        document.getElementById('cookieConsent').style.display = 'none';
    }
    </script>
    
    <style>
    /* Footer Styles */
    .footer {
        background: linear-gradient(135deg, #1e293b, #0f172a);
        color: #fff;
        padding: 60px 0 20px;
        position: relative;
        margin-top: 60px;
        width: 100%;
    }
    
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
        width: 100%;
        box-sizing: border-box;
    }
    
    /* Newsletter Section */
    .footer-newsletter {
        text-align: center;
        max-width: 600px;
        margin: 0 auto 40px;
        padding-bottom: 30px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .footer-newsletter h3 {
        font-size: 24px;
        margin-bottom: 10px;
        color: #fff;
    }
    
    .footer-newsletter p {
        color: rgba(255,255,255,0.7);
        margin-bottom: 20px;
        font-size: 16px;
    }
    
    .newsletter-form {
        max-width: 500px;
        margin: 0 auto;
    }
    
    .newsletter-input-group {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .newsletter-input-group input {
        flex: 1;
        min-width: 200px;
        padding: 14px 18px;
        border: 2px solid rgba(255,255,255,0.1);
        border-radius: 50px;
        background: rgba(255,255,255,0.05);
        color: white;
        font-size: 16px;
        transition: all 0.3s ease;
    }
    
    .newsletter-input-group input:focus {
        outline: none;
        border-color: #2563eb;
        background: rgba(255,255,255,0.1);
    }
    
    .newsletter-input-group input::placeholder {
        color: rgba(255,255,255,0.5);
    }
    
    .newsletter-btn {
        padding: 14px 30px;
        background: #2563eb;
        color: white;
        border: none;
        border-radius: 50px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
    }
    
    .newsletter-btn:hover {
        background: #1d4ed8;
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
    }
    
    .newsletter-btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }
    
    .newsletter-message {
        margin-top: 15px;
        padding: 10px;
        border-radius: 8px;
        font-size: 14px;
        display: none;
    }
    
    .newsletter-message.success {
        background: rgba(16, 185, 129, 0.2);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }
    
    .newsletter-message.error {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }
    
    /* Footer Content */
    .footer-content {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 40px;
        margin-bottom: 40px;
    }
    
    .footer-brand h3 {
        font-size: 24px;
        margin-bottom: 15px;
        color: #fff;
    }
    
    .footer-brand p {
        color: rgba(255,255,255,0.7);
        line-height: 1.6;
        margin-bottom: 20px;
    }
    
    .social-links {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .social-links a {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background: rgba(255,255,255,0.1);
        color: white;
        border-radius: 50%;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .social-links a:hover {
        background: #2563eb;
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
    }
    
    .social-links .social-placeholder {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .social-links .social-placeholder:hover {
        background: rgba(255,255,255,0.1);
        transform: none;
        box-shadow: none;
    }
    
    .footer-links h4,
    .footer-contact h4 {
        font-size: 18px;
        margin-bottom: 20px;
        color: #fff;
        position: relative;
        padding-bottom: 10px;
    }
    
    .footer-links h4::after,
    .footer-contact h4::after {
        content: '';
        position: absolute;
        left: 0;
        bottom: 0;
        width: 40px;
        height: 2px;
        background: #2563eb;
    }
    
    .footer-links ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .footer-links li {
        margin-bottom: 10px;
    }
    
    .footer-links a {
        color: rgba(255,255,255,0.7);
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-block;
    }
    
    .footer-links a:hover {
        color: #2563eb;
        transform: translateX(5px);
    }
    
    .footer-contact p {
        color: rgba(255,255,255,0.7);
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .footer-contact i {
        width: 20px;
        color: #2563eb;
    }
    
    .footer-contact a {
        color: rgba(255,255,255,0.7);
        text-decoration: none;
        transition: color 0.3s ease;
    }
    
    .footer-contact a:hover {
        color: #2563eb;
    }
    
    .footer-contact .text-muted {
        color: rgba(255,255,255,0.4);
    }
    
    /* Footer Bottom */
    .footer-bottom {
        text-align: center;
        padding-top: 20px;
        border-top: 1px solid rgba(255,255,255,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .footer-bottom p {
        color: rgba(255,255,255,0.5);
        margin: 0;
        font-size: 14px;
    }
    
    .footer-credit a {
        color: #2563eb;
        text-decoration: none;
    }
    
    .footer-credit a:hover {
        text-decoration: underline;
    }
    
    /* Back to Top Button */
    .back-to-top {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        background: #2563eb;
        color: white;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        z-index: 999;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }
    
    .back-to-top.show {
        opacity: 1;
        visibility: visible;
    }
    
    .back-to-top:hover {
        background: #1d4ed8;
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
    }
    
    /* Responsive Design */
    @media (max-width: 992px) {
        .footer-content {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .footer {
            padding: 40px 0 20px;
        }
        
        .footer-content {
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        .footer-newsletter h3 {
            font-size: 20px;
        }
        
        .newsletter-input-group {
            flex-direction: column;
        }
        
        .newsletter-btn {
            width: 100%;
            justify-content: center;
        }
        
        .footer-bottom {
            flex-direction: column;
            text-align: center;
        }
        
        .back-to-top {
            bottom: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            font-size: 16px;
        }
        
        .footer-links h4::after,
        .footer-contact h4::after {
            left: 50%;
            transform: translateX(-50%);
        }
        
        .footer-links ul {
            text-align: center;
        }
        
        .footer-links a:hover {
            transform: translateX(0) scale(1.1);
        }
        
        .footer-contact p {
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .footer-newsletter {
            padding: 0 10px 30px;
        }
        
        .social-links {
            justify-content: center;
        }
    }
    </style>
</body>
</html>