<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
secureHeaders();
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
$baseHtml = htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><base href="<?= $baseHtml ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="Encontre comércios e serviços do Alto Tietê. Busque por cidade e categoria e fale diretamente com os negócios da região."><title>Perto | Comércios do Alto Tietê</title><link rel="icon" href="favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="style.css?v=21"><link rel="stylesheet" href="responsive.css?v=3"><script src="app.js?v=27" defer></script></head><body data-base-path="<?= $baseHtml ?>"><div id="app"></div><div id="toast" role="status"></div></body></html>
