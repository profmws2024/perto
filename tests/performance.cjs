const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

// Load the actual application functions without starting network requests.
const source = fs.readFileSync(path.join(__dirname, '../public/app.js'), 'utf8');
const functions = source.slice(0, source.indexOf("window.addEventListener('hashchange'"));
function runtime(overrides = {}) {
  const context = vm.createContext({
    document: { body: { dataset: { mode: 'production' } } },
    ...overrides,
  });
  vm.runInContext(functions, context);
  return context;
}

test('photo instructions settle instead of repeatedly mutating the page', () => {
  let changes = 0;
  let value = 'Escolha até três imagens';
  const paragraph = {
    get textContent() { return value; },
    set textContent(text) { changes++; value = text; },
  };
  const context = runtime({ document: {
    body: { dataset: { mode: 'production' } },
    querySelectorAll: () => [paragraph],
  } });
  for (let i = 0; i < 20; i++) vm.runInContext('setupPublicPhotoText()', context);
  assert.equal(value, 'Escolha até cinco imagens');
  assert.equal(changes, 1, 'Repeated initialization must not trigger another DOM mutation');
});

test('leaving a page releases its carousel and image previews once', () => {
  const revoked = [];
  const cleared = [];
  let nextId = 0;
  const context = runtime({
    clearInterval: id => { if (id !== null) cleared.push(id); },
    URL: {
      createObjectURL: () => `blob:preview-${++nextId}`,
      revokeObjectURL: url => revoked.push(url),
    },
  });
  vm.runInContext('carouselTimer=42; previewUrl({}); previewUrl({}); cleanupPage(); cleanupPage()', context);
  assert.deepEqual(cleared, [42]);
  assert.deepEqual(revoked, ['blob:preview-1', 'blob:preview-2']);
});
