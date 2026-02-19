<?php
// cron/process-email-queue.php
// Process email queue - Run every minute via cron

require_once dirname(__DIR__) . '/includes/init.php';

$processed = mailer()->processQueue(50);

echo "[" . date('Y-m-d H:i:s') . "] Processed $processed emails\n";