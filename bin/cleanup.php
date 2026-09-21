<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
db()->exec('DELETE FROM login_limits WHERE started_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
// Adjust the retention period to the publisher's documented policy.
db()->exec('DELETE FROM audit_log WHERE created_at < UTC_TIMESTAMP() - INTERVAL 90 DAY');
echo "Registros antigos removidos.\n";
