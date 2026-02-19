<?php
// templates/sections/custom.php
// Generic fallback template for any section type

// Ensure $section is defined
if (!isset($section)) {
    return;
}

// Extract section data with defaults
$title = $section['title'] ?? '';
$subtitle = $section['subtitle'] ?? '';
$content = $section['content'] ?? '';
$sectionType = $section['section_type'] ?? 'custom';
$sectionId = $section['id'] ?? 'custom-' . uniqid();
$sectionKey = $section['section_key'] ?? 'section-' . $sectionId;

// Background styling
$bgColor = $section['background_color'] ?? '';
$bgImage = $section['background_image'] ?? '';
$textColor = $section['text_color'] ?? '';

$sectionStyle = '';
if ($bgColor) $sectionStyle .= "background-color: $bgColor;";
if ($bgImage) $sectionStyle .= "background-image: url('" . UPLOAD_URL . "sections/$bgImage'); background-size: cover; background-position: center;";
if ($textColor) $sectionStyle .= "color: $textColor;";

// Layout class
$layoutClass = $section['layout_style'] ?? 'default';
?>

<!-- Custom Section: <?php echo $sectionType; ?> -->
<section id="<?php echo htmlspecialchars($sectionKey); ?>" 
         class="section section-custom section-<?php echo htmlspecialchars($sectionType); ?> <?php echo $section['css_class'] ?? ''; ?> layout-<?php echo $layoutClass; ?>" 
         style="<?php echo $sectionStyle; ?>"
         data-aos="<?php echo $section['data-aos'] ?? 'fade-up'; ?>"
         data-aos-delay="<?php echo $section['data-aos-delay'] ?? '0'; ?>">
    
    <div class="container">
        <?php if ($title || $subtitle): ?>
        <div class="section-header">
            <?php if ($subtitle): ?>
            <span class="section-subtitle"><?php echo htmlspecialchars($subtitle); ?></span>
            <?php endif; ?>
            <?php if ($title): ?>
            <h2 class="section-title"><?php echo htmlspecialchars($title); ?></h2>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($content): ?>
        <div class="section-content">
            <?php echo $content; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($section['custom_css'])): ?>
<style>
<?php echo $section['custom_css']; ?>
</style>
<?php endif; ?>