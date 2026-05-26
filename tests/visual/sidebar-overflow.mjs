#!/usr/bin/env node
/**
 * Sidebar visual integrity — regression test.
 *
 * Garante que nada da sidebar (#adminmenuback, #adminmenuwrap, item active amarelo)
 * extrapole a largura nominal (180px expanded, 36px collapsed, 0 mobile-overlay).
 *
 * Requisitos: WP rodando + plugin Loomi Studio Setup ativado + tema dark.
 * Configurar via env:
 *   LOOMI_TEST_URL  (default http://localhost:8088)
 *   LOOMI_TEST_USER (default admin)
 *   LOOMI_TEST_PASS (default admin123)
 *
 * Uso: node tests/visual/sidebar-overflow.mjs
 * Exit: 0 sucesso, 1 falha.
 */

import { chromium } from 'playwright';
import { PNG } from 'pngjs';

const BASE_URL = process.env.LOOMI_TEST_URL || 'http://localhost:8088';
const ADMIN_USER = process.env.LOOMI_TEST_USER || 'admin';
const ADMIN_PASS = process.env.LOOMI_TEST_PASS || 'admin123';

const YELLOW = { r: 0xfb, g: 0xd6, b: 0x03 };
const BLACK = { r: 0x0a, g: 0x0a, b: 0x0a };
const PURE_BLACK = { r: 0x00, g: 0x00, b: 0x00 };

// Tolerância de pixels anti-aliasing (design.md "Risks" — alvo é stripe contíguo, não 0 pixels)
const PIXEL_TOLERANCE = 5;
// Subpixel rounding aceitável em getBoundingClientRect
const WIDTH_TOLERANCE = 0.5;
// Soma absoluta R+G+B máxima pra considerar "mesma cor"
const COLOR_DELTA = 30;

function colorNear(p, target, delta = COLOR_DELTA) {
	return (
		Math.abs(p.r - target.r) +
		Math.abs(p.g - target.g) +
		Math.abs(p.b - target.b) <=
		delta
	);
}

async function loginAdmin(page) {
	await page.goto(`${BASE_URL}/wp-login.php`, { waitUntil: 'domcontentloaded' });
	await page.fill('#user_login', ADMIN_USER);
	await page.fill('#user_pass', ADMIN_PASS);
	await Promise.all([
		page.waitForURL(/wp-admin/, { timeout: 15000 }),
		page.click('#wp-submit'),
	]);
}

async function goToPagesList(page) {
	await page.goto(`${BASE_URL}/wp-admin/edit.php?post_type=page`, {
		waitUntil: 'domcontentloaded',
	});
	await page.waitForSelector('#adminmenu', { state: 'visible', timeout: 10000 });
}

async function setFoldedMode(page, folded) {
	await page.evaluate((on) => {
		const cls = document.body.classList;
		if (on) cls.add('folded');
		else cls.remove('folded');
	}, folded);
	// dá tempo do paint refletir o reflow
	await page.waitForTimeout(150);
}

async function checkBoundary(page, viewport, mode) {
	const sidebarWidth = mode === 'folded' ? 36 : 180;
	const label = `viewport=${viewport.width}x${viewport.height}, mode=${mode}`;

	// Assert 1: #adminmenuback.width === #adminmenuwrap.width === sidebarWidth
	const dims = await page.evaluate(() => {
		const back = document.querySelector('#adminmenuback');
		const wrap = document.querySelector('#adminmenuwrap');
		return {
			back: back ? back.getBoundingClientRect().width : null,
			wrap: wrap ? wrap.getBoundingClientRect().width : null,
		};
	});

	if (dims.back === null || dims.wrap === null) {
		throw new Error(`sidebar elements missing (back=${dims.back} wrap=${dims.wrap}) — ${label}`);
	}
	if (Math.abs(dims.back - dims.wrap) > WIDTH_TOLERANCE) {
		throw new Error(
			`width mismatch: #adminmenuback=${dims.back}px #adminmenuwrap=${dims.wrap}px — ${label}`,
		);
	}
	if (Math.abs(dims.back - sidebarWidth) > WIDTH_TOLERANCE) {
		throw new Error(
			`#adminmenuback.width=${dims.back}px expected=${sidebarWidth}px — ${label}`,
		);
	}

	// Screenshot da banda lateral: 4px antes da borda + 40px após.
	// Em coordenadas absolutas: x ∈ [sidebarWidth - 4, sidebarWidth + 40]
	const clip = {
		x: Math.max(0, sidebarWidth - 4),
		y: 50,
		width: 44,
		height: Math.min(500, viewport.height - 80),
	};

	const buf = await page.screenshot({ clip });
	const png = PNG.sync.read(buf);

	// No screenshot, a borda direita da sidebar está em x = 4. Queremos contar
	// pixels com cor da sidebar (amarelo/preto) NA REGIÃO x ∈ [5, png.width].
	// Pulamos 1px (x=4) pra dar margem ao anti-aliasing da própria borda.
	let yellowCount = 0;
	let blackCount = 0;
	const startCol = 5;

	for (let y = 0; y < png.height; y++) {
		for (let x = startCol; x < png.width; x++) {
			const idx = (png.width * y + x) << 2;
			const px = { r: png.data[idx], g: png.data[idx + 1], b: png.data[idx + 2] };
			if (colorNear(px, YELLOW)) yellowCount++;
			if (colorNear(px, BLACK, 18) || colorNear(px, PURE_BLACK, 12)) blackCount++;
		}
	}

	if (yellowCount > PIXEL_TOLERANCE) {
		throw new Error(
			`yellow bleed detected: ${yellowCount}px in x∈[${sidebarWidth + 1}, ${sidebarWidth + 40}] (tolerance=${PIXEL_TOLERANCE}) — ${label}`,
		);
	}
	if (blackCount > PIXEL_TOLERANCE) {
		throw new Error(
			`sidebar background bleed detected: ${blackCount}px in x∈[${sidebarWidth + 1}, ${sidebarWidth + 40}] (tolerance=${PIXEL_TOLERANCE}) — ${label}`,
		);
	}

	console.log(`  ✓ sidebar boundary clean (${label})`);
}

async function runDesktop(browser) {
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const page = await ctx.newPage();
	let failures = [];

	try {
		await loginAdmin(page);
		await goToPagesList(page);

		await setFoldedMode(page, false);
		try {
			await checkBoundary(page, { width: 1440, height: 900 }, 'expanded');
		} catch (err) {
			console.error(`  ✗ ${err.message}`);
			failures.push(err);
		}

		await setFoldedMode(page, true);
		try {
			await checkBoundary(page, { width: 1440, height: 900 }, 'folded');
		} catch (err) {
			console.error(`  ✗ ${err.message}`);
			failures.push(err);
		}
	} finally {
		await ctx.close();
	}

	return failures;
}

async function runMobile(browser) {
	const ctx = await browser.newContext({ viewport: { width: 375, height: 812 } });
	const page = await ctx.newPage();
	let failures = [];

	try {
		await loginAdmin(page);
		await goToPagesList(page);

		// Em mobile (<783px), o WP esconde a sidebar como overlay fechado.
		// Validamos que o #adminmenuback não está visível na área de content.
		const visibility = await page.evaluate(() => {
			const back = document.querySelector('#adminmenuback');
			if (!back) return null;
			const rect = back.getBoundingClientRect();
			const style = window.getComputedStyle(back);
			return {
				left: rect.left,
				right: rect.right,
				width: rect.width,
				display: style.display,
				visibility: style.visibility,
			};
		});

		if (!visibility) {
			throw new Error('#adminmenuback missing on mobile');
		}

		// Cenários aceitos no mobile:
		//  - display:none / visibility:hidden
		//  - posicionado fora da viewport (right <= 0)
		const hidden =
			visibility.display === 'none' ||
			visibility.visibility === 'hidden' ||
			visibility.right <= 0;

		if (!hidden) {
			throw new Error(
				`mobile sidebar leaking into content: left=${visibility.left}, right=${visibility.right}, width=${visibility.width}`,
			);
		}

		console.log(`  ✓ sidebar boundary clean (viewport=375x812, mode=mobile-overlay)`);
	} catch (err) {
		console.error(`  ✗ ${err.message}`);
		failures.push(err);
	} finally {
		await ctx.close();
	}

	return failures;
}

async function main() {
	console.log(`sidebar-overflow visual test — base=${BASE_URL}`);
	const browser = await chromium.launch();
	let total = [];

	try {
		total = total.concat(await runDesktop(browser));
		total = total.concat(await runMobile(browser));
	} finally {
		await browser.close();
	}

	if (total.length > 0) {
		console.error(`\n${total.length} failure(s)`);
		process.exit(1);
	}
	console.log(`\nAll checks passed.`);
	process.exit(0);
}

main().catch((err) => {
	console.error('Fatal:', err.message);
	process.exit(1);
});
