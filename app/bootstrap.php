<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

// ==================================================
// CONFIGURAÇÃO LOCAL — XAMPP
// Substitua estes valores antes de publicar na internet.
// ==================================================
// Environment supplied by the server always wins. Local defaults never permit
// remote HTTP clients; production requires explicit credentials and HTTPS.
if (getenv('APP_ENV') === false) putenv('APP_ENV=local');
$localEnvironment = getenv('APP_ENV') === 'local';
if ($localEnvironment && PHP_SAPI !== 'cli') {
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        exit('Acesso não permitido.');
    }
}
if ($localEnvironment) {
    $localConfig = is_file(__DIR__.'/config.local.php') ? require __DIR__.'/config.local.php' : [];
    foreach ($localConfig as $key=>$value) {
        if (getenv($key) === false) putenv($key.'='.$value);
    }
    unset($localConfig, $key, $value);
} elseif (getenv('APP_ENV') !== 'production') {
    http_response_code(503);
    error_log('Perto: APP_ENV inválido.');
    exit('Serviço indisponível.');
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');

const CATEGORIES = [
    'Alimentação',
    'Beleza e bem-estar',
    'Casa e construção',
    'Tecnologia',
    'Moda e acessórios',
    'Saúde',
    'Automotivo',
    'Serviços',
];

const CITIES = [
    'Suzano',
    'Ferraz de Vasconcelos',
    'Mogi das Cruzes',
    'Poá',
    'Itaquaquecetuba',
];

// ==================================================
// CONEXÃO COM O BANCO
// ==================================================
function db(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    foreach (['DB_HOST', 'DB_NAME', 'DB_USER'] as $key) {
        $value = getenv($key);

        if ($value === false || trim($value) === '') {
            throw new RuntimeException(
                'Configuração do banco ausente: ' . $key
            );
        }
    }

    $local = getenv('APP_ENV') === 'local';
    $password = getenv('DB_PASS');

    // Senha vazia é permitida somente no ambiente local.
    if (!$local && ($password === false || $password === '')) {
        throw new RuntimeException(
            'A senha do banco precisa ser configurada em produção.'
        );
    }

    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_NAME');
    $username = getenv('DB_USER');
    if (!$local && strtolower($username) === 'root') {
        throw new RuntimeException('Use uma conta de banco restrita em produção.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $database
    );

    $connection = new PDO(
        $dsn,
        $username,
        $password === false ? '' : $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $connection;
}

// ==================================================
// RESPOSTAS DA API
// ==================================================
function fail(string $message, int $status = 400): never
{
    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        ['error' => $message],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

function out(array $data): never
{
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    exit;
}

// ==================================================
// CABEÇALHOS DE SEGURANÇA
// ==================================================
function secureHeaders(): void
{
    if (getenv('APP_ENV') !== 'local') {
        $origin = parse_url(getenv('APP_ORIGIN') ?: '');
        if (!is_array($origin) || ($origin['scheme'] ?? '') !== 'https'
            || empty($origin['host']) || isset($origin['user']) || isset($origin['pass'])
            || isset($origin['query']) || isset($origin['fragment']) || isset($origin['path'])
            || (PHP_SAPI !== 'cli' && ($_SERVER['HTTPS'] ?? '') !== 'on')) {
            error_log('Perto: configure APP_ORIGIN HTTPS e o transporte seguro do servidor.');
            fail('Serviço indisponível. Tente novamente em instantes.', 503);
        }
    }
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');

    header(
        'Permissions-Policy: camera=(), microphone=(), geolocation=()'
    );

    header(
        "Content-Security-Policy: "
        . "default-src 'self'; "
        . "script-src 'self'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; "
            . "img-src 'self' https: blob:; "
        . "connect-src 'self'; "
        . "frame-src https://www.google.com https://maps.google.com; "
        . "frame-ancestors 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "object-src 'none'"
    );

    if (getenv('APP_ENV') !== 'local') {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

// ==================================================
// SESSÕES
// ==================================================
function startSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $local = getenv('APP_ENV') === 'local';
    $origin = getenv('APP_ORIGIN');

    if (
        !$origin
        || (!$local && !str_starts_with($origin, 'https://'))
    ) {
        throw new RuntimeException(
            'Configure APP_ORIGIN. Em produção, utilize HTTPS.'
        );
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_name(
        $local ? 'perto_session' : '__Host-perto_session'
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !$local,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    if (!session_start()) {
        throw new RuntimeException('Não foi possível iniciar a sessão.');
    }

    $now = time();

    if (isset($_SESSION['user_id'])) {
        $lastSeen = (int) ($_SESSION['last_seen'] ?? 0);
        $created = (int) ($_SESSION['created'] ?? 0);

        $inactive = ($now - $lastSeen) > 1800;
        $expired = ($now - $created) > 28800;

        if ($inactive || $expired) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
    }

    $_SESSION['last_seen'] = $now;
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

// ==================================================
// AUTENTICAÇÃO
// ==================================================
function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $query = db()->prepare(
        'SELECT id, name, email, session_version
         FROM users
         WHERE id = ? AND active = 1'
    );

    $query->execute([$_SESSION['user_id']]);
    $user = $query->fetch();

    if (
        !$user
        || (int) $user['session_version']
            !== (int) ($_SESSION['version'] ?? -1)
    ) {
        unset($_SESSION['user_id'], $_SESSION['version']);

        return null;
    }

    return [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
    ];
}

function requireUser(): array
{
    $user = currentUser();

    if ($user === null) {
        fail('Sua sessão expirou. Entre novamente.', 401);
    }

    return $user;
}

// ==================================================
// REGISTRO DE AÇÕES
// ==================================================
function audit(
    int $userId,
    string $action,
    ?int $businessId = null
): void {
    $query = db()->prepare(
        'INSERT INTO audit_log (user_id, action, business_id)
         VALUES (?, ?, ?)'
    );

    $query->execute([
        $userId,
        $action,
        $businessId,
    ]);
}

// ==================================================
// VALIDAÇÃO DOS CAMPOS
// ==================================================
function textValue(
    array $data,
    string $key,
    int $min,
    int $max
): string {
    $value = $data[$key] ?? null;

    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
        fail('Campo inválido: ' . $key);
    }

    // Preserva espaços que façam parte da senha.
    if (!in_array($key, ['password', 'current'], true)) {
        $value = trim($value);
    }

    $length = mb_strlen($value, 'UTF-8');

    if ($length < $min || $length > $max) {
        fail('Verifique o tamanho do campo: ' . $key);
    }

    return $value;
}

function validHttpsUrl(string $url, array $domains = []): bool
{
    $parts = parse_url($url);
    if (!filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts)
        || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
        || isset($parts['user']) || isset($parts['pass'])
        || (isset($parts['port']) && $parts['port'] !== 443)) return false;
    if (!$domains) return true;
    $host = strtolower($parts['host']);
    foreach ($domains as $domain) {
        if ($host === $domain || str_ends_with($host, '.'.$domain)) return true;
    }
    return false;
}

function businessData(array $data): array
{
    $name = textValue($data, 'name', 2, 150);
    $summary = textValue($data, 'summary', 10, 300);
    $description = textValue($data, 'description', 20, 10000);

    $category = $data['category'] ?? null;
    $city = $data['city'] ?? null;

    if (
        !in_array($category, CATEGORIES, true)
        || !in_array($city, CITIES, true)
    ) {
        fail('Categoria ou cidade inválida.');
    }

    $neighborhood = textValue($data, 'neighborhood', 1, 100);
    $address = textValue($data, 'address', 5, 300);
    $cep = textValue($data, 'cep', 8, 9);
    if (!preg_match('/^[0-9]{5}-?[0-9]{3}$/D', $cep)) {
        fail('Informe um CEP válido com 8 números.');
    }
    $cepDigits = preg_replace('/\D/', '', $cep);
    $cep = substr($cepDigits, 0, 5) . '-' . substr($cepDigits, 5);
    $hours = textValue($data, 'hours', 0, 1000);

    $whatsapp = textValue($data, 'whatsapp', 0, 20);

    if (
        $whatsapp !== ''
        && !preg_match('/^55[0-9]{10,11}$/D', $whatsapp)
    ) {
        fail('Informe um WhatsApp válido com país e DDD.');
    }

    $website = textValue($data, 'website', 0, 500);

    if ($website !== '') {
        $parts = parse_url($website);

        if (
            !filter_var($website, FILTER_VALIDATE_URL)
            || !is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            fail('Informe um site HTTPS válido.');
        }
    }

    $socials = [];
    $socialDomains = ['instagram'=>['instagram.com'], 'facebook'=>['facebook.com','fb.com'],
                      'tiktok'=>['tiktok.com'], 'youtube'=>['youtube.com','youtu.be']];
    foreach (['instagram', 'facebook', 'tiktok', 'youtube'] as $social) {
        $socials[$social] = textValue($data, $social, 0, 500);
        if ($socials[$social] !== '') {
            if (!validHttpsUrl($socials[$social], $socialDomains[$social])) {
                fail('Use um link HTTPS da rede social correspondente.');
            }
            $parts = parse_url($socials[$social]);
            if (
                !filter_var($socials[$social], FILTER_VALIDATE_URL)
                || !is_array($parts)
                || ($parts['scheme'] ?? '') !== 'https'
                || empty($parts['host'])
                || isset($parts['user'])
                || isset($parts['pass'])
            ) {
                fail('Informe links de redes sociais HTTPS válidos.');
            }
        }
    }

    $photos = $data['photos'] ?? '[]';
    if (!is_string($photos)) {
        fail('Fotos inválidas.');
    }
    try {
        $photos = json_decode($photos, true, 4, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        fail('Fotos inválidas.');
    }
    if (!is_array($photos) || ($photos !== [] && array_keys($photos) !== range(0,count($photos)-1)) || count($photos) > 5) {
        fail('Informe no máximo cinco fotos.');
    }
    foreach ($photos as $photo) {
        if (
            !is_string($photo)
            || mb_strlen($photo, 'UTF-8') > 500
            || (!preg_match('/^uploads\/[a-f0-9]{32}\.(?:jpg|png|webp|gif)$/D', $photo)
                && !validHttpsUrl($photo))
        ) {
            fail('As fotos devem ser arquivos enviados ou links HTTPS válidos.');
        }
    }

    // A ordem corresponde aos parâmetros das consultas em api.php.
    return [
        $name,
        $summary,
        $description,
        $category,
        $city,
        $neighborhood,
        $address,
        $cep,
        $hours,
        $whatsapp,
        $website,
        $socials['instagram'],
        $socials['facebook'],
        $socials['tiktok'],
        $socials['youtube'],
        json_encode(array_values($photos), JSON_THROW_ON_ERROR),
    ];
}

function uploadBusinessImages(mixed $files): array
{
    if (!is_array($files) || !isset($files['name'], $files['tmp_name'], $files['error'], $files['size'])) {
        fail('Selecione pelo menos uma imagem.');
    }

    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];
    if (count($names) < 1 || count($names) > 5) {
        fail('Selecione no máximo cinco imagens.');
    }

    $mimeExtensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $directory = dirname(__DIR__) . '/public/uploads';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        fail('Não foi possível preparar o armazenamento das imagens.', 500);
    }

    $validated = [];
    foreach ($names as $index => $name) {
        if (($errors[$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($sizes[$index] ?? 0) > 5 * 1024 * 1024) {
            fail('Cada imagem deve ter até 5 MB.');
        }
        $tmpName = $tmpNames[$index] ?? '';
        if (!is_string($tmpName) || !is_uploaded_file($tmpName)) {
            fail('Arquivo de imagem inválido.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
        $dimensions = @getimagesize($tmpName);
        if (!isset($mimeExtensions[$mime]) || $dimensions === false
            || ($dimensions['mime'] ?? '') !== $mime
            || $dimensions[0] < 1 || $dimensions[1] < 1
            || $dimensions[0] > 8000 || $dimensions[1] > 8000
            || $dimensions[0] * $dimensions[1] > 24000000
            || filesize($tmpName) > 5 * 1024 * 1024) {
            fail('Use somente imagens JPG, PNG, WebP ou GIF.');
        }
        $validated[] = [$tmpName, bin2hex(random_bytes(16)).'.'.$mimeExtensions[$mime]];
    }

    // Serialize quota checks and writes so simultaneous requests cannot bypass
    // the storage ceiling. The lock file is denied by uploads/.htaccess.
    $lock = fopen($directory.'/.upload.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) fail('Envio ocupado. Tente novamente.', 429);
    $paths = [];
    try {
        $total = 0;
        foreach (new DirectoryIterator($directory) as $entry) {
            if ($entry->isFile()) $total += $entry->getSize();
        }
        foreach ($validated as [$tmpName]) $total += filesize($tmpName);
        $quota = (int)(getenv('UPLOAD_STORAGE_LIMIT_MB') ?: 512) * 1024 * 1024;
        if ($quota <= 0 || $total > $quota) fail('Armazenamento de imagens indisponível. Contate o administrador.', 507);
        foreach ($validated as [$tmpName, $filename]) {
            if (!move_uploaded_file($tmpName, $directory.'/'.$filename)) {
                throw new RuntimeException('Falha ao armazenar imagem.');
            }
            $paths[] = 'uploads/'.$filename;
        }
    } catch (Throwable $error) {
        foreach ($paths as $path) unlink(dirname(__DIR__).'/public/'.$path);
        throw $error;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    $_SESSION['uploads'] = array_filter($_SESSION['uploads'] ?? [], fn($created) => $created >= time()-3600);
    foreach ($paths as $path) $_SESSION['uploads'][$path] = time();
    return $paths;
}

// ==================================================
// LIMITE DE ENVIO DE CADASTROS
// ==================================================
function limitAction(string $scope, int $maximum): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = hash('sha256', $scope . ':' . $ip);

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $query = $pdo->prepare(
            'INSERT IGNORE INTO login_limits
                (bucket, attempts, started_at)
             VALUES (?, 0, UTC_TIMESTAMP())'
        );

        $query->execute([$key]);

        $query = $pdo->prepare(
            'SELECT
                attempts,
                started_at < UTC_TIMESTAMP() - INTERVAL 15 MINUTE
                    AS expired
             FROM login_limits
             WHERE bucket = ?
             FOR UPDATE'
        );

        $query->execute([$key]);
        $bucket = $query->fetch();

        if (!$bucket) {
            throw new RuntimeException(
                'Não foi possível verificar o limite de envios.'
            );
        }

        if ((int) $bucket['expired'] === 1) {
            $query = $pdo->prepare(
                'UPDATE login_limits
                 SET attempts = 0, started_at = UTC_TIMESTAMP()
                 WHERE bucket = ?'
            );

            $query->execute([$key]);
        } elseif ((int) $bucket['attempts'] >= $maximum) {
            $pdo->rollBack();

            header('Retry-After: 900');

            fail(
                'Limite de envios atingido. Aguarde 15 minutos.',
                429
            );
        }

        $query = $pdo->prepare(
            'UPDATE login_limits
             SET attempts = attempts + 1
             WHERE bucket = ?'
        );

        $query->execute([$key]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }
}
