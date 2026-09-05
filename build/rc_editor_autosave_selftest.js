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
        hasAttribute() { return false; },
        matches() { return tagName === 'TEXTAREA' || this.type === 'hidden'; }
    };
}

function choice(name, checked, value = '1', type = 'checkbox', confirmation = false) {
    return {
        ...field(name, value, 'INPUT'), type, checked,
        hasAttribute(attribute) { return confirmation && attribute === 'data-autosave-confirmation'; }
    };
}

function multiselect() {
    const element = {
        name: 'related_article_ids[]', tagName: 'SELECT', multiple: true,
        hasAttribute() { return false; },
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

function environment(fields, editorBindings, options = {}) {
    const containers = editorBindings.map(([backing, html]) => ({
        nextElementSibling: backing,
        editor: { innerHTML: html },
        querySelector() { return this.editor; },
        parentNode: { querySelector() { throw Error('Never bind to the first textarea in a fieldset'); } }
    }));
    const submitListeners = [];
    const timers = [];
    const storage = new Map(Object.entries(options.storage || {}));
    const banners = [];
    const live = { textContent: '' };
    const form = {
        elements: [...fields, ...(options.associatedFields || [])],
        querySelector(selector) {
            if (selector === 'textarea') return fields.find(item => item.tagName === 'TEXTAREA') || null;
            const name = selector.match(/\[name="([^"]+)"\]/)?.[1];
            const value = selector.match(/\[value="([^"]+)"\]/)?.[1];
            return fields.find(item => item.name === name && (value === undefined || item.value === value)) || null;
        },
        querySelectorAll(selector) {
            if (selector === '.ql-container') return containers;
            if (selector.startsWith('input[type="checkbox"]')) return fields.filter(item => ['checkbox', 'radio'].includes(item.type));
            return fields.filter(item => !['hidden', 'checkbox', 'radio'].includes(item.type));
        },
        parentNode: { insertBefore(banner) { banners.push(banner); } },
        addEventListener(event, callback) {
            assert.equal(event, 'submit');
            submitListeners.push(callback);
        }
    };
    const unrelatedConfirmation = choice('confirm_standalone_action', true);
    const unrelatedForm = { elements: [unrelatedConfirmation], querySelector() { return null; } };
    const context = {
        document: {
            querySelectorAll(selector) {
                assert.equal(selector, 'form[method="post"]', 'only the chosen form may provide editor fields');
                return [unrelatedForm, form];
            },
            getElementById(id) { return id === 'a11y-live' ? live : null; },
            createElement(tag) {
                assert.equal(tag, 'div');
                const buttons = [0, 1].map(() => ({
                    addEventListener(event, callback) { assert.equal(event, 'click'); this.click = callback; }
                }));
                return {
                    attributes: {}, removed: false,
                    setAttribute(name, value) { this.attributes[name] = value; },
                    querySelectorAll(selector) { assert.equal(selector, 'button'); return buttons; },
                    remove() { this.removed = true; },
                    buttons
                };
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
    return { api: context.testApi, containers, timers, storage, submitListeners, banners, live, unrelatedConfirmation };
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

const confirmation = choice('confirm_newsletter_send', true);
const customConfirmation = choice('publish_reviewed', true, 'yes', 'checkbox', true);
const confirmationRadio = choice('confirm_action', true, 'publish', 'radio');
const enabled = choice('submitter_confirmation_enabled', true);
const unchecked = choice('is_featured', false);
const visibility = choice('visibility', true, 'public', 'radio');
const draftKey = 'kora_autosave_/admin/blog_form.php42';
const fresh = environment([field('content', 'Content'), confirmation, customConfirmation, confirmationRadio, enabled, unchecked, visibility], []);
for (const save of [fresh.timers[0], fresh.submitListeners[0]]) {
    save();
    assert.equal(confirmation.checked, true, 'saving must not cancel the active user confirmation');
    assert.equal(customConfirmation.checked, true);
    assert.equal(confirmationRadio.checked, true);
    assert.equal(fresh.live.textContent, '', 'periodic saving must not announce a confirmation reset');
}
for (const key of [draftKey, draftKey + '_submitted']) {
    const draft = JSON.parse(fresh.storage.get(key));
    assert.equal(draft.__chk_confirm_newsletter_send_1, undefined, 'confirmation is not data to persist');
    assert.equal(draft.__chk_publish_reviewed_yes, undefined, 'explicit custom confirmations are not persisted');
    assert.equal(draft.__chk_confirm_action_publish, undefined, 'confirmation radio is not persisted');
    assert.equal(draft.__chk_submitter_confirmation_enabled_1, '1', 'ordinary confirmation-related settings remain data');
    assert.equal(draft.__chk_is_featured_1, '0', 'unchecked ordinary data remains recoverable');
    assert.equal(draft.__chk_visibility_public, '1', 'ordinary radio values remain recoverable');
}

for (const recovery of [false, true]) {
    for (const legacy of [false, true]) {
        const confirm = choice('confirm_newsletter_send', true);
        const custom = choice('publish_reviewed', true, 'yes', 'checkbox', true);
        const radio = choice('confirm_action', true, 'publish', 'radio');
        const associated = choice('confirm_external_action', true);
        const data = choice('is_published', false);
        const disabledData = choice('show_in_nav', true);
        const radioData = choice('visibility', false, 'public', 'radio');
        const input = field('content', 'Current content');
        const olderDraft = {
            content: 'Recovered content', __chk_is_published_1: '1', __chk_show_in_nav_1: '0',
            __chk_visibility_public: '1', _ts: Date.now()
        };
        if (legacy) Object.assign(olderDraft, {
            __chk_confirm_newsletter_send_1: '1', __chk_publish_reviewed_yes: '1',
            __chk_confirm_action_publish: '1', __chk_confirm_external_action_1: '1',
            confirm_newsletter_send: 'stale-value', publish_reviewed: 'stale-value'
        });
        const storedKey = recovery ? draftKey + '_submitted' : draftKey;
        const restored = environment([input, confirm, custom, radio, data, disabledData, radioData], [], {
            storage: { [storedKey]: JSON.stringify(olderDraft) }, associatedFields: [associated]
        });
        assert.equal(restored.banners.length, 1, 'draft offers the actual restore action');
        assert.equal(restored.banners[0].attributes.role, 'status');
        assert.equal(confirm.checked, true, 'showing the banner alone must not change user input');
        restored.banners[0].buttons[0].click();
        assert.equal(input.value, 'Recovered content');
        assert.equal(data.checked, true);
        assert.equal(disabledData.checked, false);
        assert.equal(radioData.checked, true);
        for (const item of [confirm, custom, radio, associated]) {
            assert.equal(item.checked, false, 'restore requires fresh confirmation, including old drafts and associated controls');
        }
        assert.equal(restored.unrelatedConfirmation.checked, true, 'restoring one form leaves standalone actions alone');
        assert.equal(confirm.value, '1', 'legacy direct keys cannot rewrite a confirmation value');
        assert.equal(custom.value, 'yes');
        assert.equal(restored.banners[0].removed, true);
        assert.equal(restored.storage.has(draftKey + '_submitted'), false);
        assert.equal(restored.live.textContent, 'Koncept byl obnoven. Před odesláním znovu potvrďte kontrolu akce.');
        restored.submitListeners[0]();
        const resaved = JSON.parse(restored.storage.get(draftKey + '_submitted'));
        assert.equal(resaved.__chk_confirm_newsletter_send_1, undefined, 'old confirmation cannot re-enter submit recovery');
    }
}

const discardConfirmation = choice('confirm_newsletter_send', true);
const discarded = environment([field('content', 'Current content'), discardConfirmation], [], {
    storage: { [draftKey]: JSON.stringify({ content: 'Old content', _ts: Date.now() }) }
});
discarded.banners[0].buttons[1].click();
assert.equal(discardConfirmation.checked, true, 'discarding a draft does not reset current input');
assert.equal(discarded.live.textContent, '');
assert.equal(discarded.storage.size, 0);
assert.equal(blog.live.textContent, '', 'restoring ordinary forms does not announce nonexistent confirmations');

console.log('PASS rc_editor_autosave_selftest: emitted JS, editor/data preservation, fresh and legacy confirmation exclusion, restore/discard buttons, submit recovery and live status');
