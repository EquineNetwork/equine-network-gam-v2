// Screenshot a wp-admin page of the throwaway dev site (tools/dev/setup-wp.sh).
//   node tools/dev/shoot.mjs <out.png> [admin-query]
//   node tools/dev/shoot.mjs /tmp/dashboard.png "admin.php?page=equinenetwork-gam-v2"
// Defaults to the EN Ads dashboard. Requires Playwright (browsers at /opt/pw-browsers in the
// Claude Code web container) and the dev server running: php -S localhost:8765 -t /tmp/wpsite
import { chromium } from '/opt/node22/lib/node_modules/playwright/index.mjs';

const BASE  = process.env.WP_BASE || 'http://localhost:8765';
const OUT   = process.argv[2] || '/tmp/shot.png';
const QUERY = process.argv[3] || 'admin.php?page=equinenetwork-gam-v2';

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
const page = await ctx.newPage();

await page.goto(BASE + '/wp-login.php', { waitUntil: 'networkidle' });
await page.fill('#user_login', 'admin');
await page.fill('#user_pass', 'admin');
await page.click('#wp-submit');
await page.waitForLoadState('networkidle');

await page.goto(`${BASE}/wp-admin/${QUERY}`, { waitUntil: 'networkidle' });
await page.waitForTimeout(500);
await page.screenshot({ path: OUT, fullPage: true });
console.log('shot ->', OUT);

await browser.close();
