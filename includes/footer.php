    </main>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3><?php echo getSetting('site_name'); ?></h3>
                    <p><?php echo getSetting('site_description'); ?></p>
                    
                    <div class="social-links">
                        <?php if (getSetting('github_url')): ?>
                        <a href="<?php echo getSetting('github_url'); ?>" target="_blank">
                            <i class="fab fa-github"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (getSetting('linkedin_url')): ?>
                        <a href="<?php echo getSetting('linkedin_url'); ?>" target="_blank">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (getSetting('twitter_url')): ?>
                        <a href="<?php echo getSetting('twitter_url'); ?>" target="_blank">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="footer-links">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#skills">Skills</a></li>
                        <li><a href="#projects">Projects</a></li>
                        <li><a href="#testimonials">Testimonials</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                
                <div class="footer-contact">
                    <h4>Contact Info</h4>
                    <p><i class="fas fa-envelope"></i> <?php echo getSetting('contact_email'); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo getSetting('contact_phone'); ?></p>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo getSetting('address'); ?></p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo getSetting('site_name'); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Back to top button -->
    <button id="backToTop" class="back-to-top" title="Back to Top">
        <i class="fas fa-arrow-up"></i>
    </button>
    
    <!-- Scripts -->
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/validation.js"></script>
</body>
</html>