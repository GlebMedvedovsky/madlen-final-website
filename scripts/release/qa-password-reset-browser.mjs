import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { execFileSync } from 'node:child_process';
import assert from 'node:assert/strict';

const { chromium } = await import(pathToFileURL(process.env.MADLEN_QA_PLAYWRIGHT).href);
const origin = 'https://admin.madebymadlen.de';
const out = resolve('installation-artifacts/password-reset-qa');
mkdirSync(out, { recursive: true });
const qa = (mode) => execFileSync('docker', ['exec', 'madlen-password-reset-browser', 'php',
  '/workspace/scripts/release/qa-password-reset.php', mode], { encoding: 'utf8' });
const state = () => JSON.parse(qa('state'));
const mailbox = () => JSON.parse(qa('mail')).url; // Never printed or written to review artifacts.
const browser = await chromium.launch({ headless: true, executablePath: process.env.MADLEN_QA_CHROMIUM });
const contexts = [];
const checks = [];
const record = (message) => { checks.push(message); console.log('QA group ' + checks.length + ' passed.'); };
const createContext = async () => {
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 }, serviceWorkers: 'block' });
  contexts.push(context);
  await context.route('**/*', async route => {
    const requested = new URL(route.request().url());
    if (requested.origin !== origin) return route.abort();
    if (requested.pathname === '/__local_password_reset_probe') {
      return route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><title>Local session probe</title>' });
    }
    // Local HTTP proxy for canonical HTTPS signatures: no request to Netcup/DNS.
    const response = await route.fetch({
      url: 'http://127.0.0.1:8096' + requested.pathname + requested.search,
      headers: { ...route.request().headers(), host: requested.host },
      maxRedirects: 0,
    });
    await route.fulfill({ response });
  });
  return context;
};
const oldPassword = 'Local-QA-only-928!';
const newPassword = 'Changed-QA-only-641!';
const login = async (page, password, remember = false) => {
  await page.goto(origin + '/admin/login');
  console.log('QA login loaded: ' + new URL(page.url()).pathname);
  await page.getByLabel('E-Mail-Adresse').fill('qa@example.test');
  await page.locator('input[type=password]').fill(password);
  if (remember) await page.getByRole('checkbox').check();
  await page.getByRole('button', { name: 'Anmelden', exact: true }).click();
  console.log('QA login submitted.');
};
const message = 'Wenn für diese E-Mail-Adresse ein Administratorkonto besteht, erhalten Sie einen Link zum Zurücksetzen.';
const request = async (page, email) => {
  await page.goto(origin + '/admin/password-reset/request');
  await page.getByLabel('E-Mail-Adresse').fill(email);
  await page.locator('form').getByRole('button', { name: /senden/i }).click();
  await page.getByText(message, { exact: true }).last().waitFor();
  assert.equal(await page.getByLabel('E-Mail-Adresse').inputValue(), '');
};
const fillReset = async (page, password, confirmation = password) => {
  await page.locator('input[autocomplete="new-password"]').nth(0).fill(password);
  await page.locator('input[autocomplete="new-password"]').nth(1).fill(confirmation);
  await page.getByRole('button', { name: 'Passwort zurücksetzen', exact: true }).click();
};
let page;
try {
  qa('reset-fixture');
  const stale = await createContext();
  const editor = await stale.newPage();
  await login(editor, oldPassword, true);
  await editor.waitForURL(origin + '/admin');
  const oldCookies = await stale.cookies();
  const rememberCookie = oldCookies.find(c => c.name.startsWith('remember_web_'));
  assert.ok(rememberCookie);
  await editor.goto(origin + '/admin/projects');
  const staleRequest = await editor.evaluate(() => ({
    snapshot: document.querySelector('[wire\\:id]').getAttribute('wire:snapshot'),
    uri: document.querySelector('[data-update-uri]').getAttribute('data-update-uri'),
    csrf: document.querySelector('[data-csrf]').getAttribute('data-csrf'),
  }));
  assert.ok(staleRequest.snapshot);
  const second = await createContext();
  const dashboard = await second.newPage();
  await login(dashboard, oldPassword);
  await dashboard.waitForURL(origin + '/admin');
  const secondCookies = await second.cookies();
  record('Two independent real browser logins and a remember cookie established.');

  const guest = await createContext();
  page = await guest.newPage();
  await page.goto(origin + '/admin/login');
  await page.getByRole('link', { name: 'Passwort vergessen?', exact: true }).click();
  await page.waitForURL('**/password-reset/request');
  await page.screenshot({ path: out + '/request.png', fullPage: true });
  const before = state();
  await request(page, 'unknown@example.test');
  assert.equal(state().mailCount, before.mailCount);
  await request(page, 'qa@example.test');
  assert.equal(state().mailCount, before.mailCount + 1);
  const firstUrl = mailbox();
  assert.equal(new URL(firstUrl).origin, origin);
  record('Forgot link and identical known/unknown email response; one local array email.');

  const invalid = new URL(firstUrl);
  invalid.searchParams.set('signature', 'invalid');
  assert.equal((await page.goto(invalid.href)).status(), 403);
  qa('expire');
  qa('clear-limits');
  await page.goto(firstUrl);
  await fillReset(page, newPassword);
  await page.getByText('Dieser Link ist ungültig, abgelaufen oder bereits verwendet. Bitte fordern Sie einen neuen Link an.', { exact: true }).waitFor();
  assert.equal(state().resetTokens, 1);
  record('Tampered signature HTTP 403 and expired broker token refused in browser.');

  qa('allow-next-mail');
  qa('failure-on');
  const mails = state().mailCount;
  await request(page, 'qa@example.test');
  assert.equal(state().mailCount, mails);
  qa('failure-off');
  qa('allow-next-mail');
  await request(page, 'qa@example.test');
  assert.equal(state().mailCount, mails + 1);
  const url = mailbox();
  record('Injected delivery failure retains generic response; later retry delivers locally.');

  qa('clear-limits');
  await page.goto(url);
  await fillReset(page, 'short');
  await page.getByText(/mindestens 12 Zeichen/).last().waitFor();
  await fillReset(page, newPassword, 'Mismatch-only-89!');
  await page.getByText('Die Passwörter stimmen nicht überein.', { exact: true }).waitFor();
  qa('clear-limits');
  await page.screenshot({ path: out + '/validation.png', fullPage: true });
  await fillReset(page, newPassword);
  await page.waitForURL(origin + '/admin/login');
  assert.equal(state().resetTokens, 0);
  record('Weak/unconfirmed password rejected; confirmed strong password resets and redirects to login.');

  // First stale request is an actual Livewire action, not an ordinary page reload.
  // Use a stable same-origin static document to send it: the original admin tab
  // may itself navigate to login as soon as a background request detects revocation.
  const probe = await stale.newPage();
  await probe.goto(origin + '/__local_password_reset_probe');
  await stale.addCookies(oldCookies);
  const staleStatus = await probe.evaluate(async ({ uri, csrf, snapshot }) => {
    const response = await fetch(uri, { method: 'POST', headers: {
      'Content-Type': 'application/json', 'X-Livewire': '', 'Accept': 'application/json',
    }, body: JSON.stringify({ _token: csrf, components: [{
      snapshot, updates: {}, calls: [{ method: '$refresh', params: [] }],
    }] }) });
    return response.status;
  }, staleRequest);
  assert.ok([401, 419].includes(staleStatus));
  const secondProbe = await second.newPage();
  await secondProbe.goto(origin + '/__local_password_reset_probe');
  await second.addCookies(secondCookies);
  const staleSecondStatus = await secondProbe.evaluate(async () =>
    (await fetch('/admin/session/csrf-token', { headers: { Accept: 'application/json' } })).status);
  assert.ok([401, 419, 404].includes(staleSecondStatus));
  console.log('QA stale Livewire/recovery HTTP statuses: ' + staleStatus + '/' + staleSecondStatus);
  await dashboard.goto(origin + '/admin');
  await dashboard.waitForURL(origin + '/admin/login');

  const replay = await createContext();
  await replay.addCookies(oldCookies);
  const replayPage = await replay.newPage();
  assert.equal(await replayPage.goto(origin + '/admin').then(r => r.status()), 200); // login redirect
  assert.equal(replayPage.url(), origin + '/admin/login');
  const remembered = await createContext();
  await remembered.addCookies([rememberCookie]);
  const rememberedPage = await remembered.newPage();
  await rememberedPage.goto(origin + '/admin');
  await rememberedPage.waitForURL(origin + '/admin/login');
  record('Old Livewire session, second browser session, replayed cookies and remember-only cookie denied.');

  qa('clear-limits');
  await page.goto(url);
  await fillReset(page, 'Another-QA-only-654!');
  await page.getByText('Dieser Link ist ungültig, abgelaufen oder bereits verwendet. Bitte fordern Sie einen neuen Link an.', { exact: true }).waitFor();
  await login(page, oldPassword);
  await page.getByText(/Diese Kombination aus Zugangsdaten/).waitFor();
  assert.equal(page.url(), origin + '/admin/login');
  await login(page, newPassword);
  await page.waitForURL(origin + '/admin');
  const csrfRejected = await page.evaluate(async () => {
    const uri = document.querySelector('[data-update-uri]').getAttribute('data-update-uri');
    return (await fetch(uri, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ _token: 'invalid-csrf', components: [] }) })).status;
  });
  assert.equal(csrfRejected, 419);
  record('Used link and old password rejected; new login succeeds; invalid CSRF HTTP 419.');
  const after = state();
  assert.equal(after.users, before.users);
  assert.equal(after.previews, before.previews);
  assert.equal(after.publications, before.publications);
  assert.equal(after.resetTokens, 0);
  await page.screenshot({ path: out + '/new-login-dashboard.png', fullPage: true });
  writeFileSync(out + '/browser-results.json', JSON.stringify({
    result: 'PASS', browser: 'Actual Playwright Chromium', checks,
    limitations: 'Canonical HTTPS origin uses local proxy, not real TLS/DNS/Netcup. Array mail and injected transport failure; broker expiry advanced in disposable SQLite.',
  }, null, 2));
  console.log('PASS real Chromium password reset: ' + checks.length + ' scenario groups; no secrets/tokens in artifacts.');
} catch (error) {
  const failedPage = page ?? contexts[0]?.pages()[0];
  if (failedPage) {
    await failedPage.screenshot({ path: out + '/failure.png', fullPage: true });
    console.log('Failure pathname: ' + new URL(failedPage.url()).pathname);
    console.log('Visible buttons: ' + await failedPage.getByRole('button').allTextContents());
  }
  console.error('Password reset browser check failed at group ' + checks.length + ': ' + error.name);
  console.error(error.message.split('\n')[0].replace(/https?:\/\/\S+/g, '[URL omitted]').slice(0, 220));
  throw new Error('Password reset browser failure (sensitive details suppressed); inspect local screenshot.');
} finally {
  for (const context of contexts) await context.unrouteAll({ behavior: 'ignoreErrors' });
  await browser.close();
}
