// Runs against isolated temporary storage and a local TLS SMTP sink. Sends no external mail.
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const tls = require('node:tls');
const assert = require('node:assert/strict');
const { spawn, execFileSync } = require('node:child_process');
const php = process.argv[2];
const openssl = process.argv[3] || 'openssl';
if (!php) throw Error('Usage: node tests/integration.cjs /path/to/php [/path/to/openssl]');
const root = path.resolve(__dirname, '..');
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'zichtbaar-test-'));
const phpArgs = ['-d', 'extension_dir=' + path.join(path.dirname(php), 'ext')];
const quote = s => "'" + s.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
const password = 'Local-test-only-' + require('node:crypto').randomBytes(12).toString('hex');
const hash = execFileSync(php, [...phpArgs, '-r', 'echo password_hash(' + quote(password) + ',PASSWORD_DEFAULT);'], { encoding: 'utf8' });
const key = path.join(temp, 'key.pem'), cert = path.join(temp, 'cert.pem');
execFileSync(openssl, ['req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-keyout', key, '-out', cert, '-days', '1', '-subj', '/CN=localhost', '-addext', 'subjectAltName=DNS:localhost'], { stdio: 'ignore' });
const configFile = path.join(temp, 'config.php');
function configuration(smtpPort = 0) {
  fs.writeFileSync(configFile, '<?php return [' + Object.entries({
    base_url: 'http://127.0.0.1:8081', admin_email: 'info@zichtbaar-marketing.nl',
    admin_password_hash: hash, app_key: 'test-' + 'x'.repeat(40), data_dir: path.join(temp, 'data'),
    smtp_host: smtpPort ? 'localhost' : '', smtp_port: smtpPort || 465, smtp_security: 'ssl',
    smtp_username: 'test-user', smtp_password: smtpPort ? 'test-only-password' : '', smtp_from: 'info@zichtbaar-marketing.nl',
  }).map(([k, v]) => quote(k) + '=>' + (typeof v === 'number' ? v : quote(v))).join(',') + '];');
}
configuration();
let captured = [];
const smtp = tls.createServer({ key: fs.readFileSync(key), cert: fs.readFileSync(cert) }, socket => {
  socket.write('220 localhost test SMTP\r\n');
  let buffer = '', data = false, message = '', auth = 0;
  socket.on('data', chunk => {
    buffer += chunk;
    while (buffer.includes('\r\n')) {
      const at = buffer.indexOf('\r\n'), line = buffer.slice(0, at); buffer = buffer.slice(at + 2);
      if (data) {
        if (line === '.') { captured.push(message); message = ''; data = false; socket.write('250 queued\r\n'); }
        else message += line + '\r\n';
      } else if (auth) { auth--; socket.write(auth ? '334 UGFzc3dvcmQ6\r\n' : '235 authenticated\r\n'); }
      else if (/^EHLO/.test(line)) socket.write('250-localhost\r\n250 AUTH LOGIN PLAIN\r\n');
      else if (/^AUTH LOGIN/.test(line)) { auth = 2; socket.write('334 VXNlcm5hbWU6\r\n'); }
      else if (/^AUTH PLAIN/.test(line)) socket.write('235 authenticated\r\n');
      else if (line === 'DATA') { data = true; socket.write('354 continue\r\n'); }
      else if (line === 'QUIT') socket.end('221 bye\r\n');
      else socket.write('250 OK\r\n');
    }
  });
});
const server = spawn(php, [...phpArgs, '-d', 'openssl.cafile=' + cert, '-S', '127.0.0.1:8081', '-t', path.join(root, 'public'), path.join(root, 'router.php')], {
  env: { ...process.env, ZM_CONFIG_FILE: configFile }, stdio: ['ignore', 'ignore', 'pipe'], windowsHide: true,
});
let logs = ''; server.stderr.on('data', c => logs += c);
const decode = s => s.replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
const hidden = (html, name) => decode(html.match(new RegExp('name="' + name + '" value="([^"]*)"'))?.[1] || '');
function client() {
  let cookie = '';
  return async (url, body) => {
    const res = await fetch('http://127.0.0.1:8081' + url, { method: body ? 'POST' : 'GET', redirect: 'manual', headers: { ...(cookie ? { cookie } : {}), ...(body && !(body instanceof FormData) ? { 'content-type': 'application/x-www-form-urlencoded' } : {}) }, ...(body ? { body: body instanceof FormData ? body : new URLSearchParams(body) } : {}) });
    const next = res.headers.get('set-cookie'); if (next) cookie = next.split(';')[0];
    return { status: res.status, html: await res.text(), location: res.headers.get('location') };
  };
}
(async () => {
  await new Promise(resolve => smtp.listen(0, '127.0.0.1', resolve));
  for (let i = 0; i < 50; i++) { try { await fetch('http://127.0.0.1:8081/'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  const anon = client(), admin = client();
  for (const p of ['/', '/diensten/seo-topical-authority', '/diensten/sea-ai-advertising', '/diensten/ai-ready-websites', '/diensten/webshops']) {
    const r = await anon(p); assert.equal(r.status, 200); assert.match(r.html, /action="\/contact"/);
  }
  assert.equal((await anon('/admin')).status, 303);
  assert.equal((await anon('/admin', { page: 'home' })).status, 303);
  assert.equal((await anon('/config/config.local.php')).status, 404);
  assert.equal((await anon('/diensten/onbekend')).status, 404);
  let r = await admin('/admin/login'), token = hidden(r.html, 'csrf');
  r = await admin('/admin/login', { csrf: token, email: 'info@zichtbaar-marketing.nl', password: 'wrong' }); assert.match(r.html, /Inloggen is niet gelukt/);
  r = await admin('/admin/login', { csrf: token, email: 'info@zichtbaar-marketing.nl', password }); assert.equal(r.location, '/admin');
  r = await admin('/admin'); token = hidden(r.html, 'csrf');
  const fields = { csrf: token, page: 'home', version: hidden(r.html, 'version') };
  for (const m of r.html.matchAll(/<textarea name="([^"]+)"[^>]*>([\s\S]*?)<\/textarea>/g)) fields[decode(m[1])] = decode(m[2]);
  fields['fields[titleBefore]'] = 'Test <script>alert(1)</script>';
  assert.equal((await admin('/admin', { ...fields, csrf: 'bad' })).status, 403);
  assert.equal((await admin('/admin', fields)).status, 303);
  r = await anon('/'); assert.match(r.html, /Test &lt;script&gt;/); assert.doesNotMatch(r.html, /Test <script>/);
  r = await admin('/admin', fields); assert.match(r.html, /ondertussen gewijzigd/);
  r = await anon('/');
  const submission = { csrf: hidden(r.html, 'csrf'), request_id: hidden(r.html, 'request_id'), source: '/', naam: 'Integratietest', bedrijfsnaam: 'Test', telefoon: '0612345678', email: 'test@example.com', bericht: 'Unieke lokale testaanvraag', website: '' };
  assert.equal((await anon('/contact', { ...submission, csrf: 'bad' })).status, 403);
  assert.equal((await anon('/contact', submission)).status, 303);
  r = await anon('/'); assert.match(r.html, /De e-mail kon nog niet worden verstuurd/);
  assert.equal((await anon('/contact', submission)).status, 303);
  r = await admin('/admin/messages'); assert.equal((r.html.match(/Unieke lokale testaanvraag/g) || []).length, 1);
  configuration(smtp.address().port);
  const messageId = hidden(r.html, 'id');
  r = await admin('/admin/retry', { csrf: token, id: messageId }); assert.equal(r.status, 303);
  r = await admin('/admin/messages'); assert.match(r.html, /Overgedragen aan de mailserver/);
  assert.equal(captured.length, 1); assert.match(captured[0], /To: info@zichtbaar-marketing.nl/); assert.match(captured[0], /Reply-To: Integratietest <test@example.com>/);
  await admin('/admin/retry', { csrf: token, id: messageId }); assert.equal(captured.length, 1);
  r = await anon('/diensten/webshops'); const bad = { ...submission, csrf: hidden(r.html, 'csrf'), request_id: hidden(r.html, 'request_id'), email: 'bad\r\nBcc: victim@example.com' };
  await anon('/contact', bad); r = await anon('/'); assert.match(r.html, /geldig e-mailadres/);
  assert.equal((await anon('/admin/newsletter/export')).status,303);
  const signup={csrf:hidden(r.html,'csrf'),first_name:'José',last_name:'van Dijk',company:'Test & Partners',email:'subscriber@example.com',consent:'yes',website:''};
  await anon('/nieuwsbrief/aanmelden',{...signup,first_name:''});
  assert.match((await anon('/')).html,/Vul je voornaam/); assert.equal(captured.length,1);
  assert.equal((await anon('/nieuwsbrief/aanmelden',{...signup,csrf:'bad'})).status,403);
  await anon('/nieuwsbrief/aanmelden',{...signup,consent:''});
  r=await anon('/'); assert.match(r.html,/geef toestemming/); assert.equal(captured.length,1);
  await anon('/nieuwsbrief/aanmelden',signup); assert.equal(captured.length,2);
  const confirmation=captured[1].match(/http:\/\/127\.0\.0\.1:8081(\/nieuwsbrief\/bevestigen\?token=[a-f0-9]{64})/)[1];
  const unsubscribe=captured[1].match(/http:\/\/127\.0\.0\.1:8081(\/nieuwsbrief\/afmelden\?id=[a-f0-9]{32}&token=[a-f0-9]{64})/)[1];
  r=await admin('/admin/newsletter/export'); assert.doesNotMatch(r.html,/subscriber@example.com/);
  r=await anon(confirmation); assert.equal(r.status,200);
  assert.doesNotMatch((await admin('/admin/newsletter/export')).html,/subscriber@example.com/); // A mail scanner GET cannot subscribe.
  assert.equal((await anon(confirmation,{csrf:'bad'})).status,403);
  r=await anon(confirmation,{csrf:hidden(r.html,'csrf')}); assert.match(r.html,/inschrijving is bevestigd/);
  assert.equal((await anon(confirmation)).status,400); // Single use.
  r=await admin('/admin/newsletter/export'); assert.match(r.html,/subscriber@example.com/); assert.match(r.html,/afmelden/);
  assert.match(r.html,/voornaam,achternaam,bedrijfsnaam,email/); assert.match(r.html,/José/); assert.match(r.html,/van Dijk/); assert.match(r.html,/Test & Partners/);
  r=await admin('/admin/newsletter'); assert.match(r.html,/José/); assert.match(r.html,/Test &amp; Partners/);
  r=await anon(unsubscribe); assert.equal(r.status,200);
  assert.match((await admin('/admin/newsletter/export')).html,/subscriber@example.com/); // GET cannot unsubscribe either.
  r=await anon(unsubscribe,{csrf:hidden(r.html,'csrf')}); assert.match(r.html,/Je bent afgemeld/);
  assert.doesNotMatch((await admin('/admin/newsletter/export')).html,/subscriber@example.com/);
  assert.equal((await anon(unsubscribe.replace(/token=./,'token=Z'))).status,400);
  r=await admin('/admin/test-mail',{csrf:token}); assert.equal(r.status,303); assert.equal(captured.length,3);
  assert.match(captured[2],/To: info@zichtbaar-marketing.nl/);
  configuration();
  r=await anon('/'); await anon('/nieuwsbrief/aanmelden',{...signup,csrf:hidden(r.html,'csrf'),email:'no-mail@example.com'});
  assert.match((await anon('/')).html,/bevestigingsmail kan momenteel niet/); assert.equal(captured.length,3);
  r=await admin('/admin/settings'); assert.match(r.html,/Nog geen wachtwoord opgeslagen/);
  await admin('/admin/test-mail',{csrf:token}); assert.match((await admin('/admin/settings')).html,/Er ontbreekt nog/);
  await require('./blog-http.cjs')({admin,anon,token,hidden,assert,fs,path,root,execFileSync,php,phpArgs,configFile});
  await admin('/admin/logout', { csrf: token }); assert.equal((await admin('/admin')).status, 303);
  const attacker = client(); r = await attacker('/admin/login'); const attackerToken = hidden(r.html, 'csrf');
  for (let i=0;i<11;i++) r = await attacker('/admin/login',{csrf:attackerToken,email:'info@zichtbaar-marketing.nl',password:'wrong'});
  assert.equal(r.status,429);
  // First-run setup requires its one-time token and keeps the password hashed.
  configuration();
  const setupToken=require('node:crypto').randomBytes(32).toString('hex');
  let configText=fs.readFileSync(configFile,'utf8');
  configText=configText.replace(quote(hash),"''").replace('];',','+quote('setup_token_hash')+'=>'+quote(require('node:crypto').createHash('sha256').update(setupToken).digest('hex'))+'];');
  fs.writeFileSync(configFile,configText);
  const setup=client(); assert.equal((await setup('/admin/setup')).status,403);
  assert.equal((await setup('/admin/setup?token='+setupToken)).status,303);
  r=await setup('/admin/setup');
  assert.equal((await setup('/admin/setup',{csrf:hidden(r.html,'csrf'),password,confirmation:password})).status,303);
  assert.ok(!fs.readFileSync(configFile,'utf8').includes(password));
  assert.equal((await setup('/admin/setup?token='+setupToken)).location,'/admin/login');
  console.log('PASS: contact forms, SMTP delivery, admin protections, persistent edits, newsletter consent, double opt-in, single-use tokens, scanner-safe links, unsubscribe, protected confirmed-only CSV export, missing-SMTP errors and admin test mail. No external mail sent.');
})().catch(error => { console.error(error); console.error(logs.slice(-2000)); process.exitCode = 1; }).finally(() => { server.kill(); smtp.close(); });
