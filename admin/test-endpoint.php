<?php
// admin/test-endpoint.php
// Test if AJAX endpoint is accessible

require_once dirname(__DIR__) . '/includes/init.php';

echo "<h1>SMTP Test Endpoint Test</h1>";

// Check if file exists
$ajaxFile = __DIR__ . '/ajax/test-smtp.php';
if (file_exists($ajaxFile)) {
    echo "<p style='color:green'>✓ test-smtp.php exists at: $ajaxFile</p>";
} else {
    echo "<p style='color:red'>✗ test-smtp.php NOT found at: $ajaxFile</p>";
}

// Check permissions
if (is_readable($ajaxFile)) {
    echo "<p style='color:green'>✓ File is readable</p>";
} else {
    echo "<p style='color:red'>✗ File is not readable</p>";
}

// Test URL
$url = BASE_URL . '/admin/ajax/test-smtp.php';
echo "<p>Test URL: <a href='$url' target='_blank'>$url</a></p>";
echo "<p>Click the link - you should see a JSON error message (not a 404)</p>";

// Test with a simple fetch
echo "<h2>JavaScript Test</h2>";
echo "<button onclick='testEndpoint()' style='padding: 10px; background: #2563eb; color: white; border: none; border-radius: 5px; cursor: pointer;'>Test AJAX Endpoint</button>";
echo "<div id='result' style='margin-top: 20px;'></div>";

echo "<script>
function testEndpoint() {
    const result = document.getElementById('result');
    result.innerHTML = 'Testing...';
    
    fetch('ajax/test-smtp.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({test: true})
    })
    .then(response => response.json())
    .then(data => {
        result.innerHTML = '<pre style=\"background: #f0f0f0; padding: 10px;\">' + JSON.stringify(data, null, 2) + '</pre>';
    })
    .catch(error => {
        result.innerHTML = '<p style=\"color:red\">Error: ' + error.message + '</p>';
    });
}
</script>";