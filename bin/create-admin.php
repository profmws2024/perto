<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404);exit; }
require dirname(__DIR__).'/app/bootstrap.php';
$name=trim(readline('Nome: '));$email=strtolower(trim(readline('E-mail: ')));
if (!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($name)<2||mb_strlen($name)>100) exit("Nome ou e-mail inválido.\n");
// Password comes from STDIN, never a CLI argument or a public setup page.
$tty=function_exists('stream_isatty') && stream_isatty(STDIN) && PHP_OS_FAMILY !== 'Windows';
if ($tty) system('stty -echo');
try { echo 'Senha (12+ caracteres): ';$password=rtrim(fgets(STDIN),"\r\n"); } finally { if ($tty) system('stty echo'); echo "\n"; }
if (mb_strlen($password)<12||strlen($password)>72) exit("Use ao menos 12 caracteres e no máximo 72 bytes.\n");
$q=db()->prepare('INSERT INTO users (name,email,password_hash) VALUES (?,?,?)');
$q->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
echo "Administrador criado. Nenhuma senha padrão foi instalada.\n";
