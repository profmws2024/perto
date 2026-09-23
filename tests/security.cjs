// Isolated integration tests: creates and drops only a random perto_security_* DB.
// Run: node tests/security.cjs [path/to/php.exe]
const assert = require('node:assert/strict');
const { spawn, spawnSync } = require('node:child_process');
const { randomBytes } = require('node:crypto');
const path = require('node:path');
const net = require('node:net');
const fs = require('node:fs');
const os = require('node:os');
const root = path.resolve(__dirname, '..');
const php = process.argv[2] || 'C:/xampp1/php/php.exe';
const database = 'perto_security_' + randomBytes(6).toString('hex');
const password = randomBytes(18).toString('hex');
const env = { ...process.env, APP_ENV: 'local', SECURITY_TEST_DB: database, SECURITY_TEST_PASSWORD: password };
const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'perto-security-'));
fs.mkdirSync(path.join(temporary,'app'));
fs.mkdirSync(path.join(temporary,'public'));
fs.copyFileSync(path.join(root,'app/bootstrap.php'),path.join(temporary,'app/bootstrap.php'));
if(fs.existsSync(path.join(root,'app/config.local.php')))fs.copyFileSync(path.join(root,'app/config.local.php'),path.join(temporary,'app/config.local.php'));
for(const name of ['api.php','index.php'])fs.copyFileSync(path.join(root,'public',name),path.join(temporary,'public',name));
function sql(code) {
  const result = spawnSync(php, [], { cwd: root, env, encoding: 'utf8', input: `<?php
    require 'app/bootstrap.php';
    $name=getenv('SECURITY_TEST_DB');
    if (!preg_match('/^perto_security_[a-f0-9]{12}$/D',$name)) throw new Exception('Unsafe test database');
    $pdo=new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'),getenv('DB_USER'),getenv('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    ${code}` });
  if (result.status !== 0) throw Error(result.stderr || result.stdout);
}
let server;
let serverLog='';
let created = false;
let checks = 0;
async function main() {
  sql(`$pdo->exec('CREATE DATABASE '.$name.' CHARACTER SET utf8mb4');`);
  created = true;
  sql(`$pdo->exec('USE '.$name);$pdo->exec(file_get_contents('schema.sql'));
    $q=$pdo->prepare('INSERT INTO users (name,email,password_hash) VALUES (?,?,?)');
    $q->execute(['Security test','security@example.test',password_hash(getenv('SECURITY_TEST_PASSWORD'),PASSWORD_DEFAULT)]);`);
  const port = await new Promise(resolve => { const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p))}); });
  const origin = `http://127.0.0.1:${port}`;
  server=spawn(php,['-d',`session.save_path=${temporary}`,'-d',`upload_tmp_dir=${temporary}`,'-S',`127.0.0.1:${port}`,'-t',path.join(temporary,'public')],{cwd:root,env:{...env,DB_NAME:database,APP_ORIGIN:origin},stdio:['ignore','ignore','pipe']});
  server.stderr.on('data',chunk=>{serverLog=(serverLog+chunk).slice(-6000)});
  server.on('error', error => console.error(error.message));
  for(let attempt=0;;attempt++) { try { await fetch(origin);break; } catch(error) { if(attempt>=50)throw error;await new Promise(r=>setTimeout(r,100)); } }
  async function request(action, session={}, data, options={}) {
    const headers={Cookie:session.cookie||'', ...(data===undefined?{}:{Origin:origin,'X-CSRF-Token':session.csrf||'','Content-Type':'application/json'}),...options.headers};
    const response=await fetch(`${origin}/api.php?action=${action}`,{method:data===undefined?'GET':'POST',headers,body:data===undefined?undefined:JSON.stringify(data),...options,headers});
    const cookie=response.headers.get('set-cookie');
    if(cookie)session.cookie=cookie.split(';')[0];
    const body=await response.json();
    if(body.csrf)session.csrf=body.csrf;
    return {status:response.status,body};
  }
  async function expect(label, action, status) { const response=await action;assert.equal(response.status,status,`${label}: ${JSON.stringify(response.body)}`);checks++;console.log('PASS '+label);return response; }
  const visitor={};await expect('session',request('session',visitor),200);
  await expect('anonymous admin read denied',request('admin-list',visitor),401);
  await expect('anonymous admin write denied',request('save',visitor,{}),401);
  await expect('CSRF required',request('submit',{},{}),403);
  await expect('foreign origin denied',request('submit',visitor,{}, {headers:{Origin:'https://evil.example'}}),403);
  await expect('write method enforced',request('delete',visitor),405);
  await expect('oversized JSON denied',request('submit',visitor,{text:'x'.repeat(150001)}),413);
  await expect('unknown action denied',request('unknown',visitor),404);
  const business={name:'Security fixture',summary:'Test business summary',description:'Test business description long enough',category:'Tecnologia',city:'Suzano',neighborhood:'Centro',address:'Test street 123',cep:'08600000',hours:'',whatsapp:'5511999999999',website:'',instagram:'',facebook:'',tiktok:'',youtube:'',photos:'[]',consent:true};
  await expect('social domain spoof rejected',request('submit',visitor,{...business,instagram:'https://instagram.com.evil.example/user'}),400);
  await expect('unowned upload rejected',request('submit',visitor,{...business,photos:JSON.stringify(['uploads/'+ 'a'.repeat(32)+'.png'])}),400);
  await expect('photo traversal rejected',request('submit',visitor,{...business,photos:'["uploads/../api.php"]'}),400);
  await expect('optional fields accepted',request('submit',visitor,{...business,status:'published',featured:1}),200);
  const list=await expect('public listing',request('list',visitor),200);assert.equal(list.body.businesses.length,0);checks++;
  await expect('submission quota final allowed attempt',request('submit',visitor,business),200);
  await expect('submission quota blocks excess',request('submit',visitor,business),429);
  const admin={};await request('session',admin);
  await expect('admin login',request('login',admin,{email:'security@example.test',password}),200);
  await request('session',admin);
  const pending=await expect('admin sees pending records',request('admin-list',admin),200);
  assert.ok(pending.body.businesses.every(b=>b.status==='pending'&&b.featured===0));checks++;
  const id=pending.body.businesses[0].id;
  await expect('admin can publish',request('save',admin,{...business,id,status:'published',featured:0,image:''}),200);
  const published=await request('list',visitor);assert.equal(published.body.businesses.length,1);checks++;
  await expect('malformed ID rejected',request('delete',admin,{id:'1 OR 1=1'}),400);
  for(let i=0;i<5;i++) await expect('password retry '+i,request('password',admin,{current:'incorrect',password}),403);
  await expect('password guessing throttled',request('password',admin,{current:'incorrect',password}),429);
  async function upload(bytes,type,name) {
    const form=new FormData();form.append('images[]',new Blob([bytes],{type}),name);
    const response=await fetch(`${origin}/api.php?action=upload`,{method:'POST',headers:{Origin:origin,Cookie:visitor.cookie,'X-CSRF-Token':visitor.csrf},body:form});
    return {status:response.status,body:await response.json()};
  }
  await expect('PHP disguised as image rejected',upload('<?php echo 1;','image/png','image.png'),400);
  await expect('SVG rejected',upload('<svg xmlns="http://www.w3.org/2000/svg"></svg>','image/svg+xml','image.svg'),400);
  // Files and sessions are confined to the temporary server directory.
  for(let i=0;i<3;i++) await expect('invalid upload quota '+i,upload('invalid','image/png','image.png'),400);
  await expect('upload abuse throttled',upload('invalid','image/png','image.png'),429);
  sql(`$pdo->exec('USE '.$name);$pdo->exec('DELETE FROM login_limits');`);
  const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=','base64');
  const uploaded=await expect('valid image accepted',upload(png,'image/png','photo.png'),200);
  assert.match(uploaded.body.photos[0],/^uploads\/[a-f0-9]{32}\.png$/);checks++;
  const stranger={};await request('session',stranger);
  await expect('other session cannot claim image',request('submit',stranger,{...business,photos:JSON.stringify(uploaded.body.photos)}),400);
  await expect('owner can attach image',request('submit',visitor,{...business,photos:JSON.stringify(uploaded.body.photos)}),200);
  await expect('upload claim cannot be replayed',request('submit',visitor,{...business,photos:JSON.stringify(uploaded.body.photos)}),400);
  for(let i=0;i<10;i++) await expect('SQL injection login '+i,request('login',visitor,{email:"' OR 1=1 --@example.test",password:'incorrect'}),401);
  await expect('login brute force throttled',request('login',visitor,{email:'nobody@example.test',password:'incorrect'}),429);
  sql(`$pdo->exec('USE '.$name);$pdo->exec('UPDATE users SET session_version=session_version+1');`);
  await expect('revoked session denied',request('admin-list',admin),401);
  console.log(`Security checks passed: ${checks}`);
}
main().catch(error=>{console.error(error);console.error(serverLog);process.exitCode=1}).finally(async()=>{
  if(server && server.exitCode===null) { const stopped=new Promise(resolve=>server.once('exit',resolve));server.kill();await stopped; }
  if(created)sql(`$pdo->exec('DROP DATABASE '.$name);`);
  const resolved=path.resolve(temporary);
  assert.equal(path.dirname(resolved),path.resolve(os.tmpdir()));
  assert.ok(path.basename(resolved).startsWith('perto-security-'));
  fs.rmSync(resolved,{recursive:true});
});
