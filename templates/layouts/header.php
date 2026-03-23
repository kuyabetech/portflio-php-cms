<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Primary Meta Tags -->
    <title><?php 
        $pageTitle = isset($pageTitle) ? $pageTitle . ' - ' : '';
        echo $pageTitle . getSetting('site_title', 'Kverify Digital Solutions - Professional Web Developer Portfolio'); 
    ?></title>
    <meta name="title" content="<?php 
        echo isset($pageTitle) ? $pageTitle . ' - ' . getSetting('site_title', 'Kverify Digital Solutions') : getSetting('site_title', 'Kverify Digital Solutions - Professional Web Developer Portfolio'); 
    ?>">
    <meta name="description" content="<?php 
        echo isset($pageDescription) ? $pageDescription : getSetting('site_description', 'Professional web developer specializing in custom PHP solutions, responsive designs, and web applications.'); 
    ?>">
    <meta name="keywords" content="<?php echo getSetting('site_keywords', 'web developer, PHP developer, portfolio, custom websites'); ?>">
    <meta name="author" content="<?php echo getSetting('site_name', 'Kverify Digital Solutions'); ?>">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo BASE_URL . $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:title" content="<?php 
        echo isset($pageTitle) ? $pageTitle . ' - ' . getSetting('site_name', 'Kverify Digital Solutions') : getSetting('site_title', 'Kverify Digital Solutions'); 
    ?>">
    <meta property="og:description" content="<?php 
        echo isset($pageDescription) ? $pageDescription : getSetting('site_description', 'Professional web developer portfolio'); 
    ?>">
    <meta property="og:image" content="<?php 
        echo isset($pageImage) ? $pageImage : getSetting('og_image', BASE_URL . '/assets/images/og-image.jpg'); 
    ?>">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo BASE_URL . $_SERVER['REQUEST_URI']; ?>">
    <meta property="twitter:title" content="<?php 
        echo isset($pageTitle) ? $pageTitle . ' - ' . getSetting('site_name', 'Kverify Digital Solutions') : getSetting('site_title', 'Kverify Digital Solutions'); 
    ?>">
    <meta property="twitter:description" content="<?php 
        echo isset($pageDescription) ? $pageDescription : getSetting('site_description', 'Professional web developer portfolio'); 
    ?>">
    <meta property="twitter:image" content="<?php 
        echo isset($pageImage) ? $pageImage : getSetting('og_image', BASE_URL . '/assets/images/og-image.jpg'); 
    ?>">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo BASE_URL . $_SERVER['REQUEST_URI']; ?>">
    
    <!-- Sitemap Links -->
    <link rel="sitemap" type="application/xml" title="Sitemap" href="<?php echo BASE_URL; ?>/sitemap.xml">
    
    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL; ?>/assets/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>/assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo BASE_URL; ?>/assets/images/favicon-16x16.png">
    <link rel="manifest" href="<?php echo BASE_URL; ?>/site.webmanifest">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/responsive.css">
    
    <!-- Dynamic Theme Color -->
    <meta name="theme-color" content="<?php echo getSetting('primary_color', '#2563eb'); ?>">
    
    <!-- Analytics Script (Only in production) -->
    <?php if (!defined('DEV_MODE') || !DEV_MODE): ?>
    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo getSetting('ga_tracking_id', ''); ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?php echo getSetting('ga_tracking_id', ''); ?>');
    </script>
    <!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-3L07WHV6B3"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-3L07WHV6B3');
</script>
    <?php endif; ?>
    
    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Person",
        "name": "<?php echo getSetting('site_name', 'Kverify Digital Solutions'); ?>",
        "url": "<?php echo BASE_URL; ?>",
        "sameAs": [
            "<?php echo getSetting('social_github', '#'); ?>",
            "<?php echo getSetting('social_linkedin', '#'); ?>",
            "<?php echo getSetting('social_twitter', '#'); ?>"
        ],
        "jobTitle": "Web Developer",
        "worksFor": {
            "@type": "Organization",
            "name": "<?php echo getSetting('site_name', 'Kverify Digital Solutions'); ?>"
        }
    }
    </script>
    
    <style>
        :root {
            --primary: <?php echo getSetting('primary_color', '#2563eb'); ?>;
            --primary-dark: <?php echo function_exists('adjustBrightness') ? adjustBrightness(getSetting('primary_color', '#2563eb'), -20) : '#1d4ed8'; ?>;
            --primary-light: <?php echo function_exists('adjustBrightness') ? adjustBrightness(getSetting('primary_color', '#2563eb'), 20) : '#60a5fa'; ?>;
            --secondary: <?php echo getSetting('secondary_color', '#7c3aed'); ?>;
            --secondary-dark: <?php echo function_exists('adjustBrightness') ? adjustBrightness(getSetting('secondary_color', '#7c3aed'), -20) : '#6d28d9'; ?>;
            --secondary-light: <?php echo function_exists('adjustBrightness') ? adjustBrightness(getSetting('secondary_color', '#7c3aed'), 20) : '#a78bfa'; ?>;
        }
        
        /* Dropdown Menu Styles */
        .dropdown {
            position: relative;
        }

        .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .dropdown-toggle i {
            font-size: 0.8rem;
            transition: transform 0.3s ease;
        }

        .dropdown:hover .dropdown-toggle i {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 200px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 10px 0;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
            z-index: 1000;
            border: 1px solid #e2e8f0;
            list-style: none;
        }

        .dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: block;
            padding: 10px 20px;
            color: #1e293b;
            text-decoration: none;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: #f8fafc;
            color: #2563eb;
            padding-left: 25px;
        }

        /* Mobile Responsive for Dropdowns */
        @media (max-width: 768px) {
            .dropdown {
                width: 100%;
            }
            
            .dropdown-menu {
                position: static;
                opacity: 1;
                visibility: visible;
                transform: none;
                box-shadow: none;
                border: none;
                padding: 0 0 0 20px;
                background: transparent;
                display: none;
            }
            
            .dropdown.active .dropdown-menu {
                display: block;
            }
            
            .dropdown-toggle i {
                margin-left: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Animation -->
<!--    <div class="loader-wrapper" id="loader">
        <div class="loader"></div>
    </div>-->

    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="container">
            <div class="nav-wrapper">
                <!-- Logo -->
                <a href="<?php echo BASE_URL; ?>" class="logo">
                    <?php 
                    $site_logo = getSetting('site_logo');
                    if (!empty($site_logo) && file_exists(UPLOAD_PATH . 'settings/' . $site_logo)): 
                    ?>
                        <img src="<?php echo UPLOAD_URL . 'settings/' . $site_logo; ?>" 
                             alt="<?php echo getSetting('site_name', 'Kverify'); ?>" 
                             class="logo-img">
                    <?php else: ?>
                        <?php echo getSetting('site_name', 'Kverify'); ?>
                    <?php endif; ?>
                </a>
                
                <!-- Desktop Navigation - Enhanced with more items -->
                <ul class="nav-menu" id="navMenu">
                    <!-- Main Sections -->
                    <li><a href="#home" class="nav-link">Home</a></li>
                    <li><a href="#about" class="nav-link">About</a></li>
                    <li><a href="#skills" class="nav-link">Skills</a></li>
                    <li><a href="#projects" class="nav-link">Projects</a></li>
                    <li><a href="#testimonials" class="nav-link">Testimonials</a></li>
                    <li><a href="#contact" class="nav-link">Contact</a></li>
                    
                    <!-- Blog Link (if blog exists) -->
                    <?php
                    $blog_count = 0;
                    try {
                        $blog_count = db()->fetch("SELECT COUNT(*) as count FROM blog_posts WHERE status = 'published'")['count'] ?? 0;
                    } catch (Exception $e) {}
                    if ($blog_count > 0):
                    ?>
                    <li><a href="<?php echo BASE_URL; ?>/blog.php" class="nav-link">Blog</a></li>
                    <?php endif; ?>
                    
                    <!-- Services Dropdown -->
                    <li class="dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            Services <i class="fas fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="#web-development" class="dropdown-item">Web Development</a></li>
                            <li><a href="#app-development" class="dropdown-item">App Development</a></li>
                            <li><a href="#consulting" class="dropdown-item">Consulting</a></li>
                            <li><a href="maintenance.php" class="dropdown-item">Maintenance</a></li>
                        </ul>
                    </li>
                    
                    <!-- Resources Dropdown -->
                    <li class="dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            Resources <i class="fas fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="<?php echo BASE_URL; ?>/tutorials.php" class="dropdown-item">Tutorials</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/guides.php" class="dropdown-item">Guides</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/faq.php" class="dropdown-item">FAQ</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/support" class="dropdown-item">Support</a></li>
                        </ul>
                    </li>
                    
                    <!-- Call to Action Button -->
                    <li class="nav-cta">
                        <a href="#contact" class="btn btn-primary btn-sm">
                            <i class="fas fa-paper-plane"></i>
                            Hire Me
                        </a>
                    </li>
                </ul>
                
                <!-- Mobile Menu Button -->
                <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle navigation menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </nav>

    <!-- Progress Bar -->
    <div class="progress-container">
        <div class="progress-bar" id="progressBar"></div>
    </div>

    <main>