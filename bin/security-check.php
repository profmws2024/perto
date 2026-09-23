<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
$checks = [
    'PHP 8.4 ou superior (manter patches atualizados)' => PHP_VERSION_ID >= 80400,
    'APP_ENV=production' => getenv('APP_ENV') === 'production',
    'Origem HTTPS configurada' => validHttpsUrl(getenv('APP_ORIGIN') ?: ''),
    'Banco usa conta diferente de root' => strtolower(getenv('DB_USER') ?: 'root') !== 'root',
    'Senha de banco definida' => (getenv('DB_PASS') ?: '') !== '',
    'pdo_mysql disponível' => extension_loaded('pdo_mysql'),
    'mbstring disponível' => extension_loaded('mbstring'),
    'fileinfo disponível' => extension_loaded('fileinfo'),
    'Bloqueio Apache de uploads presente' => is_file(dirname(__DIR__).'/public/uploads/.htaccess'),
];
foreach ($checks as $label=>$passed) echo ($passed ? '[OK] ' : '[PENDENTE] ').$label.PHP_EOL;
echo 'Este comando não certifica segurança nem verifica firewall, TLS, privilégios SQL ou a aplicação das regras pelo servidor.'.PHP_EOL;
exit(in_array(false, $checks, true) ? 1 : 0);
