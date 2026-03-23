<?php $featuredProjects = getProjects(6, true); ?>

<!-- Projects Section -->
<section id="projects" class="projects">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">My Work</span>
            <h2 class="section-title">Featured Projects</h2>
            <p class="section-description">
                Here are some of my recent projects that showcase my skills and expertise
            </p>
        </div>
        
        <div class="projects-grid">
            <?php foreach ($featuredProjects as $project): ?>
            <div class="project-card">
                <div class="project-image">
                    <img src="<?php echo UPLOAD_URL . $project['featured_image']; ?>" 
                         alt="<?php echo $project['title']; ?>">
                    <div class="project-overlay">
                        <div class="project-links">
                            <a href="<?php echo BASE_URL; ?>/project/<?php echo $project['slug']; ?>" 
                               class="project-link" title="View Details">
                                <i class="fas fa-search"></i>
                            </a>
                            <?php if ($project['project_url']): ?>
                            <a href="<?php echo $project['project_url']; ?>" target="_blank" 
                               class="project-link" title="Live Preview">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($project['github_url']): ?>
                            <a href="<?php echo $project['github_url']; ?>" target="_blank" 
                               class="project-link" title="View Code">
                                <i class="fab fa-github"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="project-info">
                    <h3><?php echo $project['title']; ?></h3>
                    <p><?php echo truncate($project['short_description'], 100); ?></p>
                    
                    <?php if ($project['technologies']): ?>
                    <div class="project-tech">
                        <?php 
                        $techs = explode(',', $project['technologies']);
                        foreach ($techs as $tech): 
                        ?>
                        <span class="tech-tag"><?php echo trim($tech); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="projects-cta">
            <a href="<?php echo BASE_URL; ?>/project.php" class="btn btn-outline">
                View All Projects <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>