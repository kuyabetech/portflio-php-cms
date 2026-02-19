<?php
// templates/sections/testimonials.php
// Testimonials Carousel Section

// Get testimonials directly from database
$testimonials = db()->fetchAll("
    SELECT * FROM testimonials 
    WHERE status = 'approved' 
    ORDER BY is_featured DESC, id DESC 
    LIMIT 6
");

// Only show section if there are testimonials
if (empty($testimonials)) {
    return;
}
?>

<!-- Testimonials Carousel Section -->
<section class="testimonials-carousel section">
    <div class="container">
        <div class="section-header">
            <h2>Testimonials</h2>
            <p>What my clients say</p>
        </div>
        
        <div class="carousel-container">
            <div class="carousel-track" id="testimonialTrack">
                <?php foreach ($testimonials as $t): ?>
                <div class="carousel-slide">
                    <div class="testimonial-card">
                        <div class="testimonial-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star" style="color: <?php echo $i <= ($t['rating'] ?? 5) ? '#fbbf24' : '#ddd'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        
                        <p class="testimonial-text">"<?php echo htmlspecialchars($t['testimonial']); ?>"</p>
                        
                        <div class="testimonial-author">
                            <?php if (!empty($t['client_image'])): ?>
                            <img src="<?php echo UPLOAD_URL . 'testimonials/' . $t['client_image']; ?>" 
                                 alt="<?php echo htmlspecialchars($t['client_name']); ?>">
                            <?php else: ?>
                            <div class="author-avatar">
                                <?php echo strtoupper(substr($t['client_name'], 0, 1)); ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="author-info">
                                <h4><?php echo htmlspecialchars($t['client_name']); ?></h4>
                                <p><?php echo htmlspecialchars($t['client_position'] . ($t['client_company'] ? ', ' . $t['client_company'] : '')); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Carousel Navigation -->
            <button class="carousel-btn prev-btn" id="prevBtn">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="carousel-btn next-btn" id="nextBtn">
                <i class="fas fa-chevron-right"></i>
            </button>
            
            <!-- Carousel Dots -->
            <div class="carousel-dots" id="carouselDots">
                <?php for ($i = 0; $i < count($testimonials); $i++): ?>
                <span class="dot <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>"></span>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</section>

<style>
/* Testimonials Carousel Styles */
.testimonials-carousel {
    padding: 80px 0;
    background: #f8fafc;
    overflow: hidden;
}

.section-header {
    text-align: center;
    margin-bottom: 50px;
}

.section-header h2 {
    font-size: 2.5rem;
    color: #0f172a;
    margin-bottom: 10px;
}

.section-header p {
    font-size: 1.1rem;
    color: #64748b;
}

/* Carousel Container */
.carousel-container {
    position: relative;
    max-width: 1000px;
    margin: 0 auto;
    padding: 0 50px;
}

.carousel-track {
    display: flex;
    transition: transform 0.5s ease-in-out;
}

.carousel-slide {
    flex: 0 0 100%;
    padding: 0 15px;
    box-sizing: border-box;
}

/* Testimonial Card */
.testimonial-card {
    background: white;
    padding: 40px;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    text-align: center;
    max-width: 600px;
    margin: 0 auto;
}

.testimonial-rating {
    margin-bottom: 20px;
}

.testimonial-rating i {
    font-size: 1.2rem;
    margin: 0 2px;
}

.testimonial-text {
    color: #334155;
    font-size: 1.1rem;
    line-height: 1.8;
    margin-bottom: 30px;
    font-style: italic;
}

.testimonial-author {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.testimonial-author img {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #2563eb;
}

.author-avatar {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
    font-weight: 600;
    border: 3px solid #2563eb;
}

.author-info h4 {
    font-size: 1.2rem;
    color: #0f172a;
    margin-bottom: 5px;
}

.author-info p {
    color: #64748b;
    font-size: 0.95rem;
}

/* Carousel Navigation Buttons */
.carousel-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 45px;
    height: 45px;
    border: none;
    border-radius: 50%;
    background: white;
    color: #2563eb;
    font-size: 1.2rem;
    cursor: pointer;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    z-index: 10;
}

.carousel-btn:hover {
    background: #2563eb;
    color: white;
    transform: translateY(-50%) scale(1.1);
}

.prev-btn {
    left: 0;
}

.next-btn {
    right: 0;
}

/* Carousel Dots */
.carousel-dots {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 30px;
}

.dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #cbd5e1;
    cursor: pointer;
    transition: all 0.3s ease;
}

.dot:hover {
    background: #94a3b8;
}

.dot.active {
    background: #2563eb;
    transform: scale(1.2);
}

/* Responsive Design */
@media (max-width: 768px) {
    .testimonials-carousel {
        padding: 60px 0;
    }
    
    .section-header h2 {
        font-size: 2rem;
    }
    
    .carousel-container {
        padding: 0 40px;
    }
    
    .testimonial-card {
        padding: 30px 20px;
    }
    
    .testimonial-text {
        font-size: 1rem;
    }
    
    .testimonial-author img,
    .author-avatar {
        width: 60px;
        height: 60px;
    }
    
    .carousel-btn {
        width: 35px;
        height: 35px;
        font-size: 1rem;
    }
}

@media (max-width: 480px) {
    .testimonial-author {
        flex-direction: column;
        text-align: center;
    }
    
    .carousel-container {
        padding: 0 30px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const track = document.getElementById('testimonialTrack');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const dots = document.querySelectorAll('.dot');
    
    if (!track) return;
    
    let currentIndex = 0;
    const totalSlides = document.querySelectorAll('.carousel-slide').length;
    
    // Function to update carousel position
    function updateCarousel() {
        track.style.transform = `translateX(-${currentIndex * 100}%)`;
        
        // Update dots
        dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === currentIndex);
        });
    }
    
    // Next button click
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            currentIndex = (currentIndex + 1) % totalSlides;
            updateCarousel();
        });
    }
    
    // Previous button click
    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            currentIndex = (currentIndex - 1 + totalSlides) % totalSlides;
            updateCarousel();
        });
    }
    
    // Dot clicks
    dots.forEach((dot, index) => {
        dot.addEventListener('click', function() {
            currentIndex = index;
            updateCarousel();
        });
    });
    
    // Auto-advance carousel every 5 seconds
    let autoAdvance = setInterval(() => {
        currentIndex = (currentIndex + 1) % totalSlides;
        updateCarousel();
    }, 5000);
    
    // Pause auto-advance on hover
    const container = document.querySelector('.carousel-container');
    if (container) {
        container.addEventListener('mouseenter', function() {
            clearInterval(autoAdvance);
        });
        
        container.addEventListener('mouseleave', function() {
            autoAdvance = setInterval(() => {
                currentIndex = (currentIndex + 1) % totalSlides;
                updateCarousel();
            }, 5000);
        });
    }
    
    // Touch support for mobile
    let touchStartX = 0;
    let touchEndX = 0;
    
    track.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    });
    
    track.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    });
    
    function handleSwipe() {
        const swipeThreshold = 50;
        const diff = touchStartX - touchEndX;
        
        if (Math.abs(diff) > swipeThreshold) {
            if (diff > 0) {
                // Swipe left - next
                currentIndex = (currentIndex + 1) % totalSlides;
            } else {
                // Swipe right - previous
                currentIndex = (currentIndex - 1 + totalSlides) % totalSlides;
            }
            updateCarousel();
        }
    }
});
</script>