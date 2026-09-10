import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const editProjectSource = readFileSync(
  new URL('../../backend/app/Filament/Resources/Projects/Pages/EditProject.php', import.meta.url),
  'utf8',
);

function heredoc(name) {
  const match = editProjectSource.match(
    new RegExp(`private const ${name} = <<<'JS'\\r?\\n([\\s\\S]*?)\\r?\\nJS;`),
  );
  assert.ok(match, `${name} must exist in EditProject.php`);
  return match[1];
}

const prepareSource = heredoc('PREPARE_PREVIEW_TAB_JS');
const openSource = heredoc('OPEN_PREVIEW_TAB_JS');
const closeSource = heredoc('CLOSE_PREVIEW_TAB_JS');

class MockElement {
  constructor(tagName, ownerDocument) {
    this.tagName = tagName.toUpperCase();
    this.ownerDocument = ownerDocument;
    this.children = [];
    this.attributes = {};
    this.style = { cssText: '' };
    this.parent = null;
    this.textContent = '';
    this.closed = false;
  }

  append(...children) {
    for (const child of children) {
      child.parent = this;
      this.children.push(child);
    }
  }

  replaceChildren(...children) {
    for (const child of this.children) child.parent = null;
    this.children = [];
    this.append(...children);
  }

  setAttribute(name, value) {
    this.attributes[name] = String(value);
  }

  addEventListener(name, callback) {
    this[`on${name}`] = callback;
  }

  click() {
    this.onclick?.();
  }

  remove() {
    if (!this.parent) return;
    this.parent.children = this.parent.children.filter((child) => child !== this);
    this.parent = null;
  }
}

function createDocument() {
  const document = {
    documentElement: { lang: '' },
    title: '',
    createElement(tagName) {
      return new MockElement(tagName, document);
    },
    getElementById(id) {
      const visit = (element) => {
        if (element.id === id) return element;
        for (const child of element.children) {
          const found = visit(child);
          if (found) return found;
        }
        return null;
      };
      return visit(document.body);
    },
  };
  document.body = new MockElement('body', document);
  return document;
}

function findTag(element, tagName) {
  if (element.tagName === tagName.toUpperCase()) return element;
  for (const child of element.children) {
    const found = findTag(child, tagName);
    if (found) return found;
  }
  return null;
}

function makeHarness() {
  const document = createDocument();
  const hooks = [];
  const timers = new Map();
  const tabs = [];
  let nextTimer = 1;
  let uuidNumber = 1;
  let reloads = 0;

  const window = {
    crypto: {
      randomUUID() {
        return `00000000-0000-4000-8000-${String(uuidNumber++).padStart(12, '0')}`;
      },
    },
    location: {
      reload() {
        reloads++;
      },
    },
    Livewire: {
      hook(name, callback) {
        const hook = { name, callback, active: true };
        hooks.push(hook);
        return () => {
          hook.active = false;
        };
      },
    },
    open() {
      const tabDocument = createDocument();
      const tab = {
        document: tabDocument,
        closed: false,
        opener: window,
        assigned: [],
        focused: 0,
        location: {
          assign(url) {
            tab.assigned.push(url);
          },
        },
        focus() {
          tab.focused++;
        },
        close() {
          tab.closed = true;
        },
      };
      tabs.push(tab);
      return tab;
    },
    setTimeout(callback, delay) {
      const id = nextTimer++;
      timers.set(id, { callback, delay });
      return id;
    },
    clearTimeout(id) {
      timers.delete(id);
    },
  };

  const wireValues = { title_de: 'Ungespeicherter Titel bleibt erhalten' };
  const wire = {
    $id: 'editor-component-1',
    $set(name, value) {
      wireValues[name] = value;
      return Promise.resolve();
    },
  };
  const event = {
    currentTarget: new MockElement('button', document),
    prevented: 0,
    stopped: 0,
    preventDefault() {
      this.prevented++;
    },
    stopImmediatePropagation() {
      this.stopped++;
    },
  };
  const executePrepare = () => {
    Function('$event', '$wire', 'window', 'document', prepareSource)(event, wire, window, document);
  };
  event.currentTarget.onclick = executePrepare;

  const requestPayload = () => JSON.stringify({
    components: [{
      snapshot: JSON.stringify({ memo: { id: wire.$id } }),
      updates: {
        previewRequestId: wireValues.previewRequestId,
        previewRequestAttempt: wireValues.previewRequestAttempt,
      },
      calls: [{ method: 'mountAction', params: ['preview'] }],
    }],
  });

  const failLatestRequest = (status) => {
    const hook = hooks.findLast((candidate) => candidate.active && candidate.name === 'request');
    assert.ok(hook, 'a matching Livewire request hook must be active');
    let failure;
    hook.callback({
      options: { body: requestPayload() },
      fail(callback) {
        failure = callback;
      },
    });
    assert.equal(typeof failure, 'function', 'the preview request must register a failure callback');
    let prevented = 0;
    failure({
      status,
      preventDefault() {
        prevented++;
      },
    });
    return prevented;
  };

  const openPreview = Function('window', 'document', `return (${openSource});`)(window, document);
  const closePreview = Function('window', 'document', `return (${closeSource});`)(window, document);

  return {
    document,
    event,
    executePrepare,
    failLatestRequest,
    openPreview,
    closePreview,
    reloadCount: () => reloads,
    tabs,
    timers,
    window,
    wireValues,
  };
}

test('network failure preserves fields and retries the same operation without accepting a late response', () => {
  const harness = makeHarness();
  harness.executePrepare();

  const firstOperation = { ...harness.window.__madlenProjectPreviewOperation };
  assert.equal(harness.tabs.length, 1);
  assert.equal(harness.window.__madlenProjectPreviewPending, true);
  assert.equal(harness.wireValues.title_de, 'Ungespeicherter Titel bleibt erhalten');
  assert.equal(harness.failLatestRequest(503), 1);
  assert.equal(harness.window.__madlenProjectPreviewPending, false);
  assert.equal(harness.window.__madlenProjectPreviewOperation.retry, true);
  assert.match(
    harness.document.getElementById('madlen-project-preview-recovery').textContent
      + harness.document.getElementById('madlen-project-preview-recovery').children.map((child) => child.textContent).join(' '),
    /Verbindung unterbrochen|Antwort der Vorschau ist verloren gegangen/,
  );

  const retryButton = findTag(harness.document.getElementById('madlen-project-preview-recovery'), 'button');
  assert.ok(retryButton);
  retryButton.click();

  const secondOperation = harness.window.__madlenProjectPreviewOperation;
  assert.equal(secondOperation.requestId, firstOperation.requestId, 'retry must reuse the idempotency key');
  assert.ok(secondOperation.attempt > firstOperation.attempt, 'retry must have a newer response generation');
  assert.equal(harness.tabs.length, 1, 'the existing placeholder tab should be reused');
  assert.equal(harness.wireValues.title_de, 'Ungespeicherter Titel bleibt erhalten');

  harness.openPreview('/admin/preview/old/portfolio/renaissance', firstOperation.requestId, firstOperation.attempt);
  harness.closePreview(firstOperation.requestId, firstOperation.attempt);
  assert.deepEqual(harness.tabs[0].assigned, [], 'a late old response must not navigate the newer attempt');
  assert.equal(harness.tabs[0].closed, false, 'a late old close must not close the newer attempt tab');

  harness.openPreview('/admin/preview/current/portfolio/renaissance', secondOperation.requestId, secondOperation.attempt);
  assert.deepEqual(harness.tabs[0].assigned, ['/admin/preview/current/portfolio/renaissance']);
  assert.equal(harness.window.__madlenProjectPreviewOperation, null);
  assert.equal(harness.window.__madlenProjectPreviewPending, false);
});

test('HTTP 500 uses the recoverable German error instead of Livewire error UI', () => {
  const harness = makeHarness();
  harness.executePrepare();
  assert.equal(harness.failLatestRequest(500), 1);
  const panel = harness.document.getElementById('madlen-project-preview-recovery');
  assert.ok(panel);
  assert.match(panel.children.map((child) => child.textContent).join(' '), /Serverantwort fehlgeschlagen/);
  assert.equal(harness.wireValues.title_de, 'Ungespeicherter Titel bleibt erhalten');
  assert.equal(harness.reloadCount(), 0);
});

test('HTTP 419 keeps the editor and offers re-authentication without automatic reload', () => {
  const harness = makeHarness();
  harness.executePrepare();
  assert.equal(harness.failLatestRequest(419), 1);
  const panel = harness.document.getElementById('madlen-project-preview-recovery');
  assert.ok(panel);
  assert.match(panel.children.map((child) => child.textContent).join(' '), /Sitzung abgelaufen/);
  const loginLink = findTag(panel, 'a');
  assert.equal(loginLink.href, '/admin/login');
  assert.equal(loginLink.target, '_blank');
  assert.equal(harness.wireValues.title_de, 'Ungespeicherter Titel bleibt erhalten');
  assert.equal(harness.reloadCount(), 0);
});
