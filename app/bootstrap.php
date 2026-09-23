<?php
declare(strict_types=1);

// ==================================================
// CONFIGURAÇÃO LOCAL — XAMPP
// Substitua estes valores antes de publicar na internet.
// ==================================================
putenv('APP_ENV=local');
putenv('APP_ORIGIN=http://localhost');

putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=3306');
putenv('DB_NAME=perto');
putenv('DB_USER=root');
putenv('DB_PASS=');

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

    if (!is_string($value)) {
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
    foreach (['instagram', 'facebook', 'tiktok', 'youtube'] as $social) {
        $socials[$social] = textValue($data, $social, 0, 500);
        if ($socials[$social] !== '') {
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
    if (!is_array($photos) || count($photos) > 5) {
        fail('Informe no máximo cinco fotos.');
    }
    foreach ($photos as $photo) {
        if (
            !is_string($photo)
            || mb_strlen($photo, 'UTF-8') > 500
            || (!preg_match('/^uploads\/[a-z0-9-]+\.(?:jpg|png|webp|gif)$/i', $photo)
                && (!filter_var($photo, FILTER_VALIDATE_URL) || !str_starts_with($photo, 'https://')))
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

    $paths = [];
    foreach ($names as $index => $name) {
        if (($errors[$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($sizes[$index] ?? 0) > 5 * 1024 * 1024) {
            fail('Cada imagem deve ter até 5 MB.');
        }
        $tmpName = $tmpNames[$index] ?? '';
        if (!is_string($tmpName) || !is_uploaded_file($tmpName)) {
            fail('Arquivo de imagem inválido.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
        if (!isset($mimeExtensions[$mime]) || @getimagesize($tmpName) === false) {
            fail('Use somente imagens JPG, PNG, WebP ou GIF.');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $mimeExtensions[$mime];
        if (!move_uploaded_file($tmpName, $directory . '/' . $filename)) {
            fail('Não foi possível salvar uma das imagens.', 500);
        }
        $paths[] = 'uploads/' . $filename;
    }
    return $paths;
}

// ==================================================
// LIMITE DE ENVIO DE CADASTROS
// ==================================================
function limitSubmission(): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = hash('sha256', 'business-submit:' . $ip);

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
        } elseif ((int) $bucket['attempts'] >= 5) {
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
