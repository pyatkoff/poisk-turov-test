'use strict';

const {spawn, execFile} = require('node:child_process');
const {writeFile} = require('node:fs/promises');
const {promisify} = require('node:util');
const {setTimeout: delay} = require('node:timers/promises');

const runFile = promisify(execFile);
const elementKey = 'element-6066-11e4-a52e-4f735466cecf';

// Xvfb supplies the desktop. Send the real shortcut to this session's Firefox
// window using the X11 libraries already used by Firefox. No window manager
// or xdotool is required.
const responsiveShortcut = String.raw`import ctypes as c
import ctypes.util
import sys

x = c.CDLL(ctypes.util.find_library("X11") or "libX11.so.6")
xt = c.CDLL(ctypes.util.find_library("Xtst") or "libXtst.so.6")
pointer, uint, ulong, integer = c.c_void_p, c.c_uint, c.c_ulong, c.c_int
ptr = c.POINTER

def bind(library, name, result, *arguments):
    fn = getattr(library, name)
    fn.restype, fn.argtypes = result, arguments
    return fn

display = bind(x, "XOpenDisplay", pointer, c.c_char_p)(None)
if not display:
    raise RuntimeError("Cannot access the test X display")
root = bind(x, "XDefaultRootWindow", ulong, pointer)(display)
query = bind(x, "XQueryTree", integer, pointer, ulong, ptr(ulong), ptr(ulong),
             ptr(ptr(ulong)), ptr(uint))
intern = bind(x, "XInternAtom", ulong, pointer, c.c_char_p, integer)
prop = bind(x, "XGetWindowProperty", integer, pointer, ulong, ulong, c.c_long,
            c.c_long, integer, ulong, ptr(ulong), ptr(integer), ptr(ulong),
            ptr(ulong), ptr(pointer))
geometry = bind(x, "XGetGeometry", integer, pointer, ulong, ptr(ulong),
                ptr(integer), ptr(integer), ptr(uint), ptr(uint), ptr(uint), ptr(uint))
free = bind(x, "XFree", integer, pointer)
pid_atom = intern(display, b"_NET_WM_PID", 0)
pid = int(sys.argv[1])

def children(wid):
    root_return, parent_return, items, n = ulong(), ulong(), ptr(ulong)(), uint()
    if not query(display, wid, c.byref(root_return), c.byref(parent_return),
                 c.byref(items), c.byref(n)):
        return []
    try:
        return list(items[:n.value]) if n.value else []
    finally:
        if items:
            free(items)

def owner(wid):
    kind, fmt, n, left, data = ulong(), integer(), ulong(), ulong(), pointer()
    status = prop(display, wid, pid_atom, 0, 1, 0, 0, c.byref(kind),
                  c.byref(fmt), c.byref(n), c.byref(left), c.byref(data))
    try:
        if status == 0 and n.value and fmt.value == 32:
            return c.cast(data, ptr(ulong))[0]
    finally:
        if data:
            free(data)

def area(wid):
    r, top, left, width, height, border, depth = (ulong(), integer(), integer(),
                                                  uint(), uint(), uint(), uint())
    geometry(display, wid, *map(c.byref, (r, top, left, width, height, border, depth)))
    return width.value * height.value

pending = children(root)
candidates = []
while pending:
    wid = pending.pop()
    if owner(wid) == pid:
        candidates.append((area(wid), wid))
    else:
        pending.extend(children(wid))
if not candidates:
    raise RuntimeError("Test Firefox window absent from the X display")
wid = max(candidates)[1]
bind(x, "XRaiseWindow", integer, pointer, ulong)(display, wid)
bind(x, "XSetInputFocus", integer, pointer, ulong, integer, ulong)(display, wid, 2, 0)
sync = bind(x, "XSync", integer, pointer, integer)
sync(display, 0)
keysym = bind(x, "XStringToKeysym", ulong, c.c_char_p)
keycode = bind(x, "XKeysymToKeycode", c.c_ubyte, pointer, ulong)
press = bind(xt, "XTestFakeKeyEvent", integer, pointer, uint, integer, ulong)
codes = [keycode(display, keysym(name)) for name in [b"Control_L", b"Shift_L", b"m"]]
if not all(codes):
    raise RuntimeError("Test X display lacks the RDM shortcut keys")
for down, order in [(1, codes), (0, reversed(codes))]:
    for key in order:
        if not press(display, key, down, 25):
            raise RuntimeError("Could not send the RDM shortcut")
sync(display, 0)
bind(x, "XCloseDisplay", integer, pointer)(display)
`;

const viewportMetrics = `({
  width: innerWidth, height: innerHeight,
  screenWidth: screen.width, screenHeight: screen.height,
  touchPoints: navigator.maxTouchPoints,
  coarsePointer: matchMedia('(pointer: coarse)').matches
})`;

async function startFirefox() {
  if (process.platform !== 'linux' || !process.env.DISPLAY) {
    throw new Error('A dedicated Linux X display is required for this Firefox check');
  }
  const service = spawn(process.env.GECKODRIVER || 'geckodriver', [
    '--host', '127.0.0.1', '--port', '0',
  ], {detached: true, stdio: ['ignore', 'pipe', 'pipe']});
  let endpoint = '';
  let log = '';
  let startupError;
  service.once('error', error => { startupError = error; });
  const onOutput = data => {
    log = (log + data.toString()).slice(-8192);
    const match = log.match(/Listening on 127\.0\.0\.1:(\d+)/i);
    if (match) endpoint = 'http://127.0.0.1:' + match[1];
  };
  service.stdout.on('data', onOutput);
  service.stderr.on('data', onOutput);
  let sessionPath = '';

  async function request(method, path, data, timeout = 60000) {
    const response = await fetch(endpoint + path, {
      method, headers: {'content-type': 'application/json'},
      body: data === undefined ? undefined : JSON.stringify(data),
      signal: AbortSignal.timeout(timeout),
    });
    const {value} = await response.json();
    if (!response.ok || value?.error) {
      throw new Error('Firefox ' + method + ' ' + path + ': ' + (value?.message || response.status));
    }
    return value;
  }

  async function quit() {
    try {
      if (sessionPath) await request('DELETE', sessionPath, undefined, 12000);
    } finally {
      sessionPath = '';
      if (service.pid && service.exitCode === null && service.signalCode === null) {
        try { process.kill(-service.pid, 'SIGTERM'); }
        catch (error) { if (error.code !== 'ESRCH') throw error; }
      }
    }
  }

  try {
    const deadline = Date.now() + 10000;
    while (!endpoint && Date.now() < deadline) {
      if (startupError) throw startupError;
      if (service.exitCode !== null) break;
      await delay(40);
    }
    if (!endpoint) throw new Error('Geckodriver did not start: ' + log);
    const firefox = {
      args: ['-width', '1200', '-height', '1050'],
      prefs: {
        'devtools.responsive.viewport.width': 430,
        'devtools.responsive.viewport.height': 932,
        'devtools.responsive.touchSimulation.enabled': true,
        // The acceptance server binds to loopback. Keep all other web traffic
        // (including Firefox background requests) away from public services.
        'network.proxy.type': 1,
        'network.proxy.http': '127.0.0.1',
        'network.proxy.http_port': 9,
        'network.proxy.ssl': '127.0.0.1',
        'network.proxy.ssl_port': 9,
        'network.proxy.no_proxies_on': 'localhost,127.0.0.1,::1',
        'network.captive-portal-service.enabled': false,
        'network.connectivity-service.enabled': false,
        'network.trr.mode': 5,
      },
    };
    if (process.env.FIREFOX_BINARY) firefox.binary = process.env.FIREFOX_BINARY;
    const {sessionId, capabilities} = await request('POST', '/session', {capabilities: {
      alwaysMatch: {browserName: 'firefox', 'moz:firefoxOptions': firefox},
    }});
    sessionPath = '/session/' + encodeURIComponent(sessionId);
    const command = (method, path, data) => request(method, sessionPath + path, data);
    await command('POST', '/timeouts', {implicit: 0, pageLoad: 45000, script: 15000});

    const execute = (script, ...args) => command('POST', '/execute/sync', {script, args});
    const evaluate = expression => execute('return (' + expression + ');');
    const elementPath = async selector => {
      const element = await command('POST', '/element', {using: 'css selector', value: selector});
      return '/element/' + encodeURIComponent(element[elementKey]);
    };
    async function wait(selector, timeout = 12000) {
      const until = Date.now() + timeout;
      do {
        const visible = await execute(
          'const el = document.querySelector(arguments[0]); return !!el && el.getClientRects().length > 0 && getComputedStyle(el).visibility !== "hidden";',
          selector);
        if (visible) return;
        await delay(100);
      } while (Date.now() < until);
      throw new Error('Firefox timed out waiting for ' + selector);
    }
    async function click(selector) {
      await wait(selector);
      await execute('document.querySelector(arguments[0]).scrollIntoView({block:"center",inline:"nearest",behavior:"instant"});', selector);
      await command('POST', await elementPath(selector) + '/click', {});
    }
    async function fill(selector, text) {
      await wait(selector);
      const element = await elementPath(selector);
      await command('POST', element + '/clear', {});
      await command('POST', element + '/value', {text: String(text)});
    }
    async function enableResponsiveMode() {
      const before = await evaluate(viewportMetrics);
      if (before.width === 430 && before.height === 932) {
        throw new Error('Cannot distinguish the original browser from the RDM viewport');
      }
      const pid = capabilities['moz:processID'];
      if (!Number.isSafeInteger(pid) || pid <= 0) throw new Error('Missing Firefox process ID');
      await runFile('python3', ['-c', responsiveShortcut, String(pid)], {timeout: 10000});
      const until = Date.now() + 20000;
      let after;
      while (Date.now() < until) {
        try {
          after = await evaluate(viewportMetrics);
          if (after.width === 430 && after.height === 932 && after.touchPoints > 0) {
            return {mode: 'Firefox Responsive Design Mode (Linux Ctrl+Shift+M)',
              before, after, width: after.width, height: after.height, touch: after.touchPoints, rdmVerified: true};
          }
        } catch (error) { after = error.message; }
        await delay(200);
      }
      throw new Error('Firefox RDM size/touch unverified: ' + JSON.stringify({before, after}));
    }
    return {
      capabilities, go: url => command('POST', '/url', {url}),
      evaluate, enableResponsiveMode, wait, click, fill, quit,
      text: async selector => command('GET', await elementPath(selector) + '/text'),
      value: async selector => command('GET', await elementPath(selector) + '/property/value'),
      count: selector => execute('return document.querySelectorAll(arguments[0]).length;', selector),
      screenshot: async path => {
        const base64 = await command('GET', '/screenshot');
        await writeFile(path, Buffer.from(base64, 'base64'));
      },
    };
  } catch (error) {
    await quit().catch(() => {});
    throw error;
  }
}

module.exports = {startFirefox};
