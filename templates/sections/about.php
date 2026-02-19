<?php
// templates/sections/about.php
// Dynamic About Section - Uses your existing CSS classes

$title = $section['title'] ?? 'About Me';
$subtitle = $section['subtitle'] ?? 'Get to know me';
$content = $section['content'] ?? '';
$image = $section['settings']['image'] ?? 'about.jpg';
$skills = isset($section['settings']['skills']) ? json_decode($section['settings']['skills'], true) : [];

// Background styling
$bgColor = $section['background_color'] ?? '';
$bgImage = $section['background_image'] ?? '';
$sectionStyle = '';
if ($bgColor) $sectionStyle .= "background-color: $bgColor;";
if ($bgImage) $sectionStyle .= "background-image: url('" . UPLOAD_URL . "sections/$bgImage'); background-size: cover; background-position: center;";
?>

<!-- About Section - Using your existing CSS classes -->
<section id="<?php echo $section['section_key'] ?? 'about'; ?>" class="about section <?php echo $section['css_class'] ?? ''; ?>" style="<?php echo $sectionStyle; ?>">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle"><?php echo htmlspecialchars($subtitle); ?></span>
            <h2 class="section-title"><?php echo htmlspecialchars($title); ?></h2>
        </div>
        
        <div class="about-grid">
            <div class="about-content">
                <?php echo $content; ?>
                
                <?php if (!empty($skills)): ?>
                <div class="skills-container">
                    <?php foreach ($skills as $skill): ?>
                    <div class="skill-item">
                        <div class="skill-info">
                            <span><?php echo htmlspecialchars($skill['name']); ?></span>
                            <span><?php echo $skill['percentage']; ?>%</span>
                        </div>
                        <div class="skill-bar">
                            <div class="skill-progress" style="width: <?php echo $skill['percentage']; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($image): ?>
            <div class="about-image">
                <img src="<?php echo UPLOAD_URL . 'sections/' . $image; ?>" alt="<?php echo htmlspecialchars($title); ?>">
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($section['custom_css']): ?>
<style>
<?php echo $section['custom_css']; ?>
</style>
<?php endif; ?>