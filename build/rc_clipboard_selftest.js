'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../themes/default/layouts/base.php'), 'utf8');
const script = source.slice(source.indexOf('function copyTextFallback'), source.indexOf('</script>', source.indexOf('function copyTextFallback')));
let focusRestored = 0;
let nodes = 0;
let fallbackResult = false;
const live = {textContent: ''};
const navigator = {};
const context = vm.createContext({navigator, Promise, setTimeout: () => {}, document: {
  activeElement: {focus() { focusRestored++; }},
  createElement() { return {setAttribute() {}, select() {}}; },
  body: {appendChild() { nodes++; }, removeChild() { nodes--; }},
  execCommand() { if (fallbackResult instanceof Error) throw fallbackResult; return fallbackResult; },
  getElementById() { return live; }, addEventListener() {}
}});
vm.runInContext(script, context);
const button = () => ({textContent: 'Copy', innerHTML: 'Copy', hasAttribute() { return false; }, setAttribute() {}, getAttribute() { return 'Copy'; }});
(async () => {
  let btn = button();
  await context.copyWithFeedback(btn, 'text', 'Copy', 'Success');
  assert.equal(btn.textContent, 'Copy');
  assert.match(live.textContent, /nepodařilo/);
  fallbackResult = new Error('clipboard disabled');
  await context.copyWithFeedback(btn, 'text', 'Copy', 'Success');
  assert.match(live.textContent, /nepodařilo/);
  navigator.clipboard = {writeText: () => Promise.reject(new Error('denied'))};
  await context.copyWithFeedback(btn, 'text', 'Copy', 'Success');
  assert.equal(btn.textContent, 'Copy');
  fallbackResult = true;
  await context.copyWithFeedback(btn, 'text', 'Copy', 'Success');
  assert.equal(live.textContent, 'Success');
  assert.equal(btn.textContent, 'Zkopírováno!');
  navigator.clipboard.writeText = () => Promise.resolve();
  btn = button();
  await context.copyWithFeedback(btn, 'text', 'Copy', 'Success');
  assert.equal(btn.textContent, 'Zkopírováno!');
  assert.equal(nodes, 0);
  assert.equal(focusRestored, 4);
  console.log('RC clipboard: success, denial, fallback failure, exception and focus verified.');
})().catch(error => { console.error(error); process.exitCode = 1; });
