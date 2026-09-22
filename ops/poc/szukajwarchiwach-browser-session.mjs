#!/usr/bin/env node

import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { chromium } from 'playwright';

const DEFAULT_TARGET_URL = 'https://szukajwarchiwach.gov.pl/skan/-/skan/cf34102284d1121630d7e065baff12133f77944f57015c27db5bb8f666cd9c38';
const DEFAULT_BOOTSTRAP_URL = 'https://www.szukajwarchiwach.gov.pl/';
const DEFAULT_OUTPUT_DIR = 'storage/app/private/szukajwarchiwach-poc';
const DEFAULT_TIMEOUT_MS = 30_000;

function option(name, fallback) {
    const prefix = `--${name}=`;
    const value = process.argv.slice(2).find((argument) => argument.startsWith(prefix));

    return value === undefined ? fallback : value.slice(prefix.length);
}

function hasFlag(name) {
    return process.argv.slice(2).includes(`--${name}`);
}

function usage(exitCode = 0) {
    process.stdout.write(`Szukaj w Archiwach browser-session PoC

Usage:
  node ops/poc/szukajwarchiwach-browser-session.mjs [options]

Options:
  --url=<url>            Target public /skan/-/skan/<token> viewer URL.
  --bootstrap-url=<url>  Portal page opened first to establish browser session state.
  --output=<dir>         Diagnostic/output directory.
  --timeout-ms=<ms>      Navigation/request timeout in milliseconds.
  --help                 Show this help.

The PoC listens to browser network responses so an image loaded by the scan viewer
from photos.szukajwarchiwach.gov.pl can be captured separately from the HTML viewer.
Cookie values are never printed or persisted.
`);
    process.exit(exitCode);
}

if (hasFlag('help')) {
    usage();
}

const targetUrl = option('url', DEFAULT_TARGET_URL);
const bootstrapUrl = option('bootstrap-url', DEFAULT_BOOTSTRAP_URL);
const outputDir = option('output', DEFAULT_OUTPUT_DIR);
const timeoutMs = Number.parseInt(option('timeout-ms', String(DEFAULT_TIMEOUT_MS)), 10);

if (!Number.isFinite(timeoutMs) || timeoutMs < 1_000) {
    throw new Error('--timeout-ms must be an integer of at least 1000.');
}

for (const [label, value] of [['target URL', targetUrl], ['bootstrap URL', bootstrapUrl]]) {
    const parsed = new URL(value);

    if (parsed.protocol !== 'https:') {
        throw new Error(`${label} must use HTTPS.`);
    }

    if (!['szukajwarchiwach.gov.pl', 'www.szukajwarchiwach.gov.pl'].includes(parsed.hostname)) {
        throw new Error(`${label} must use the current szukajwarchiwach.gov.pl host family.`);
    }
}

await mkdir(outputDir, { recursive: true });

const diagnostics = {
    schema: 'mytree.szukajwarchiwach-browser-poc.v2',
    target_url: targetUrl,
    bootstrap_url: bootstrapUrl,
    headless: true,
    attempts: [],
    network_resources: [],
    dom_resource_candidates: [],
    cookie_names: [],
    artifacts: {},
};

function safeResponseDetails(response, body) {
    const headers = response.headers();
    const contentType = headers['content-type'] ?? null;

    return {
        status: response.status(),
        ok: response.ok(),
        url: response.url(),
        content_type: contentType,
        content_length_header: headers['content-length'] ?? null,
        body_bytes: body.length,
        imperva_edge_header_present: Boolean(headers['x-iinfo']),
        imperva_block_page: hasImpervaBlockBody(body),
    };
}

function hasImpervaBlockBody(body) {
    const text = body.subarray(0, Math.min(body.length, 16_384)).toString('latin1').toLowerCase();

    return text.includes('incapsula')
        || text.includes('imperva')
        || text.includes('request unsuccessful')
        || text.includes('incident id')
        || text.includes('<title>403 forbidden</title>')
        || text.includes('>403 forbidden<');
}

function imageExtension(contentType, body) {
    const normalized = (contentType ?? '').split(';', 1)[0].trim().toLowerCase();

    if (normalized === 'image/jpeg' || isJpeg(body)) {
        return '.jpg';
    }

    if (normalized === 'image/png' || isPng(body)) {
        return '.png';
    }

    if (normalized === 'image/tiff' || isTiff(body)) {
        return '.tif';
    }

    if (normalized === 'image/webp' || isWebp(body)) {
        return '.webp';
    }

    if (normalized.startsWith('image/')) {
        return '.img';
    }

    return null;
}

function isJpeg(body) {
    return body.length >= 3 && body[0] === 0xff && body[1] === 0xd8 && body[2] === 0xff;
}

function isPng(body) {
    return body.length >= 8
        && body[0] === 0x89
        && body[1] === 0x50
        && body[2] === 0x4e
        && body[3] === 0x47
        && body[4] === 0x0d
        && body[5] === 0x0a
        && body[6] === 0x1a
        && body[7] === 0x0a;
}

function isTiff(body) {
    return body.length >= 4 && (
        (body[0] === 0x49 && body[1] === 0x49 && body[2] === 0x2a && body[3] === 0x00)
        || (body[0] === 0x4d && body[1] === 0x4d && body[2] === 0x00 && body[3] === 0x2a)
    );
}

function isWebp(body) {
    return body.length >= 12
        && body.subarray(0, 4).toString('ascii') === 'RIFF'
        && body.subarray(8, 12).toString('ascii') === 'WEBP';
}

async function recordCookies(context) {
    const cookies = await context.cookies();
    diagnostics.cookie_names = cookies
        .map((cookie) => ({ name: cookie.name, domain: cookie.domain }))
        .sort((left, right) => `${left.domain}:${left.name}`.localeCompare(`${right.domain}:${right.name}`));

    process.stdout.write(
        `Session cookie names: ${diagnostics.cookie_names.map((cookie) => `${cookie.domain}:${cookie.name}`).join(', ') || '(none)'}\n`,
    );
}

async function saveNonImageBody(prefix, body) {
    const filename = path.join(outputDir, `${prefix}.html`);
    await writeFile(filename, body);
    diagnostics.artifacts[`${prefix}_body`] = filename;
    process.stdout.write(`HTML/non-image response saved: ${body.length} bytes -> ${filename}\n`);
}

async function apiFetch(context, label, url) {
    const response = await context.request.get(url, {
        failOnStatusCode: false,
        timeout: timeoutMs,
    });
    const body = Buffer.from(await response.body());
    const details = { label, transport: 'browser_context_request', ...safeResponseDetails(response, body) };
    diagnostics.attempts.push(details);

    process.stdout.write(
        `${label}: status=${details.status} content-type=${details.content_type ?? '(none)'} bytes=${details.body_bytes} imperva-block=${details.imperva_block_page ? 'yes' : 'no'} x-iinfo=${details.imperva_edge_header_present ? 'yes' : 'no'}\n`,
    );

    return { response, body, details };
}

function responseLooksInteresting(response) {
    let hostname = '';

    try {
        hostname = new URL(response.url()).hostname;
    } catch {
        return false;
    }

    const contentType = (response.headers()['content-type'] ?? '').toLowerCase();

    return hostname === 'photos.szukajwarchiwach.gov.pl' || contentType.startsWith('image/');
}

const browser = await chromium.launch({ headless: true });
let exitCode = 3;

try {
    const context = await browser.newContext();
    const page = await context.newPage();

    process.stdout.write(`Bootstrap navigation: ${bootstrapUrl}\n`);

    try {
        const bootstrapResponse = await page.goto(bootstrapUrl, {
            waitUntil: 'domcontentloaded',
            timeout: timeoutMs,
        });

        await page.waitForTimeout(2_500);

        diagnostics.bootstrap = {
            final_url: page.url(),
            title: await page.title().catch(() => null),
            status: bootstrapResponse?.status() ?? null,
            content_type: bootstrapResponse?.headers()['content-type'] ?? null,
        };
        process.stdout.write(
            `Bootstrap result: status=${diagnostics.bootstrap.status ?? '(none)'} final-url=${diagnostics.bootstrap.final_url}\n`,
        );
    } catch (error) {
        diagnostics.bootstrap = {
            error: error instanceof Error ? error.message : String(error),
            final_url: page.url(),
        };
        process.stdout.write(`Bootstrap navigation failed: ${diagnostics.bootstrap.error}\n`);
    }

    const bootstrapScreenshot = path.join(outputDir, '01-bootstrap.png');
    await page.screenshot({ path: bootstrapScreenshot, fullPage: true }).catch(() => null);
    diagnostics.artifacts.bootstrap_screenshot = bootstrapScreenshot;
    await recordCookies(context);

    process.stdout.write(`Session HTTP fetch of supplied URL: ${targetUrl}\n`);
    const first = await apiFetch(context, 'session-http-supplied-url', targetUrl);
    const canonicalViewerUrl = first.response.url();
    diagnostics.canonical_viewer_url = canonicalViewerUrl;

    if (canonicalViewerUrl !== targetUrl) {
        process.stdout.write(`Canonical viewer URL resolved by session HTTP: ${canonicalViewerUrl}\n`);
    }
    if (imageExtension(first.details.content_type, first.body) !== null) {
        const extension = imageExtension(first.details.content_type, first.body);
        const filename = path.join(outputDir, `02-session-http-image${extension}`);
        await writeFile(filename, first.body);
        diagnostics.artifacts.downloaded_image = filename;
        exitCode = 0;
    } else {
        await saveNonImageBody('02-session-http-supplied-url', first.body);
    }

    if (exitCode !== 0) {
        process.stdout.write('Supplied /skan URL was not an image; opening it as a browser viewer and observing image/network subrequests.\n');

        const capturePromises = [];
        let largestNetworkImage = null;

        const captureResponse = async (response) => {
            if (!responseLooksInteresting(response)) {
                return;
            }

            const headers = response.headers();
            const contentType = headers['content-type'] ?? null;
            let body;

            try {
                body = Buffer.from(await response.body());
            } catch (error) {
                diagnostics.network_resources.push({
                    url: response.url(),
                    status: response.status(),
                    content_type: contentType,
                    resource_type: response.request().resourceType(),
                    body_error: error instanceof Error ? error.message : String(error),
                });

                return;
            }

            let hostname = null;
            try {
                hostname = new URL(response.url()).hostname;
            } catch {
                // Keep null in diagnostics.
            }

            const extension = imageExtension(contentType, body);
            const resource = {
                url: response.url(),
                hostname,
                status: response.status(),
                content_type: contentType,
                resource_type: response.request().resourceType(),
                body_bytes: body.length,
                image_detected: extension !== null,
                imperva_edge_header_present: Boolean(headers['x-iinfo']),
                imperva_block_page: hasImpervaBlockBody(body),
            };

            diagnostics.network_resources.push(resource);

            if (extension !== null && (largestNetworkImage === null || body.length > largestNetworkImage.body.length)) {
                largestNetworkImage = {
                    body,
                    extension,
                    url: response.url(),
                    contentType,
                };
            }
        };

        page.on('response', (response) => {
            const promise = captureResponse(response);
            capturePromises.push(promise);
        });

        let navigationResponse = null;
        let download = null;
        const downloadPromise = page.waitForEvent('download', { timeout: timeoutMs }).catch(() => null);

        try {
            navigationResponse = await page.goto(canonicalViewerUrl, {
                waitUntil: 'domcontentloaded',
                timeout: timeoutMs,
            });

            await page.waitForLoadState('networkidle', { timeout: 10_000 }).catch(() => null);
        } catch (error) {
            diagnostics.browser_navigation_error = error instanceof Error ? error.message : String(error);
            process.stdout.write(`Browser navigation reported: ${diagnostics.browser_navigation_error}\n`);
        }

        await page.waitForTimeout(4_000);

        download = await Promise.race([
            downloadPromise,
            page.waitForTimeout(500).then(() => null),
        ]);

        if (download !== null) {
            const suggested = download.suggestedFilename() || 'browser-download.bin';
            const filename = path.join(outputDir, `03-${path.basename(suggested)}`);
            await download.saveAs(filename);
            const downloadedBody = await readFile(filename);
            diagnostics.artifacts.browser_download = filename;
            diagnostics.browser_download_bytes = downloadedBody.length;
            diagnostics.browser_download_image_detected = imageExtension(null, downloadedBody) !== null;

            process.stdout.write(
                `Browser download event: ${downloadedBody.length} bytes -> ${filename}; image=${diagnostics.browser_download_image_detected ? 'yes' : 'no'}\n`,
            );

            if (diagnostics.browser_download_image_detected) {
                exitCode = 0;
            }
        }

        await Promise.allSettled(capturePromises);

        diagnostics.browser_navigation = {
            final_url: page.url(),
            title: await page.title().catch(() => null),
            status: navigationResponse?.status() ?? null,
            content_type: navigationResponse?.headers()['content-type'] ?? null,
        };

        process.stdout.write(
            `Browser viewer result: status=${diagnostics.browser_navigation.status ?? '(none)'} title=${diagnostics.browser_navigation.title ?? '(none)'} final-url=${diagnostics.browser_navigation.final_url}\n`,
        );

        if (navigationResponse !== null) {
            const body = Buffer.from(await navigationResponse.body().catch(() => Buffer.alloc(0)));
            const details = {
                label: 'browser-viewer-navigation',
                transport: 'page_navigation',
                ...safeResponseDetails(navigationResponse, body),
            };
            diagnostics.attempts.push(details);
            await saveNonImageBody('03-browser-viewer-response', body);
        }

        diagnostics.dom_resource_candidates = await page.evaluate(() => {
            const candidates = new Set();

            for (const image of document.querySelectorAll('img')) {
                if (image.currentSrc) {
                    candidates.add(image.currentSrc);
                } else if (image.src) {
                    candidates.add(image.src);
                }
            }

            for (const entry of performance.getEntriesByType('resource')) {
                if (entry.name.includes('szukajwarchiwach.gov.pl')) {
                    candidates.add(entry.name);
                }
            }

            return [...candidates].sort();
        }).catch(() => []);

        const browserScreenshot = path.join(outputDir, '04-browser-viewer.png');
        await page.screenshot({ path: browserScreenshot, fullPage: true }).catch(() => null);
        diagnostics.artifacts.browser_navigation_screenshot = browserScreenshot;
        await recordCookies(context);

        const imageResources = diagnostics.network_resources.filter((resource) => resource.image_detected);
        const photosResources = diagnostics.network_resources.filter(
            (resource) => resource.hostname === 'photos.szukajwarchiwach.gov.pl',
        );

        process.stdout.write(
            `Observed browser resources: ${diagnostics.network_resources.length} interesting, ${photosResources.length} from photos.szukajwarchiwach.gov.pl, ${imageResources.length} recognized images.\n`,
        );

        if (largestNetworkImage !== null) {
            const filename = path.join(outputDir, `05-largest-network-image${largestNetworkImage.extension}`);
            await writeFile(filename, largestNetworkImage.body);
            diagnostics.artifacts.largest_network_image = filename;
            diagnostics.largest_network_image = {
                url: largestNetworkImage.url,
                content_type: largestNetworkImage.contentType,
                body_bytes: largestNetworkImage.body.length,
            };
            process.stdout.write(
                `Largest browser-loaded image: ${largestNetworkImage.body.length} bytes -> ${filename}\n`,
            );
            exitCode = 0;
        }

        const finalBrowserUrl = page.url();
        if (finalBrowserUrl !== canonicalViewerUrl) {
            process.stdout.write(`Session HTTP fetch of final browser URL: ${finalBrowserUrl}\n`);
            const canonical = await apiFetch(context, 'session-http-final-browser-url', finalBrowserUrl);

            if (imageExtension(canonical.details.content_type, canonical.body) === null) {
                await saveNonImageBody('06-session-http-final-browser-url', canonical.body);
            }
        }
    }

    diagnostics.result = exitCode === 0 ? 'browser_image_observed' : 'no_image_observed';
} finally {
    await browser.close();
    const diagnosticsPath = path.join(outputDir, 'diagnostics.json');
    await writeFile(diagnosticsPath, JSON.stringify(diagnostics, null, 2) + '\n');
    process.stdout.write(`Diagnostics: ${diagnosticsPath}\n`);
}

process.exit(exitCode);
