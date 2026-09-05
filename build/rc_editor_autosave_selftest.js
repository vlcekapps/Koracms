'use strict';

const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const path = require('node:path');
const vm = require('node:vm');

const php = process.argv[2] || 'php';
const result = spawnSync(php, [path.join(__dirname, 'rc_editor_publication_selftest.php'), '--autosave-js'], { encoding: 'utf8' });
assert.equal(result.status, 0, result.stderr);
assert.ok(result.stdout.includes('function gather()'), 'test must execute the emitted production script');

function field(name, value, tagName = 'TEXTAREA') {
    return {
        name, value, tagName,
        type: tagName === 'INPUT' ? 'hidden' : undefined,
        matches() { return tagName === 'TEXTAREA' || this.type === 'hidden'; }
    };
}

function multiselect() {
    const element = {
        name: 'related_article_ids[]', tagName: 'SELECT', multiple: true,
        options: ['11', '22', '33'].map(value => ({ value, selected: value !== '33' }))
    };
    Object.defineProperties(element, {
        selectedOptions: { get() { return this.options.filter(option => option.selected); } },
        value: {
            get() { return this.selectedOptions[0]?.value || ''; },
            set(value) { this.options.forEach(option => { option.selected = option.value === value; }); }
        }
    });
    return element;
}

function environment(fields, editorBindings) {
    const containers = editorBindings.map(([backing, html]) => ({
        nextElementSibling: backing,
        editor: { innerHTML: html },
        querySelector() { return this.editor; },
        parentNode: { querySelector() { throw Error('Never bind to the first textarea in a fieldset'); } }
    }));
    const submitListeners = [];
    const timers = [];
    const storage = new Map();
    const form = {
        querySelector(selector) {
            if (selector === 'textarea') return fields.find(item => item.tagName === 'TEXTAREA') || null;
            const name = selector.match(/\[name="([^"]+)"\]/)?.[1];
            return fields.find(item => item.name === name) || null;
        },
        querySelectorAll(selector) {
            if (selector === '.ql-container') return containers;
            if (selector.startsWith('input[type="checkbox"]')) return [];
            return fields.filter(item => item.type !== 'hidden');
        },
        addEventListener(event, callback) {
            assert.equal(event, 'submit');
            submitListeners.push(callback);
        }
    };
    const unrelatedForm = { querySelector() { return null; } };
    const context = {
        document: {
            querySelectorAll(selector) {
                assert.equal(selector, 'form[method="post"]', 'only the chosen form may provide editor fields');
                return [unrelatedForm, form];
            }
        },
        location: { pathname: '/admin/blog_form.php', search: '?id=42' },
        URLSearchParams,
        CSS: { escape(value) { return value; } },
        localStorage: {
            getItem(key) { return storage.get(key) || null; },
            setItem(key, value) { storage.set(key, value); },
            removeItem(key) { storage.delete(key); }
        },
        setInterval(callback) { timers.push(callback); }
    };
    vm.createContext(context);
    const hook = 'try{var raw=localStorage.getItem(key);';
    assert.ok(result.stdout.includes(hook));
    vm.runInContext(result.stdout.replace(hook,
        'globalThis.testApi={gather:gather,restore:restore};' + hook), context);
    assert.ok(context.testApi, 'a preceding preview/conversion form must not disable autosave');
    return { api: context.testApi, containers, timers, storage, submitListeners };
}

const perex = field('perex', 'Short introduction');
const body = field('content', '<p>Old body</p>');
const related = multiselect();
const blog = environment([perex, body, related], [[body, '<p>New long body</p>']]);
blog.timers[0]();
assert.equal(perex.value, 'Short introduction');
assert.equal(body.value, '<p>New long body</p>');
const saved = blog.api.gather();
assert.deepEqual(Array.from(saved['related_article_ids[]']), ['11', '22']);
body.value = 'Changed';
perex.value = 'Changed';
related.value = '33';
blog.api.restore(saved);
assert.equal(body.value, '<p>New long body</p>');
assert.equal(perex.value, 'Short introduction');
assert.deepEqual(related.selectedOptions.map(option => option.value), ['11', '22']);
blog.api.restore({ content: '', 'related_article_ids[]': [] });
assert.equal(blog.containers[0].editor.innerHTML, '', 'empty recovery clears the visual editor');
assert.equal(related.selectedOptions.length, 0, 'empty multi-selection remains empty');
blog.api.restore({ 'related_article_ids[]': '22' });
assert.deepEqual(related.selectedOptions.map(option => option.value), ['22'], 'legacy scalar drafts remain readable');
blog.submitListeners[0]();
assert.ok(blog.storage.has('kora_autosave_/admin/blog_form.php42_submitted'), 'submit recovery remains installed');

const excerpt = field('excerpt', 'Short FAQ excerpt');
const answer = field('answer', 'Old FAQ answer', 'INPUT');
const faq = environment([excerpt, answer], [[answer, '<p>New FAQ answer</p>']]);
const faqDraft = faq.api.gather();
assert.equal(excerpt.value, 'Short FAQ excerpt');
assert.equal(faqDraft.answer, '<p>New FAQ answer</p>', 'hidden WYSIWYG backing field must be captured');
faq.api.restore({ answer: '' });
assert.equal(faq.containers[0].editor.innerHTML, '');

const intro = field('excerpt', 'Event introduction');
const description = field('description', 'Old description');
const program = field('program_note', 'Old program');
const events = environment([intro, description, program], [[description, 'New description'], [program, 'New program']]);
const eventDraft = events.api.gather();
assert.equal(intro.value, 'Event introduction');
assert.equal(eventDraft.description, 'New description');
assert.equal(eventDraft.program_note, 'New program');

console.log('PASS rc_editor_autosave_selftest: actual emitted JS, form selection, WYSIWYG isolation, hidden backing field, multiselect, empty/legacy recovery and submit recovery');
