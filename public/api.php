<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
secureHeaders();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
set_exception_handler(function(Throwable $e): void { error_log('Perto API: '.$e->getMessage()); fail('Serviço indisponível. Tente novamente em instantes.',500); });
startSession();
$action=$_GET['action']??'list';
if (!is_string($action)) fail('Ação inválida.');
$writes=['login','logout','save','delete','password','submit','upload'];
if (in_array($action,$writes,true)) {
    if ($_SERVER['REQUEST_METHOD']!=='POST') fail('Método não permitido.',405);
    $origin=rtrim(getenv('APP_ORIGIN')?:'', '/');
    if (($_SERVER['HTTP_ORIGIN']??'') !== $origin) fail('Origem não permitida.',403);
    if (!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??'')) fail('Atualize a página e tente novamente.',403);
    if ($action==='upload') {
        if (!str_starts_with($_SERVER['CONTENT_TYPE']??'', 'multipart/form-data')) fail('Formato não permitido.',415);
        limitSubmission();
        out(['photos'=>uploadBusinessImages($_FILES['images']??null)]);
    }
    if (!str_starts_with($_SERVER['CONTENT_TYPE']??'', 'application/json')) fail('Formato não permitido.',415);
    $raw=file_get_contents('php://input', false, null, 0, 150001);
    if (strlen($raw)>150000) fail('Conteúdo muito grande.',413);
    try {$d=json_decode($raw,true,32,JSON_THROW_ON_ERROR);} catch(JsonException $e) {fail('Dados inválidos.');}
    if (!is_array($d)) fail('Dados inválidos.');
} elseif ($_SERVER['REQUEST_METHOD']!=='GET') fail('Método não permitido.',405);
if ($action==='session') out(['user'=>currentUser(),'csrf'=>$_SESSION['csrf']]);
if ($action==='list' || $action==='admin-list') {
    if ($action==='admin-list') requireUser();
    $sql='SELECT id,name,summary,description,category,city,neighborhood,address,cep,hours,whatsapp,website,instagram,facebook,tiktok,youtube,photos,image,status,featured,created_at FROM businesses';
    if ($action==='list') $sql.=" WHERE status='published'";
    $sql.=' ORDER BY created_at DESC,id DESC';
    $rows=db()->query($sql)->fetchAll(); foreach($rows as &$row) {$row['id']=(int)$row['id'];$row['featured']=(int)$row['featured'];} unset($row);
    out(['businesses'=>$rows]);
}
if ($action==='login') {
    $email=strtolower(textValue($d,'email',3,254)); $password=textValue($d,'password',1,128);
    // Separate per-IP and per-account buckets prevent bypass by alternating accounts.
    $keys=[hash('sha256','ip:'.($_SERVER['REMOTE_ADDR']??'unknown')),hash('sha256','email:'.$email)]; sort($keys);
    $pdo=db();$pdo->beginTransaction();
    foreach ($keys as $key) {
        $q=$pdo->prepare('INSERT IGNORE INTO login_limits (bucket,attempts,started_at) VALUES (?,0,UTC_TIMESTAMP())');$q->execute([$key]);
        $q=$pdo->prepare('SELECT attempts, started_at < UTC_TIMESTAMP() - INTERVAL 15 MINUTE AS expired FROM login_limits WHERE bucket=? FOR UPDATE');$q->execute([$key]);$b=$q->fetch();
        if ($b['expired']) { $q=$pdo->prepare('UPDATE login_limits SET attempts=0,started_at=UTC_TIMESTAMP() WHERE bucket=?');$q->execute([$key]); }
        elseif ((int)$b['attempts']>=10) { $pdo->rollBack();header('Retry-After: 900');fail('Muitas tentativas. Aguarde 15 minutos.',429); }
        $q=$pdo->prepare('UPDATE login_limits SET attempts=attempts+1 WHERE bucket=?');$q->execute([$key]);
    }
    $pdo->commit();
    $q=$pdo->prepare('SELECT * FROM users WHERE email=? AND active=1');$q->execute([$email]);$u=$q->fetch();
    $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $valid=password_verify($password,$u['password_hash']??$dummy);
    if (!$u || !$valid) fail('E-mail ou senha inválidos.',401);
    if (password_needs_rehash($u['password_hash'],PASSWORD_DEFAULT)) {$q=$pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');$q->execute([password_hash($password,PASSWORD_DEFAULT),$u['id']]);}
    session_regenerate_id(true);
    $_SESSION=['user_id'=>(int)$u['id'],'version'=>(int)$u['session_version'],'csrf'=>bin2hex(random_bytes(32)),'created'=>time(),'last_seen'=>time()];
    audit((int)$u['id'],'login'); out(['ok'=>true]);
}
if ($action==='logout') { $_SESSION=[];session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));out(['ok'=>true]); }
if ($action==='submit') {
    limitSubmission();
    if(($d['consent']??false)!==true) fail('Confirme sua autorização para divulgar o negócio.');
    $values=businessData($d);
    if($values[8]==='') fail('Informe o WhatsApp comercial.');
    // Public submissions never choose their publication status, image, owner or placement.
    $q=db()->prepare("INSERT INTO businesses (name,summary,description,category,city,neighborhood,address,cep,hours,whatsapp,website,instagram,facebook,tiktok,youtube,photos,status,consent_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',UTC_TIMESTAMP())");
    $q->execute($values);out(['ok'=>true]);
}
$u=requireUser();
if ($action==='save') {
    $values=businessData($d);$status=$d['status']??null;$image=$d['image']??'';
    if(!in_array($status,['draft','pending','published'],true)||!in_array($image,['','assets/cafe.jpg','assets/barbearia.jpg','assets/padaria.jpg','assets/tecnologia.jpg'],true)) fail('Status ou imagem inválida.');
    $id=$d['id']??null;if($id!==null&&(!is_int($id)||$id<1)) fail('Identificador inválido.');
    $featured=($d['featured']??0)===1?1:0;
    $pdo=db();$pdo->beginTransaction();
    if($id){$q=$pdo->prepare('SELECT id FROM businesses WHERE id=? FOR UPDATE');$q->execute([$id]);if(!$q->fetch()){$pdo->rollBack();fail('Comércio não encontrado.',404);}}
    if($id){$q=$pdo->prepare('UPDATE businesses SET name=?,summary=?,description=?,category=?,city=?,neighborhood=?,address=?,cep=?,hours=?,whatsapp=?,website=?,instagram=?,facebook=?,tiktok=?,youtube=?,photos=?,status=?,image=?,featured=?,updated_at=UTC_TIMESTAMP() WHERE id=?');$q->execute([...$values,$status,$image,$featured,$id]);}
    else{$q=$pdo->prepare('INSERT INTO businesses (name,summary,description,category,city,neighborhood,address,cep,hours,whatsapp,website,instagram,facebook,tiktok,youtube,photos,status,image,featured,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$q->execute([...$values,$status,$image,$featured,$u['id']]);$id=(int)$pdo->lastInsertId();}
    audit($u['id'],'save:'.$status,$id);$pdo->commit();out(['ok'=>true,'id'=>$id]);
}
if ($action==='delete') {
    $id=$d['id']??null;if(!is_int($id)||$id<1)fail('Identificador inválido.');
    $pdo=db();$pdo->beginTransaction();$q=$pdo->prepare('DELETE FROM businesses WHERE id=?');$q->execute([$id]);
    if(!$q->rowCount()){$pdo->rollBack();fail('Comércio não encontrado.',404);}
    audit($u['id'],'delete',$id);$pdo->commit();out(['ok'=>true]);
}
if ($action==='password') {
    $current=textValue($d,'current',1,128);$password=textValue($d,'password',12,128);
    if (strlen($password)>72) fail('Use uma senha com até 72 bytes.');
    $q=db()->prepare('SELECT password_hash FROM users WHERE id=?');$q->execute([$u['id']]);
    if (!password_verify($current,$q->fetchColumn())) { usleep(500000);fail('Senha atual inválida.',403); }
    $pdo=db();$pdo->beginTransaction();$q=$pdo->prepare('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE id=?');$q->execute([password_hash($password,PASSWORD_DEFAULT),$u['id']]);audit($u['id'],'password-change');$pdo->commit();
    $_SESSION['version']++;session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));out(['ok'=>true]);
}
fail('Recurso não encontrado.',404);
