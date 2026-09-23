const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../public/app.js'),'utf8');
const definitions=source.slice(0,source.indexOf("window.addEventListener('popstate'"));
for(const base of ['/', '/perto/public/']) {
 test('clean URLs, filters and navigation under '+base,()=>{
  const location={pathname:base+'admin/editar/12',search:''};
  const visited=[];
  const context=vm.createContext({document:{body:{dataset:{mode:'production',basePath:base}}},location,URLSearchParams,
   history:{pushState:(_state,_title,url)=>visited.push(url)},window:{scrollTo:()=>{}}});
  vm.runInContext(definitions,context);
  assert.equal(vm.runInContext('route()',context),'/admin/editar/12');
  location.pathname=base+'explorar';location.search='?city=Suzano';
  assert.equal(vm.runInContext('route()',context),'/explorar?city=Suzano');
  vm.runInContext("render=()=>{};navigate('/admin/comercios')",context);
  assert.deepEqual(visited,[base+'admin/comercios']);
  const link=vm.runInContext("filterUrl(new URLSearchParams('city=Suzano'),'sort','name')",context);
  assert.equal(new URL(link,'https://example.test'+base).pathname,base+'explorar');
  assert.ok(!vm.runInContext('header()+footer()',context).includes('href="#'));
 });
}
