// Runs against isolated temporary storage and a local TLS SMTP sink. Sends no external mail.
const fs = require("node:fs");
const path = require("node:path");
const os = require("node:os");
const tls = require("node:tls");
const assert = require("node:assert/strict");
const { spawn, execFileSync } = require("node:child_process");
const php = process.argv[2];
const openssl = process.argv[3] || "openssl";
if (!php) throw Error("Usage: node tests/integration.cjs /path/to/php [/path/to/openssl]");
const root = path.resolve(__dirname, "..");
const temp = fs.mkdtempSync(path.join(os.tmpdir(), "zichtbaar-test-"));
const phpArgs = ["-d", "extension_dir=" + path.join(path.dirname(php), "ext")];
const quote = (s) => "'" + s.replace(/\\/g, "\\\\").replace(/'/g, "\\'") + "'";
const password = "Local-test-only-" + require("node:crypto").randomBytes(12).toString("hex");
const hash = execFileSync(
  php,
  [...phpArgs, "-r", "echo password_hash(" + quote(password) + ",PASSWORD_DEFAULT);"],
  { encoding: "utf8" },
);
const key = path.join(temp, "key.pem"),
  cert = path.join(temp, "cert.pem");
execFileSync(
  openssl,
  [
    "req",
    "-x509",
    "-newkey",
    "rsa:2048",
    "-nodes",
    "-keyout",
    key,
    "-out",
    cert,
    "-days",
    "1",
    "-subj",
    "/CN=localhost",
    "-addext",
    "subjectAltName=DNS:localhost",
  ],
  { stdio: "ignore" },
);
const configFile = path.join(temp, "config.php");
function configuration(smtpPort = 0) {
  fs.writeFileSync(
    configFile,
    "<?php return [" +
      Object.entries({
        base_url: "http://127.0.0.1:8082",
        admin_email: "info@zichtbaar-marketing.nl",
        admin_password_hash: hash,
        app_key: "test-" + "x".repeat(40),
        data_dir: path.join(temp, "data"),
        smtp_host: smtpPort ? "localhost" : "",
        smtp_port: smtpPort || 465,
        smtp_security: "ssl",
        smtp_username: "test-user",
        smtp_password: smtpPort ? "test-only-password" : "",
        smtp_from: "info@zichtbaar-marketing.nl",
      })
        .map(([k, v]) => quote(k) + "=>" + (typeof v === "number" ? v : quote(v)))
        .join(",") +
      "];",
  );
}
configuration();
let captured = [];
const smtp = tls.createServer(
  { key: fs.readFileSync(key), cert: fs.readFileSync(cert) },
  (socket) => {
    socket.write("220 localhost test SMTP\r\n");
    let buffer = "",
      data = false,
      message = "",
      auth = 0;
    socket.on("data", (chunk) => {
      buffer += chunk;
      while (buffer.includes("\r\n")) {
        const at = buffer.indexOf("\r\n"),
          line = buffer.slice(0, at);
        buffer = buffer.slice(at + 2);
        if (data) {
          if (line === ".") {
            captured.push(message);
            message = "";
            data = false;
            socket.write("250 queued\r\n");
          } else message += line + "\r\n";
        } else if (auth) {
          auth--;
          socket.write(auth ? "334 UGFzc3dvcmQ6\r\n" : "235 authenticated\r\n");
        } else if (/^EHLO/.test(line)) socket.write("250-localhost\r\n250 AUTH LOGIN PLAIN\r\n");
        else if (/^AUTH LOGIN/.test(line)) {
          auth = 2;
          socket.write("334 VXNlcm5hbWU6\r\n");
        } else if (/^AUTH PLAIN/.test(line)) socket.write("235 authenticated\r\n");
        else if (line === "DATA") {
          data = true;
          socket.write("354 continue\r\n");
        } else if (line === "QUIT") socket.end("221 bye\r\n");
        else socket.write("250 OK\r\n");
      }
    });
  },
);
const server = spawn(
  php,
  [
    ...phpArgs,
    "-d",
    "openssl.cafile=" + cert,
    "-S",
    "127.0.0.1:8082",
    "-t",
    path.join(root, "public"),
    path.join(root, "router.php"),
  ],
  {
    env: { ...process.env, ZM_CONFIG_FILE: configFile },
    stdio: ["ignore", "ignore", "pipe"],
    windowsHide: true,
  },
);
let logs = "";
server.stderr.on("data", (c) => (logs += c));
const decode = (s) =>
  s
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&amp;/g, "&");
const hidden = (html, name) =>
  decode(html.match(new RegExp('name="' + name + '" value="([^"]*)"'))?.[1] || "");
function client() {
  let cookie = "";
  return async (url, body) => {
    const res = await fetch("http://127.0.0.1:8082" + url, {
      method: body ? "POST" : "GET",
      redirect: "manual",
      headers: {
        ...(cookie ? { cookie } : {}),
        ...(body && !(body instanceof FormData)
          ? { "content-type": "application/x-www-form-urlencoded" }
          : {}),
      },
      ...(body ? { body: body instanceof FormData ? body : new URLSearchParams(body) } : {}),
    });
    const next = res.headers.get("set-cookie");
    if (next) cookie = next.split(";")[0];
    return { status: res.status, html: await res.text(), location: res.headers.get("location") };
  };
}
(async () => {
  await new Promise((resolve) => smtp.listen(0, "127.0.0.1", resolve));
  for (let i = 0; i < 50; i++) {
    try {
      await fetch("http://127.0.0.1:8082/");
      break;
    } catch {
      await new Promise((r) => setTimeout(r, 100));
    }
  }
  const visitor = client(),
    admin = client(),
    other = client();
  assert.equal((await visitor("/admin/meetings")).status, 303);
  let r = await visitor("/afspraak-plannen");
  assert.equal(r.status, 200);
  assert.match(r.html, /nog geen vrije tijdstippen/);
  r = await admin("/admin/login");
  assert.equal(
    (
      await admin("/admin/login", {
        csrf: hidden(r.html, "csrf"),
        email: "info@zichtbaar-marketing.nl",
        password,
      })
    ).status,
    303,
  );
  const day = new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10),
    month = day.slice(0, 7);
  r = await admin("/admin/meetings?month=" + month);
  const token = hidden(r.html, "csrf");
  const slots = { csrf: token, day, from: "09:00", until: "10:00", duration: "30", month };
  assert.equal((await admin("/admin/meetings/slots", { ...slots, csrf: "bad" })).status, 403);
  assert.equal((await admin("/admin/meetings/slots", slots)).status, 303);
  r = await admin("/admin/meetings/slots", slots);
  assert.match(r.html, /overlapt/);
  r = await visitor("/afspraak-plannen");
  const ids = [...r.html.matchAll(/<option value="(\d+)"/g)].map((m) => m[1]);
  assert.equal(ids.length, 2);
  const booking = {
    csrf: hidden(r.html, "csrf"),
    request_id: hidden(r.html, "request_id"),
    slot: ids[0],
    name: "Test <script>",
    company: "Testbedrijf",
    email: "visitor@example.com",
    phone: "0612345678",
    note: "Test gesprek",
    website: "",
  };
  assert.equal((await visitor("/afspraak-plannen", { ...booking, csrf: "bad" })).status, 403);
  assert.equal((await visitor("/afspraak-plannen", booking)).status, 303);
  assert.equal((await visitor("/afspraak-plannen", booking)).status, 303); // Idempotent resubmission.
  r = await visitor("/afspraak-plannen");
  assert.ok(!r.html.includes('value="' + ids[0] + '"'));
  assert.ok(!r.html.includes("visitor@example.com"));
  assert.match(
    (await admin("/admin/meetings/slots", { csrf: token, month, slot_id: ids[0], action: "close" }))
      .html,
    /gereserveerd/,
  );
  r = await other("/afspraak-plannen");
  r = await other("/afspraak-plannen", {
    ...booking,
    csrf: hidden(r.html, "csrf"),
    request_id: hidden(r.html, "request_id"),
  });
  assert.match(r.html, /niet meer beschikbaar/);
  r = await admin("/admin/meetings?month=" + month);
  assert.match(r.html, /Test &lt;script&gt;/);
  assert.match(r.html, /Te bevestigen/);
  const id = hidden(r.html, "id");
  assert.ok(id);
  let change = {
    csrf: token,
    id,
    version: hidden(r.html, "version"),
    month,
    action: "link",
    meet_url: "https://meet.google.com/abc-defg-hij",
  };
  assert.match((await admin("/admin/meetings/change", change)).html, /Bevestig de afspraak/);
  assert.equal(
    (await admin("/admin/meetings/change", { ...change, action: "confirm", meet_url: "" })).status,
    303,
  );
  r = await admin("/admin/meetings?month=" + month);
  assert.match(r.html, /Definitief bevestigd/);
  assert.match(r.html, /Vervallen door wijziging/);
  assert.match(
    (await admin("/admin/meetings/change", { ...change, action: "confirm", meet_url: "" })).html,
    /ondertussen gewijzigd/,
  );
  change.version = hidden(r.html, "version");
  assert.match(
    (await admin("/admin/meetings/change", { ...change, meet_url: "https://evil.example/" })).html,
    /Gebruik een Google Meet-link/,
  );
  configuration(smtp.address().port);
  assert.equal((await admin("/admin/meetings/change", change)).status, 303);
  assert.equal(captured.length, 1);
  assert.match(captured[0], /Google Meet-link/);
  assert.match(captured[0], /afspraak.ics/);
  const decodeCalendar = (message) =>
    Buffer.from(
      message
        .match(/Content-Type: text\/calendar[\s\S]*?\r\n\r\n([\s\S]*?)\r\n--/)[1]
        .replace(/\s/g, ""),
      "base64",
    ).toString();
  const invite = decodeCalendar(captured[0]);
  assert.match(invite, /STATUS:CONFIRMED/);
  assert.match(invite, /LOCATION:https:\/\/meet.google.com\/abc-defg-hij/);
  assert.match(invite, new RegExp("UID:" + id));
  assert.match(invite, /DTSTART:\d{8}T\d{6}Z/);
  assert.ok(invite.split("\r\n").every((line) => Buffer.byteLength(line) <= 75));
  const calendarPart = captured[0].split(/Content-Type: text\/calendar/)[1];
  assert.ok(calendarPart);
  r = await admin("/admin/meetings?month=" + month);
  assert.match(r.html, /Geaccepteerd door mailserver/);
  // Sharing the same form twice cannot send a second invitation.
  assert.match((await admin("/admin/meetings/change", change)).html, /ondertussen gewijzigd/);
  assert.equal(captured.length, 1);
  change.version = hidden(r.html, "version");
  assert.equal(
    (await admin("/admin/meetings/change", { ...change, action: "cancel", meet_url: "" })).status,
    303,
  );
  assert.equal(captured.length, 2);
  assert.match(decodeCalendar(captured[1]), /METHOD:CANCEL/);
  assert.match(decodeCalendar(captured[1]), new RegExp("UID:" + id));
  r = await admin("/admin/meetings?month=" + month);
  assert.match(r.html, /Geannuleerd/);
  r = await visitor("/afspraak-plannen");
  assert.ok(!r.html.includes('value="' + ids[0] + '"'));
  assert.equal((await admin("/admin/meetings/slots", { ...slots, until: "09:30" })).status, 303);
  r = await visitor("/afspraak-plannen");
  assert.ok(r.html.includes('value="' + ids[0] + '"'));
  // A fresh reservation sends both the visitor receipt and the admin notification.
  const fresh = {
    ...booking,
    email: "second@example.com",
    csrf: hidden(r.html, "csrf"),
    request_id: hidden(r.html, "request_id"),
  };
  assert.equal((await visitor("/afspraak-plannen", fresh)).status, 303);
  assert.equal(captured.length, 4);
  assert.ok(captured.slice(2).every((message) => message.includes("voorlopige reservering")));
  assert.ok(
    captured.slice(2).some((message) => message.includes("To: info@zichtbaar-marketing.nl")),
  );
  // Two separate PHP workers race for the same remaining slot against SQLite.
  const reserveScript =
    "$i=json_decode($argv[1],true);require " +
    quote(path.join(root, "app", "bootstrap.php")) +
    ";require " +
    quote(path.join(root, "app", "bookings.php")) +
    ';try{meeting_reserve($i,bin2hex(random_bytes(16)));echo "booked";}catch(InvalidArgumentException $e){echo "unavailable";}';
  const worker = (email) =>
    new Promise((resolve, reject) => {
      const child = spawn(
        php,
        [
          ...phpArgs,
          "-r",
          reserveScript,
          JSON.stringify({ slot: ids[1], name: "Race test", email }),
        ],
        { env: { ...process.env, ZM_CONFIG_FILE: configFile } },
      );
      let out = "",
        err = "";
      child.stdout.on("data", (c) => (out += c));
      child.stderr.on("data", (c) => (err += c));
      child.on("error", reject);
      child.on("exit", (code) => (code === 0 ? resolve(out) : reject(Error(err))));
    });
  assert.deepEqual(
    (await Promise.all([worker("race1@example.com"), worker("race2@example.com")])).sort(),
    ["booked", "unavailable"],
  );
  const unauthorized = client();
  assert.equal((await unauthorized("/admin/meetings/change", change)).status, 303);
  console.log(
    "PASS: availability, overlaps, CSRF/admin protections, idempotency, double booking prevention, privacy, confirmation before Meet sharing, optimistic conflicts, SMTP invitation, cancellation and reopening. No external mail sent.",
  );
})()
  .catch((error) => {
    console.error(error);
    console.error(logs.slice(-4000));
    process.exitCode = 1;
  })
  .finally(() => {
    server.kill();
    smtp.close();
  });
