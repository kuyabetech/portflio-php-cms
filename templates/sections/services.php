<?php
// templates/sections/services.php
// Dynamic Services Section - Uses your existing CSS classes

$title = $section['title'] ?? 'Our Services';
$subtitle = $section['subtitle'] ?? 'What I Offer';
$description = $section['description'] ?? '';

// Background styling
$bgColor = $section['background_color'] ?? '';
$bgImage = $section['background_image'] ?? '';
$sectionStyle = '';
if ($bgColor) $sectionStyle .= "background-color: $bgColor;";
if ($bgImage) $sectionStyle .= "background-image: url('" . UPLOAD_URL . "sections/$bgImage'); background-size: cover; background-position: center;";

// Get service items from database
$services = db()->fetchAll("
    SELECT * FROM section_items 
    WHERE section_id = ? AND is_visible = 1 
    ORDER BY sort_order ASC
", [$section['id']]);

// If no items, try to get from skills table
if (empty($services)) {
    $skills = getSkills();
    foreach ($skills as $skill) {
        $services[] = [
            'id' => $skill['id'],
            'title' => $skill['name'],
            'description' => $skill['description'] ?? '',
            'icon' => $skill['icon_class'] ?? 'fas fa-code',
            'subtitle' => $skill['category'] ?? 'Technical'
        ];
    }
}
?>

<!-- Services Section - Using your existing CSS classes -->
<section id="<?php echo $section['section_key'] ?? 'services'; ?>" class="services section <?php echo $section['css_class'] ?? ''; ?>" style="<?php echo $sectionStyle; ?>">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle"><?php echo htmlspecialchars($subtitle); ?></span>
            <h2 class="section-title"><?php echo htmlspecialchars($title); ?></h2>
            <?php if ($description): ?>
            <p class="section-description"><?php echo htmlspecialchars($description); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="services-grid">
            <?php foreach ($services as $service): ?>
            <div class="service-card">
                <?php if ($service['icon']): ?>
                <div class="service-icon">
                    <i class="<?php echo htmlspecialchars($service['icon']); ?>"></i>
                </div>
                <?php endif; ?>
                
                <h3><?php echo htmlspecialchars($service['title']); ?></h3>
                
                <?php if ($service['subtitle']): ?>
                <p class="service-subtitle"><?php echo htmlspecialchars($service['subtitle']); ?></p>
                <?php endif; ?>
                
                <p class="service-description"><?php echo htmlspecialchars($service['description']); ?></p>
                
                <?php if (!empty($service['button_url'])): ?>
                <a href="<?php echo htmlspecialchars($service['button_url']); ?>" class="service-link">
                    <?php echo htmlspecialchars($service['button_text'] ?: 'Learn More'); ?> <i class="fas fa-arrow-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($section['custom_css']): ?>
<style>
<?php echo $section['custom_css']; ?>
</style>
<?php endif; ?>