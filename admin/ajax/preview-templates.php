<?php
// admin/ajax/preview-template.php
// Preview template

require_once dirname(__DIR__, 2) . '/includes/init.php';

if (!Auth::check()) {
    die('Unauthorized');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$template = db()->fetch("SELECT * FROM newsletter_templates WHERE id = ?", [$id]);

if (!$template) {
    die('Template not found');
}

// Personalize preview
$content = $template['content'];
$content = str_replace('{{first_name}}', 'John', $content);
$content = str_replace('{{last_name}}', 'Doe', $content);
$content = str_replace('{{email}}', 'john@example.com', $content);
$content = str_replace('{{unsubscribe_url}}', '#', $content);
$content = str_replace('{{site_name}}', SITE_NAME, $content);
$content = str_replace('{{site_url}}', BASE_URL, $content);
$content = str_replace('{{year}}', date('Y'), $content);
$content = str_replace('{{primary_color}}', getSetting('primary_color', '#2563eb'), $content);
$content = str_replace('{{secondary_color}}', getSetting('secondary_color', '#7c3aed'), $content);

echo $content;
?>