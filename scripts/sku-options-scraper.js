const fs = require('node:fs');
const nodePath = require('node:path');

function detectPluginFromSignals(signals) {
  if ((signals.teeinblueCount || 0) > 0) {
    return 'teeinblue';
  }

  if ((signals.customilyCount || 0) > 0) {
    return 'customily';
  }

  if ((signals.ymqCount || 0) > 0) {
    return 'ymq';
  }

  return 'unknown';
}

function getPluginSignalsFromDocument(documentRef) {
  const teeinblueCount = safeQueryCount(documentRef, '.tee-form-action')
    + safeQueryCount(documentRef, '.tee-form-wrapper');
  const ymqCount = safeQueryCount(documentRef, '.ymq-options-box[ymq-option-label]')
    + safeQueryCount(documentRef, '.ymq-option-condition-hide')
    + safeQueryCount(documentRef, 'ymq-option');
  const customilyCount = safeQueryCount(documentRef, '.customily-preview-button');

  return {
    teeinblueCount,
    ymqCount,
    customilyCount,
  };
}

function extractYmqOptionsFromDocument(documentRef) {
  const hiddenElements = Array.from(documentRef.querySelectorAll('.ymq-option-condition-hide'));

  hiddenElements.forEach((element) => {
    if (element.style) {
      if (typeof element.style.removeProperty === 'function') {
        element.style.removeProperty('display');
      }
      if (typeof element.style.setProperty === 'function') {
        element.style.setProperty('display', 'block', 'important');
      } else {
        element.style.display = 'block';
      }
    }

    if (typeof element.removeAttribute === 'function') {
      element.removeAttribute('hidden');
      element.removeAttribute('aria-hidden');
    }

    if (typeof element.setAttribute === 'function') {
      element.setAttribute('style', removeDisplayNoneImportant(String(element.getAttribute('style') || '')));
    }
  });

  const boxes = Array.from(documentRef.querySelectorAll('.ymq-options-box[ymq-option-label]'));
  const options = [];

  boxes.forEach((box) => {
    const name = trimText(box.getAttribute('ymq-option-label'));
    const lowered = name.toLowerCase();

    if (!name || lowered.includes('color') || lowered.includes('vip') || lowered.includes('number')) {
      return;
    }

    const values = [];
    const images = Array.from(box.querySelectorAll('.ymq-option-swatches-image-container .ymq-option-swatches-image'));

    images.forEach((image) => {
      const valueItem = typeof image.closest === 'function' ? image.closest('.ymq-option-value-item') : null;
      const input = valueItem && typeof valueItem.querySelector === 'function'
        ? valueItem.querySelector('.ymq-option-swatches-input')
        : null;
      const imageValue = trimText(
        getAttribute(input, 'value')
        || getAttribute(input, 'aria-label')
        || getAttribute(image, 'aria-label')
        || getAttribute(image, 'title')
        || (valueItem ? valueItem.textContent : '')
      );
      const sourceImageUrl = trimText(
        getAttribute(image, 'src')
        || getAttribute(image, 'data-src')
        || getAttribute(image, 'data-original')
        || image.currentSrc
        || extractBackgroundImageUrl(getAttribute(image, 'style'))
      );

      if (!sourceImageUrl) {
        return;
      }

      if (shouldSkipYmqImageValue(name, imageValue)) {
        return;
      }

      values.push({
        image_value: imageValue,
        source_image_url: absolutizeUrl(sourceImageUrl),
        extension: extensionFromUrl(sourceImageUrl),
      });
    });

    if (values.length > 0) {
      options.push({
        name,
        values: uniqueValues(values),
      });
    }
  });

  return options;
}

function extractSkuFromDocument(documentRef) {
  const selectors = [
    '[itemprop="sku"]',
    '[data-product-sku]',
    '[data-sku]',
    '.sku',
    '.product-sku',
    '.variant-sku',
  ];

  for (const selector of selectors) {
    const element = documentRef.querySelector(selector);

    if (!element) {
      continue;
    }

    const sku = trimText(
      getAttribute(element, 'content')
      || getAttribute(element, 'data-product-sku')
      || getAttribute(element, 'data-sku')
      || element.textContent
    ).replace(/^sku\s*[:#-]?\s*/i, '');

    if (sku) {
      return sku;
    }
  }

  const scripts = Array.from(documentRef.querySelectorAll('script[type="application/ld+json"], script[type="application/json"], script:not([src])'));

  for (const script of scripts) {
    const text = trimText(script.textContent);

    if (!text || (!text.includes('sku') && !text.includes('variants'))) {
      continue;
    }

    const sku = extractSkuFromJsonText(text);

    if (sku) {
      return sku;
    }
  }

  return '';
}

function extractSkuFromJsonText(text) {
  try {
    const data = JSON.parse(text);
    return findSkuInObject(data);
  } catch (error) {
    const match = text.match(/"sku"\s*:\s*"([^"]+)"/i);
    return match ? trimText(match[1]) : '';
  }
}

function collectPagePayloadFromDocument(documentRef, responseStatus = null) {
  const sku = extractSkuFromDocument(documentRef);

  if (pageLooks404(documentRef, responseStatus)) {
    return {
      plugin: 'unknown',
      status: 'skipped',
      sku,
      options: [],
      error: 'Page appears to be 404 and no SKU was found.',
    };
  }

  const plugin = detectPluginFromSignals(getPluginSignalsFromDocument(documentRef));

  if (plugin === 'ymq') {
    const options = extractYmqOptionsFromDocument(documentRef);

    return {
      plugin,
      status: 'completed',
      sku,
      options,
      error: options.length > 0 ? null : 'No exportable YMQ image options were found after filtering labels such as color, vip, and number.',
    };
  }

  if (plugin === 'customily') {
    return {
      plugin,
      status: 'unsupported',
      sku,
      options: [],
      error: 'Customily pages are detected but not supported in this version.',
    };
  }

  if (plugin === 'teeinblue') {
    return {
      plugin,
      status: 'unsupported',
      sku,
      options: [],
      error: 'Teeinblue pages are detected but not supported in this version.',
    };
  }

  return {
    plugin,
    status: 'failed',
    sku,
    options: [],
    error: 'No supported option plugin was detected.',
  };
}

function collectPagePayloadFromHtml(html, responseStatus = null) {
  const text = String(html || '');
  const sku = extractSkuFromHtml(text);

  if (pageHtmlLooks404(text, responseStatus)) {
    return {
      plugin: 'unknown',
      status: 'skipped',
      sku,
      options: [],
      error: 'Page appears to be 404 and no SKU was found.',
    };
  }

  const options = extractYmqOptionsFromHtml(text);

  if (options.length > 0) {
    return {
      plugin: 'ymq',
      status: 'completed',
      sku,
      options,
      error: null,
    };
  }

  if (/tee-form-action|tee-form-wrapper/i.test(text)) {
    return {
      plugin: 'teeinblue',
      status: 'unsupported',
      sku,
      options: [],
      error: 'Teeinblue pages are detected but not supported in this version.',
    };
  }

  if (/customily-preview-button/i.test(text)) {
    return {
      plugin: 'customily',
      status: 'unsupported',
      sku,
      options: [],
      error: 'Customily pages are detected but not supported in this version.',
    };
  }

  return {
    plugin: 'unknown',
    status: 'failed',
    sku,
    options: [],
    error: 'No supported option plugin was detected.',
  };
}

function extractYmqOptionsFromHtml(html) {
  const productId = extractCurrentProductIdFromHtml(html);
  const optionSets = extractYmqOptionSetObjects(html);
  const optionMap = new Map();

  optionSets.forEach((optionSet) => {
    if (!isYmqOptionSetAssignedToProduct(optionSet, productId)) {
      return;
    }

    const template = optionSet && optionSet.template && typeof optionSet.template === 'object'
      ? optionSet.template
      : {};

    Object.values(template).forEach((definition) => {
      const name = trimText(definition && definition.label);
      const lowered = name.toLowerCase();

      if (!name || lowered.includes('color') || lowered.includes('vip') || lowered.includes('number')) {
        return;
      }

      const values = [];
      const optionValues = definition && definition.options && typeof definition.options === 'object'
        ? definition.options
        : {};

      Object.values(optionValues).forEach((optionValue) => {
        const imageValue = decodeHtmlEntities(trimText(optionValue && optionValue.value));
        const sourceImageUrl = trimText(
          (optionValue && optionValue.canvas2)
          || (optionValue && optionValue.canvas1)
          || (optionValue && optionValue.image)
          || ''
        );

        if (!sourceImageUrl || shouldSkipYmqImageValue(name, imageValue)) {
          return;
        }

        values.push({
          image_value: imageValue,
          source_image_url: absolutizeUrl(sourceImageUrl),
          extension: extensionFromUrl(sourceImageUrl),
        });
      });

      if (values.length === 0) {
        return;
      }

      const existing = optionMap.get(name) || [];
      optionMap.set(name, existing.concat(values));
    });
  });

  return Array.from(optionMap.entries()).map(([name, values]) => ({
    name,
    values: uniqueValues(values),
  }));
}

function extractCurrentProductIdFromHtml(html) {
  const text = String(html || '');
  const patterns = [
    /var\s+__st\s*=\s*\{[\s\S]*?["']?rid["']?\s*:\s*"?(\d+)"?/i,
    /productId\s*:\s*"?(\d+)"?/i,
    /ymq_option\.product\s*=\s*\{[\s\S]*?["']?id["']?\s*:\s*"?(\d+)"?/i,
  ];

  for (const pattern of patterns) {
    const match = text.match(pattern);

    if (match && match[1]) {
      return String(match[1]);
    }
  }

  return '';
}

function isYmqOptionSetAssignedToProduct(optionSet, productId) {
  if (!productId) {
    return true;
  }

  const products = optionSet
    && optionSet.assign
    && optionSet.assign.manual
    && Array.isArray(optionSet.assign.manual.product)
    ? optionSet.assign.manual.product
    : [];

  if (products.length === 0) {
    return false;
  }

  return products.some((product) => String(
    product && typeof product === 'object' ? (product.id || product.product_id || '') : product
  ) === productId);
}

function extractYmqOptionSetObjects(html) {
  const text = String(html || '');
  const objects = [];
  let searchIndex = 0;
  const marker = 'ymq_option.option_sets';

  while (searchIndex < text.length) {
    const markerIndex = text.indexOf(marker, searchIndex);

    if (markerIndex === -1) {
      break;
    }

    const equalsIndex = text.indexOf('=', markerIndex);
    const objectStart = text.indexOf('{', equalsIndex);

    if (equalsIndex === -1 || objectStart === -1) {
      searchIndex = markerIndex + marker.length;
      continue;
    }

    const objectEnd = findBalancedObjectEnd(text, objectStart);

    if (objectEnd === -1) {
      searchIndex = objectStart + 1;
      continue;
    }

    const literal = text.slice(objectStart, objectEnd + 1);
    const parsed = parseJavaScriptObjectLiteral(literal);

    if (parsed) {
      objects.push(parsed);
    }

    searchIndex = objectEnd + 1;
  }

  return objects;
}

function findBalancedObjectEnd(text, startIndex) {
  let depth = 0;
  let quote = '';
  let escaped = false;

  for (let index = startIndex; index < text.length; index += 1) {
    const char = text[index];

    if (quote) {
      if (escaped) {
        escaped = false;
      } else if (char === '\\') {
        escaped = true;
      } else if (char === quote) {
        quote = '';
      }
      continue;
    }

    if (char === '"' || char === "'" || char === '`') {
      quote = char;
      continue;
    }

    if (char === '{') {
      depth += 1;
    } else if (char === '}') {
      depth -= 1;

      if (depth === 0) {
        return index;
      }
    }
  }

  return -1;
}

function parseJavaScriptObjectLiteral(literal) {
  try {
    // The YMQ snippet is emitted as a JavaScript object assignment, not strict JSON.
    // Evaluating only the isolated object literal lets us read that config without running the page script.
    // eslint-disable-next-line no-new-func
    return Function(`"use strict"; return (${literal});`)();
  } catch (error) {
    return null;
  }
}

function extractSkuFromHtml(html) {
  const text = String(html || '');
  const skuMatch = text.match(/"sku"\s*:\s*"([^"]+)"/i);

  if (skuMatch) {
    return trimText(skuMatch[1]);
  }

  const itempropMatch = text.match(/itemprop=["']sku["'][^>]*(?:content=["']([^"']+)["'])?/i);

  if (itempropMatch && itempropMatch[1]) {
    return trimText(itempropMatch[1]);
  }

  return '';
}

function pageHtmlLooks404(html, responseStatus = null) {
  if (Number(responseStatus) === 404) {
    return true;
  }

  return /(^|\b)(404|page not found|not found)(\b|$)/i.test(String(html || ''));
}

function pageLooks404(documentRef, responseStatus = null) {
  if (Number(responseStatus) === 404) {
    return true;
  }

  const title = trimText(documentRef && documentRef.title);
  const bodyText = trimText(documentRef && documentRef.body ? documentRef.body.textContent : '');
  const text = `${title} ${bodyText}`;

  return /(^|\b)(404|page not found|not found)(\b|$)/i.test(text);
}

function shouldSkipYmqImageValue(optionName, imageValue) {
  if (!String(optionName || '').toLowerCase().includes('names')) {
    return false;
  }

  const value = trimText(imageValue);

  if (!/^\d+$/.test(value)) {
    return false;
  }

  return Number(value) < 20;
}

function findSkuInObject(value) {
  if (!value || typeof value !== 'object') {
    return '';
  }

  if (typeof value.sku === 'string' && trimText(value.sku)) {
    return trimText(value.sku);
  }

  if (Array.isArray(value.variants)) {
    for (const variant of value.variants) {
      const sku = findSkuInObject(variant);
      if (sku) {
        return sku;
      }
    }
  }

  if (Array.isArray(value)) {
    for (const item of value) {
      const sku = findSkuInObject(item);
      if (sku) {
        return sku;
      }
    }
    return '';
  }

  for (const key of Object.keys(value)) {
    const sku = findSkuInObject(value[key]);
    if (sku) {
      return sku;
    }
  }

  return '';
}

async function scrapeUrls(urls, timeoutSeconds) {
  const { chromium } = require('playwright');
  const launchOptions = {
    headless: true,
    args: [
      '--disable-blink-features=AutomationControlled',
      '--disable-dev-shm-usage',
      '--disable-http2',
      '--disable-quic',
      '--disable-setuid-sandbox',
      '--ignore-certificate-errors',
      '--no-sandbox',
    ],
  };

  if (process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE) {
    launchOptions.executablePath = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE;
  }

  const browser = await chromium.launch(launchOptions);
  const results = [];

  try {
    for (const url of urls) {
      results.push(await scrapeUrl(browser, url, timeoutSeconds));
    }
  } finally {
    await browser.close();
  }

  return results;
}

async function scrapeUrl(browser, url, timeoutSeconds) {
  const page = await browser.newPage({
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36',
    extraHTTPHeaders: {
      'Accept-Language': 'en-US,en;q=0.9',
    },
    ignoreHTTPSErrors: true,
    locale: 'en-US',
    timezoneId: 'America/Los_Angeles',
    viewport: {
      width: 1440,
      height: 1800,
    },
  });
  const timeoutMs = Math.max(1000, Number(timeoutSeconds || 120) * 1000);

  try {
    await page.addInitScript(() => {
      Object.defineProperty(navigator, 'webdriver', {
        get: () => undefined,
      });
    }).catch(() => {});

    const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: timeoutMs });
    const responseStatus = response ? response.status() : null;
    await page.waitForLoadState('networkidle', { timeout: Math.min(timeoutMs, 15000) }).catch(() => {});
    await page.waitForSelector('.tee-form-action, .tee-form-wrapper, .ymq-options-box[ymq-option-label], .ymq-option-condition-hide, ymq-option, .customily-preview-button', {
      timeout: Math.min(timeoutMs, 20000),
    }).catch(() => {});
    await prepareDynamicOptionContent(page, timeoutMs);

    const payload = await page.evaluate(({ runtimeSource, responseStatus: browserResponseStatus }) => {
      // eslint-disable-next-line no-eval
      eval(runtimeSource);
      return collectPagePayloadFromDocument(document, browserResponseStatus);
    }, { runtimeSource: browserRuntimeSource(), responseStatus });

    if (shouldUseHtmlFallback(payload)) {
      const fallbackPayload = await fetchPagePayloadFromHtml(url, timeoutMs, responseStatus).catch(() => null);

      if (fallbackPayload && fallbackPayload.options.length > 0) {
        return Object.assign({ url }, fallbackPayload, { browser_error: payload.error || null });
      }
    }

    return Object.assign({ url }, payload);
  } catch (error) {
    const fallbackPayload = await fetchPagePayloadFromHtml(url, timeoutMs).catch(() => null);

    if (fallbackPayload && (fallbackPayload.options.length > 0 || fallbackPayload.sku)) {
      return Object.assign({ url }, fallbackPayload, { browser_error: error.message });
    }

    return {
      url,
      plugin: 'unknown',
      status: 'failed',
      sku: '',
      options: [],
      error: error.message,
    };
  } finally {
    await page.close();
  }
}

function shouldUseHtmlFallback(payload) {
  if (!payload || payload.status === 'failed' || payload.plugin === 'unknown') {
    return true;
  }

  return payload.plugin === 'ymq' && (!payload.options || payload.options.length === 0);
}

async function fetchPagePayloadFromHtml(url, timeoutMs, responseStatus = null) {
  const html = await fetchHtml(url, timeoutMs);

  return collectPagePayloadFromHtml(html, responseStatus);
}

async function fetchHtml(url, timeoutMs) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), Math.min(timeoutMs, 45000));

  try {
    const response = await fetch(url, {
      redirect: 'follow',
      signal: controller.signal,
      headers: {
        accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'accept-language': 'en-US,en;q=0.9',
        'cache-control': 'no-cache',
        pragma: 'no-cache',
        'user-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36',
      },
    });

    return await response.text();
  } finally {
    clearTimeout(timeout);
  }
}

async function prepareDynamicOptionContent(page, timeoutMs) {
  const deadline = Date.now() + Math.min(timeoutMs, 45000);

  await revealHiddenYmqOptions(page).catch(() => {});
  await page.waitForTimeout(500).catch(() => {});

  for (let index = 0; index < 4 && Date.now() < deadline; index += 1) {
    await scrollPageForLazyImages(page).catch(() => {});
    await revealHiddenYmqOptions(page).catch(() => {});
    await waitForYmqOptionImageCountStable(page, Math.min(7000, Math.max(1000, deadline - Date.now()))).catch(() => {});
  }

  await page.evaluate(() => window.scrollTo(0, 0)).catch(() => {});
}

async function revealHiddenYmqOptions(page) {
  await page.evaluate(() => {
    const hiddenElements = Array.from(document.querySelectorAll('.ymq-option-condition-hide, [hidden], [aria-hidden="true"]'));

    hiddenElements.forEach((element) => {
      if (!(element instanceof HTMLElement)) {
        return;
      }

      element.hidden = false;
      element.removeAttribute('hidden');
      element.removeAttribute('aria-hidden');
      element.classList.remove('ymq-option-condition-hide');
      element.style.removeProperty('display');
      element.style.setProperty('display', 'block', 'important');
      element.style.removeProperty('visibility');
      element.style.removeProperty('opacity');
    });

    Array.from(document.querySelectorAll('.ymq-options-box[style], ymq-option[style]')).forEach((element) => {
      if (!(element instanceof HTMLElement)) {
        return;
      }

      const style = String(element.getAttribute('style') || '');

      if (/display\s*:\s*none/i.test(style)) {
        element.style.removeProperty('display');
        element.style.setProperty('display', 'block', 'important');
      }
    });
  });
}

async function scrollPageForLazyImages(page) {
  await page.evaluate(async () => {
    const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
    const height = Math.max(
      document.body ? document.body.scrollHeight : 0,
      document.documentElement ? document.documentElement.scrollHeight : 0,
    );
    const step = Math.max(400, Math.floor((window.innerHeight || 900) * 0.75));

    for (let y = 0; y <= height; y += step) {
      window.scrollTo(0, y);
      document.dispatchEvent(new Event('scroll', { bubbles: true }));
      window.dispatchEvent(new Event('scroll'));
      await delay(180);
    }

    window.scrollTo(0, height);
    await delay(300);
  });
}

async function waitForYmqOptionImageCountStable(page, timeoutMs = 7000) {
  const startedAt = Date.now();
  let previousSignature = '';
  let stableCount = 0;

  while (Date.now() - startedAt < timeoutMs) {
    const signature = await page.evaluate(() => {
      const boxes = Array.from(document.querySelectorAll('.ymq-options-box[ymq-option-label]'));
      const imageCount = document.querySelectorAll('.ymq-option-swatches-image-container .ymq-option-swatches-image').length;
      const valueCount = document.querySelectorAll('.ymq-option-value-item').length;
      return `${boxes.length}:${valueCount}:${imageCount}:${document.body ? document.body.scrollHeight : 0}`;
    });

    if (signature === previousSignature) {
      stableCount += 1;

      if (stableCount >= 3) {
        return signature;
      }
    } else {
      previousSignature = signature;
      stableCount = 0;
    }

    await page.waitForTimeout(500);
  }

  return previousSignature;
}

function browserRuntimeSource() {
  return [
    detectPluginFromSignals,
    getPluginSignalsFromDocument,
    extractYmqOptionsFromDocument,
    extractSkuFromDocument,
    extractSkuFromJsonText,
    findSkuInObject,
    collectPagePayloadFromDocument,
    collectPagePayloadFromHtml,
    pageLooks404,
    pageHtmlLooks404,
    shouldSkipYmqImageValue,
    safeQueryCount,
    removeDisplayNoneImportant,
    trimText,
    getAttribute,
    extractBackgroundImageUrl,
    absolutizeUrl,
    decodeHtmlEntities,
    extensionFromUrl,
    uniqueValues,
  ].map((fn) => fn.toString()).join('\n');
}

function safeQueryCount(documentRef, selector) {
  try {
    return documentRef.querySelectorAll ? documentRef.querySelectorAll(selector).length : 0;
  } catch (error) {
    return 0;
  }
}

function removeDisplayNoneImportant(styleText) {
  return styleText
    .split(';')
    .map((part) => part.trim())
    .filter((part) => part && !/^display\s*:\s*none\s*!important$/i.test(part))
    .concat(['display: block'])
    .join('; ');
}

function trimText(value) {
  return String(value || '').replace(/\s+/g, ' ').trim();
}

function decodeHtmlEntities(value) {
  return String(value || '')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&#(\d+);/g, (match, code) => String.fromCharCode(Number(code)))
    .replace(/&#x([0-9a-f]+);/gi, (match, code) => String.fromCharCode(parseInt(code, 16)));
}

function getAttribute(element, name) {
  if (!element || typeof element.getAttribute !== 'function') {
    return '';
  }

  return element.getAttribute(name) || '';
}

function extractBackgroundImageUrl(styleText) {
  const match = String(styleText || '').match(/url\((['"]?)(.*?)\1\)/i);
  return match ? match[2] : '';
}

function absolutizeUrl(url) {
  const value = trimText(url);

  if (!value || value.startsWith('data:')) {
    return value;
  }

  if (typeof window !== 'undefined' && window.location) {
    return new URL(value, window.location.href).href;
  }

  return value;
}

function extensionFromUrl(url) {
  try {
    const parsed = new URL(url, 'https://example.invalid');
    const match = parsed.pathname.match(/\.([a-z0-9]+)$/i);
    const extension = match ? match[1].toLowerCase() : '';
    return extension === 'jpeg' ? 'jpg' : (extension || 'image');
  } catch (error) {
    const match = String(url || '').match(/\.([a-z0-9]+)(?:[?#]|$)/i);
    return match ? match[1].toLowerCase() : 'image';
  }
}

function uniqueValues(values) {
  const seen = new Set();
  const unique = [];

  values.forEach((value) => {
    const key = `${value.image_value}\u001f${value.source_image_url}`;

    if (seen.has(key)) {
      return;
    }

    seen.add(key);
    unique.push(value);
  });

  return unique;
}

function parseArgs(argv) {
  const args = {
    input: null,
    output: null,
    timeout: 120,
  };

  for (let index = 2; index < argv.length; index += 1) {
    const arg = argv[index];

    if (arg === '--input') {
      args.input = argv[++index];
    } else if (arg === '--output') {
      args.output = argv[++index];
    } else if (arg === '--timeout') {
      args.timeout = Number(argv[++index] || 120);
    }
  }

  return args;
}

async function runCli() {
  const args = parseArgs(process.argv);

  if (!args.input || !args.output) {
    throw new Error('Usage: node scripts/sku-options-scraper.js --input urls.json --output result.json [--timeout 120]');
  }

  const input = JSON.parse(fs.readFileSync(args.input, 'utf8'));
  const urls = Array.isArray(input.urls) ? input.urls.map((url) => String(url).trim()).filter(Boolean) : [];

  if (urls.length === 0) {
    throw new Error('Input JSON must contain a non-empty urls array.');
  }

  const results = await scrapeUrls(urls, args.timeout);
  fs.mkdirSync(nodePath.dirname(args.output), { recursive: true });
  fs.writeFileSync(args.output, JSON.stringify({ results }, null, 2));
}

if (require.main === module) {
  runCli().catch((error) => {
    console.error(error.stack || error.message);
    process.exit(1);
  });
}

module.exports = {
  detectPluginFromSignals,
  collectPagePayloadFromDocument,
  collectPagePayloadFromHtml,
  extractYmqOptionsFromDocument,
  extractYmqOptionsFromHtml,
  extractSkuFromDocument,
  pageLooks404,
  shouldSkipYmqImageValue,
  extensionFromUrl,
};
