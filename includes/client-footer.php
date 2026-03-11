<?php
// includes/client-footer.php
?>
    </main>
    
    <footer class="client-footer">
        <div class="footer-container">
            <div class="footer-copyright">
                &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
            </div>
            <div class="footer-links">
                <a href="privacy.php">Privacy Policy</a>
                <a href="terms.php">Terms of Service</a>
                <a href="help.php">Help Center</a>
            </div>
        </div>
    </footer>

    <style>
    .client-footer {
        background: white;
        border-top: 1px solid #e2e8f0;
        padding: 20px 0;
        margin-top: 40px;
    }
    
    .footer-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .footer-copyright {
        color: #64748b;
        font-size: 14px;
    }
    
    .footer-links {
        display: flex;
        gap: 20px;
    }
    
    .footer-links a {
        color: #64748b;
        text-decoration: none;
        font-size: 14px;
        transition: color 0.2s ease;
    }
    
    .footer-links a:hover {
        color: #667eea;
    }
    
    @media (max-width: 768px) {
        .footer-container {
            flex-direction: column;
            text-align: center;
        }
        
        .footer-links {
            justify-content: center;
        }
    }
    </style>
</body>
</html>