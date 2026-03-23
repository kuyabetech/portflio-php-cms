<?php
// Newsletter Popup Form
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$showPopup = !isset($_COOKIE['newsletter_popup_closed']) && !isset($_SESSION['subscribed']);
?>

<?php if ($showPopup): ?>
<div class="newsletter-popup" id="newsletterPopup">
    <div class="popup-overlay" onclick="closePopup()"></div>
    <div class="popup-container">
        <button class="popup-close" onclick="closePopup()">&times;</button>
        
        <div class="popup-content">
            <div class="popup-icon">
                <i class="fas fa-envelope-open-text"></i>
            </div>
            <h2>Never Miss an Update!</h2>
            <p>Subscribe to my newsletter and get the latest projects, blog posts, and tech insights delivered directly to your inbox.</p>
            
            <form class="popup-form" id="popupForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                
                <!-- Honeypot -->
                <div style="display: none;">
                    <input type="text" name="website">
                </div>
                
                <input type="text" name="name" placeholder="Your name (optional)" class="popup-input">
                <input type="email" name="email" placeholder="Your email address" required class="popup-input">
                
                <button type="submit" class="popup-btn">
                    <i class="fas fa-paper-plane"></i> Subscribe Now
                </button>
                
                <div class="popup-message" id="popupMessage"></div>
                
                <p class="popup-note">
                    <i class="fas fa-lock"></i> No spam. Unsubscribe anytime.
                </p>
            </form>
            
            <a href="#" class="popup-no-thanks" onclick="closePopup(); return false;">No thanks, I'm not interested</a>
        </div>
    </div>
</div>

<style>
.newsletter-popup {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.popup-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
}

.popup-container {
    position: relative;
    background: white;
    border-radius: 20px;
    padding: 40px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    animation: popupSlideUp 0.5s ease;
}

.popup-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #94a3b8;
    transition: color 0.3s ease;
}

.popup-close:hover {
    color: #ef4444;
}

.popup-icon {
    text-align: center;
    font-size: 48px;
    color: #2563eb;
    margin-bottom: 20px;
}

.popup-content h2 {
    text-align: center;
    color: #1e293b;
    margin-bottom: 15px;
}

.popup-content p {
    text-align: center;
    color: #64748b;
    margin-bottom: 25px;
    line-height: 1.6;
}

.popup-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.popup-input {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.popup-input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

.popup-btn {
    background: #2563eb;
    color: white;
    border: none;
    padding: 14px;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.popup-btn:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(37,99,235,0.2);
}

.popup-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.popup-message {
    padding: 10px;
    border-radius: 8px;
    font-size: 0.9rem;
    display: none;
    text-align: center;
}

.popup-message.success {
    background: rgba(16,185,129,0.1);
    color: #10b981;
}

.popup-message.error {
    background: rgba(239,68,68,0.1);
    color: #ef4444;
}

.popup-note {
    text-align: center;
    color: #94a3b8;
    font-size: 0.85rem;
    margin-top: 10px;
}

.popup-no-thanks {
    display: block;
    text-align: center;
    margin-top: 15px;
    color: #94a3b8;
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.3s ease;
}

.popup-no-thanks:hover {
    color: #64748b;
}

@keyframes popupSlideUp {
    from {
        opacity: 0;
        transform: translateY(50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .popup-container {
        padding: 30px 20px;
    }
}
</style>

<script>
function closePopup() {
    document.getElementById('newsletterPopup').style.display = 'none';
    // Set cookie to remember popup was closed
    document.cookie = "newsletter_popup_closed=1; path=/; max-age=86400"; // 24 hours
}

document.addEventListener('DOMContentLoaded', function() {
    const popupForm = document.getElementById('popupForm');
    const messageDiv = document.getElementById('popupMessage');
    
    if (popupForm) {
        popupForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subscribing...';
            messageDiv.style.display = 'none';
            
            try {
                const response = await fetch('<?php echo BASE_URL; ?>/subscribe.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    messageDiv.className = 'popup-message success';
                    messageDiv.textContent = data.message;
                    messageDiv.style.display = 'block';
                    
                    // Close popup after 2 seconds
                    setTimeout(() => {
                        closePopup();
                    }, 2000);
                } else {
                    messageDiv.className = 'popup-message error';
                    messageDiv.textContent = data.message;
                    messageDiv.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Subscribe Now';
                }
            } catch (error) {
                messageDiv.className = 'popup-message error';
                messageDiv.textContent = 'Network error. Please try again.';
                messageDiv.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Subscribe Now';
            }
        });
    }
});
</script>
<?php endif; ?>