<?php
// templates/sections/skills.php
// Simple skills section

// Get skills from database
$skills = db()->fetchAll("SELECT * FROM skills WHERE is_visible = 1 ORDER BY display_order ASC");

if (empty($skills)) {
    return;
}
?>

<!-- Skills Section -->
<section class="skills section">
    <div class="container">
        <div class="section-header">
            <h2>My Skills</h2>
            <p>Technologies I work with</p>
        </div>
        
        <div class="skills-grid">
            <?php foreach ($skills as $skill): ?>
            <div class="skill-card">
                <div class="skill-header">
                    <?php if (!empty($skill['icon_class'])): ?>
                    <i class="<?php echo $skill['icon_class']; ?>"></i>
                    <?php endif; ?>
                    <h3><?php echo htmlspecialchars($skill['name']); ?></h3>
                </div>
                
                <div class="skill-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $skill['proficiency']; ?>%"></div>
                    </div>
                    <span class="skill-percentage"><?php echo $skill['proficiency']; ?>%</span>
                </div>
                
                <?php if (!empty($skill['years_experience'])): ?>
                <p class="skill-experience"><?php echo $skill['years_experience']; ?>+ years experience</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<style>
.skills {
    padding: 80px 0;
}

.skills-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-top: 40px;
}

.skill-card {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.skill-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.skill-header i {
    font-size: 24px;
    color: #2563eb;
}

.skill-header h3 {
    margin: 0;
}

.skill-progress {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.progress-bar {
    flex: 1;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #2563eb, #7c3aed);
    border-radius: 4px;
}

.skill-percentage {
    font-weight: 600;
    color: #2563eb;
}

.skill-experience {
    color: #6b7280;
    font-size: 0.9rem;
    margin: 0;
}
</style>