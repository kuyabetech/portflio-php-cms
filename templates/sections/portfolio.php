<?php
// templates/sections/portfolio.php
// Dynamic Portfolio Section - Uses your existing CSS classes

$title = $section['title'] ?? 'My Work';
$subtitle = $section['subtitle'] ?? 'Recent Projects';
$description = $section['description'] ?? '';

// Background styling
$bgColor = $section['background_color'] ?? '';
$bgImage = $section['background_image'] ?? '';
$sectionStyle = '';
if ($bgColor) $sectionStyle .= "background-color: $bgColor;";
if ($bgImage) $sectionStyle .= "background-image: url('" . UPLOAD_URL . "sections/$bgImage'); background-size: cover; background-position: center;";

// Get portfolio items from section_items
$projects = db()->fetchAll("
    SELECT * FROM section_items 
    WHERE section_id = ? AND is_visible = 1 
    ORDER BY sort_order ASC
", [$section['id']]);

// If no items, get from projects table
if (empty($projects)) {
    $featuredProjects = getProjects(6, true);
    foreach ($featuredProjects as $project) {
        $projects[] = [
            'id' => $project['id'],
            'title' => $project['title'],
            'description' => $project['short_description'],
            'image' => $project['featured_image'],
            'link' => $project['project_url'],
            'github' => $project['github_url'],
            'technologies' => $project['technologies']
        ];
    }
}
?>

<!-- Portfolio Section - Using your existing CSS classes -->
<section id="<?php echo $section['section_key'] ?? 'portfolio'; ?>" class="projects section <?php echo $section['css_class'] ?? ''; ?>" style="<?php echo $sectionStyle; ?>">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle"><?php echo htmlspecialchars($subtitle); ?></span>
            <h2 class="section-title"><?php echo htmlspecialchars($title); ?></h2>
            <?php if ($description): ?>
            <p class="section-description"><?php echo htmlspecialchars($description); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="projects-grid">
            <?php foreach ($projects as $project): ?>
            <div class="project-card">
                <div class="project-image">
                    <img src="<?php echo UPLOAD_URL . (isset($project['image']) ? 'sections/' . $project['image'] : 'projects/' . $project['featured_image']); ?>" 
                         alt="<?php echo htmlspecialchars($project['title']); ?>">
                    <div class="project-overlay">
                        <div class="project-links">
                            <a href="<?php echo BASE_URL; ?>/project/<?php echo $project['slug'] ?? $project['id']; ?>" class="project-link" title="View Details">
                                <i class="fas fa-search"></i>
                            </a>
                            <?php if (!empty($project['link'])): ?>
                            <a href="<?php echo $project['link']; ?>" target="_blank" class="project-link" title="Live Preview">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($project['github'])): ?>
                            <a href="<?php echo $project['github']; ?>" target="_blank" class="project-link" title="View Code">
                                <i class="fab fa-github"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="project-info">
                    <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                    <p><?php echo htmlspecialchars(substr($project['description'] ?? '', 0, 100)); ?>...</p>
                    
                    <?php if (!empty($project['technologies'])): ?>
                    <div class="project-tech">
                        <?php 
                        $techs = explode(',', $project['technologies']);
                        foreach (array_slice($techs, 0, 3) as $tech): 
                        ?>
                        <span class="tech-tag"><?php echo trim($tech); ?></span>
                        <?php endforeach; ?>
                        <?php if (count($techs) > 3): ?>
                        <span class="tech-tag">+<?php echo count($techs) - 3; ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
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