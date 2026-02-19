<?php
// templates/sections/hero.php
// Hero Section with Dynamic Profile Data

// Get profile data from database
$profile_name = 'John Doe'; // Default
$profile_title = 'Professional Web Developer'; // Default
$profile_image = 'profile.jpg'; // Default
$profile_bio = ''; // Default
$years_experience = '5+'; // Default
$projects_count = '50'; // Default
$clients_count = '30'; // Default

try {
    // Get user profile from database (assuming first user is you)
    $user = db()->fetch("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
    
    if ($user) {
        $profile_name = $user['full_name'] ?: $user['username'] ?: 'John Doe';
        $profile_image = $user['profile_image'] ?: 'profile.jpg';
        $profile_bio = $user['bio'] ?? '';
    }
    
    // Get site settings
    $profile_title = getSetting('hero_title', 'Professional Web Developer');
    $years_experience = getSetting('years_experience', '5+');
    
    // Get real counts from database
    $projects_count = db()->fetch("SELECT COUNT(*) as count FROM projects WHERE status = 'published'")['count'] ?? 50;
    $clients_count = db()->fetch("SELECT COUNT(*) as count FROM clients")['count'] ?? 30;
    
} catch (Exception $e) {
    // Silently use defaults if any error
    error_log("Hero section error: " . $e->getMessage());
}

// Format the name for display
$first_name = explode(' ', $profile_name)[0];
$full_name = $profile_name;
?>

<!-- Hero Section -->
<section class="hero" id="home">
    <div class="container">
        <div class="hero-content">
            <span class="hero-subtitle">Welcome to my portfolio</span>
            <h1 class="hero-title">
                Hi, I'm <span class="highlight"><?php echo htmlspecialchars($first_name); ?></span><br>
                <?php echo htmlspecialchars($profile_title); ?>
            </h1>
            
            <?php if (!empty($profile_bio)): ?>
            <p class="hero-description">
                <?php echo htmlspecialchars($profile_bio); ?>
            </p>
            <?php else: ?>
            <p class="hero-description">
                I create stunning, high-performance websites and web applications 
                that help businesses grow and succeed online. With years of 
                experience, I deliver solutions that exceed expectations.
            </p>
            <?php endif; ?>
            
            <div class="hero-buttons">
                <a href="#contact" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i>
                    Hire Me
                </a>
                <a href="#projects" class="btn btn-outline">
                    <i class="fas fa-eye"></i>
                    View My Work
                </a>
            </div>
            
            <div class="hero-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $projects_count; ?></span>
                    <span class="stat-label">Projects</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $clients_count; ?></span>
                    <span class="stat-label">Clients</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $years_experience; ?></span>
                    <span class="stat-label">Years</span>
                </div>
            </div>
        </div>
        
        <div class="hero-image">
            <div class="image-wrapper">
                <?php 
                $image_path = BASE_URL . '/assets/images/uploads/' . $profile_image;
                // Check if profile image exists in uploads folder
                if (file_exists(UPLOAD_PATH . 'profiles/' . $profile_image)) {
                    $image_path = UPLOAD_URL . 'profiles/' . $profile_image;
                }
                ?>
                <img src="<?php echo $image_path; ?>" 
                     alt="<?php echo htmlspecialchars($full_name); ?>"
                     onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.jpg'; this.onerror=null;">
                
                <?php if ($years_experience): ?>
                <div class="experience-badge">
                    <span class="years"><?php echo $years_experience; ?></span>
                    <span class="text">Years Experience</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<style>
/* Hero Section Styles */
.hero {
    min-height: 100vh;
    display: flex;
    align-items: center;
    padding: 80px 0;
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    position: relative;
    overflow: hidden;
}

.hero .container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4rem;
    align-items: center;
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 2rem;
}

.hero-content {
    max-width: 600px;
}

.hero-subtitle {
    display: inline-block;
    font-size: 1rem;
    font-weight: 600;
    color: #2563eb;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 1rem;
    background: rgba(37, 99, 235, 0.1);
    padding: 0.5rem 1rem;
    border-radius: 30px;
}

.hero-title {
    font-size: clamp(2.5rem, 5vw, 4rem);
    font-weight: 700;
    margin-bottom: 1.5rem;
    line-height: 1.2;
    color: #0f172a;
}

.hero-title .highlight {
    color: #2563eb;
    position: relative;
    display: inline-block;
}

.hero-title .highlight::after {
    content: '';
    position: absolute;
    bottom: 5px;
    left: 0;
    width: 100%;
    height: 10px;
    background: rgba(37, 99, 235, 0.1);
    z-index: -1;
}

.hero-description {
    font-size: 1.125rem;
    color: #475569;
    margin-bottom: 2rem;
    line-height: 1.8;
    max-width: 500px;
}

.hero-buttons {
    display: flex;
    gap: 1rem;
    margin-bottom: 3rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.875rem 2rem;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    cursor: pointer;
}

.btn-primary {
    background: #2563eb;
    color: white;
    box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
}

.btn-primary:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
}

.btn-outline {
    background: transparent;
    color: #2563eb;
    border-color: #2563eb;
}

.btn-outline:hover {
    background: #2563eb;
    color: white;
    transform: translateY(-2px);
}

.hero-stats {
    display: flex;
    gap: 3rem;
}

.stat-item {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: 2rem;
    font-weight: 700;
    color: #2563eb;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hero-image {
    position: relative;
}

.image-wrapper {
    position: relative;
    border-radius: 30px;
    overflow: hidden;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.image-wrapper img {
    width: 100%;
    height: auto;
    display: block;
    transition: transform 0.5s ease;
}

.image-wrapper:hover img {
    transform: scale(1.05);
}

.experience-badge {
    position: absolute;
    bottom: 30px;
    right: -20px;
    background: white;
    padding: 1rem 1.5rem;
    border-radius: 50px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    text-align: center;
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}

.experience-badge .years {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: #2563eb;
    line-height: 1;
}

.experience-badge .text {
    font-size: 0.875rem;
    color: #475569;
}

/* Responsive */
@media (max-width: 768px) {
    .hero {
        padding: 100px 0 60px;
    }
    
    .hero .container {
        grid-template-columns: 1fr;
        text-align: center;
        gap: 40px;
    }
    
    .hero-content {
        margin: 0 auto;
    }
    
    .hero-description {
        margin-left: auto;
        margin-right: auto;
    }
    
    .hero-buttons {
        justify-content: center;
    }
    
    .hero-stats {
        justify-content: center;
        gap: 2rem;
    }
    
    .hero-image {
        max-width: 400px;
        margin: 0 auto;
    }
    
    .experience-badge {
        right: -10px;
        bottom: -10px;
        padding: 0.75rem 1rem;
    }
}

@media (max-width: 480px) {
    .hero-buttons {
        flex-direction: column;
    }
    
    .hero-stats {
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .stat-item {
        flex: 1 1 calc(50% - 1rem);
    }
}
</style>