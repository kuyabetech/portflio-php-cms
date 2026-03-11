<?php
// client/check-tables.php
require_once dirname(__DIR__) . '/includes/init.php';

echo "<h1>Database Structure Check</h1>";

// Check projects table
echo "<h2>Projects Table Structure:</h2>";
try {
    $projectsColumns = db()->fetchAll("SHOW COLUMNS FROM projects");
    echo "<pre>";
    print_r($projectsColumns);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Check project_invoices table
echo "<h2>Project Invoices Table Structure:</h2>";
try {
    $invoicesColumns = db()->fetchAll("SHOW COLUMNS FROM project_invoices");
    echo "<pre>";
    print_r($invoicesColumns);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Check client_users table
echo "<h2>Client Users Table Structure:</h2>";
try {
    $clientColumns = db()->fetchAll("SHOW COLUMNS FROM client_users");
    echo "<pre>";
    print_r($clientColumns);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}
?>