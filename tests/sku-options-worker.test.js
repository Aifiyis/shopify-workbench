const test = require('node:test');
const assert = require('node:assert/strict');
const {
  collectPagePayloadFromHtml,
  collectPagePayloadFromDocument,
  detectPluginFromSignals,
  extractYmqOptionsFromDocument,
  extractYmqOptionsFromHtml,
} = require('../scripts/sku-options-scraper');

function fakeElement(attributes = {}, children = []) {
  return {
    attributes,
    children,
    style: {
      display: attributes.styleDisplay || '',
      setProperty(name, value) {
        this[name] = value;
      },
      removeProperty(name) {
        delete this[name];
      },
    },
    textContent: attributes.textContent || '',
    getAttribute(name) {
      return this.attributes[name] || null;
    },
    setAttribute(name, value) {
      this.attributes[name] = value;
    },
    removeAttribute(name) {
      delete this.attributes[name];
    },
    querySelector(selector) {
      return this.querySelectorAll(selector)[0] || null;
    },
    querySelectorAll(selector) {
      const results = [];
      const visit = (node) => {
        if (matches(node, selector)) {
          results.push(node);
        }
        (node.children || []).forEach(visit);
      };
      (this.children || []).forEach(visit);
      return results;
    },
    closest(selector) {
      let current = this.parent || null;
      while (current) {
        if (matches(current, selector)) {
          return current;
        }
        current = current.parent || null;
      }
      return null;
    },
  };
}

function linkParents(node) {
  (node.children || []).forEach((child) => {
    child.parent = node;
    linkParents(child);
  });
  return node;
}

function matches(node, selector) {
  if (selector === '.ymq-option-condition-hide') {
    return (node.attributes.class || '').split(/\s+/).includes('ymq-option-condition-hide');
  }
  if (selector === 'ymq-option') {
    return String(node.attributes.tag || '').toLowerCase() === 'ymq-option';
  }
  if (selector === '.ymq-options-box[ymq-option-label]') {
    return (node.attributes.class || '').split(/\s+/).includes('ymq-options-box') && !!node.attributes['ymq-option-label'];
  }
  if (selector === '.ymq-option-swatches-image-container .ymq-option-swatches-image') {
    return (node.attributes.class || '').split(/\s+/).includes('ymq-option-swatches-image');
  }
  if (selector === '.ymq-option-value-item') {
    return (node.attributes.class || '').split(/\s+/).includes('ymq-option-value-item');
  }
  if (selector === '.ymq-option-swatches-input') {
    return (node.attributes.class || '').split(/\s+/).includes('ymq-option-swatches-input');
  }
  if (selector === '.customily-preview-button') {
    return (node.attributes.class || '').split(/\s+/).includes('customily-preview-button');
  }
  if (selector === '.tee-form-action') {
    return (node.attributes.class || '').split(/\s+/).includes('tee-form-action');
  }
  if (selector === '.tee-form-wrapper') {
    return (node.attributes.class || '').split(/\s+/).includes('tee-form-wrapper');
  }
  if (selector === '[class*="customily" i], [id*="customily" i], iframe[src*="customily" i]') {
    return String(node.attributes.class || '').toLowerCase().includes('customily')
      || String(node.attributes.id || '').toLowerCase().includes('customily')
      || String(node.attributes.src || '').toLowerCase().includes('customily');
  }
  return false;
}

test('detects Teeinblue before Customily before YMQ and unknown pages', () => {
  assert.equal(detectPluginFromSignals({ teeinblueCount: 1, ymqCount: 1, customilyCount: 1 }), 'teeinblue');
  assert.equal(detectPluginFromSignals({ teeinblueCount: 0, ymqCount: 1, customilyCount: 1 }), 'customily');
  assert.equal(detectPluginFromSignals({ teeinblueCount: 0, ymqCount: 0, customilyCount: 1 }), 'customily');
  assert.equal(detectPluginFromSignals({ teeinblueCount: 0, ymqCount: 1, customilyCount: 0 }), 'ymq');
  assert.equal(detectPluginFromSignals({ teeinblueCount: 0, ymqCount: 0, customilyCount: 0 }), 'unknown');
});

test('marks Teeinblue documents as unsupported before YMQ extraction', () => {
  const document = linkParents(fakeElement({}, [
    fakeElement({ class: 'tee-form-wrapper' }),
    fakeElement({ tag: 'ymq-option' }),
  ]));

  const result = collectPagePayloadFromDocument(document);

  assert.equal(result.plugin, 'teeinblue');
  assert.equal(result.status, 'unsupported');
  assert.match(result.error, /Teeinblue/i);
});

test('detects YMQ when the page contains ymq-option tags', () => {
  const document = linkParents(fakeElement({}, [
    fakeElement({ tag: 'ymq-option' }),
  ]));

  const result = collectPagePayloadFromDocument(document);

  assert.equal(result.plugin, 'ymq');
  assert.equal(result.status, 'completed');
  assert.match(result.error, /No exportable YMQ image options/i);
});

test('extracts YMQ options, reveals hidden boxes, and skips color/vip/number labels', () => {
  const hidden = fakeElement({ class: 'ymq-option-condition-hide', style: 'display: none !important' });
  const input = fakeElement({ class: 'ymq-option-swatches-input', value: 'Names with Heart' });
  const image = fakeElement({ class: 'ymq-option-swatches-image', src: 'https://cdn.example.test/heart.png' });
  const valueItem = fakeElement({ class: 'ymq-option-value-item' }, [
    input,
    fakeElement({ class: 'ymq-option-swatches-image-container' }, [image]),
  ]);
  const sleeveBox = fakeElement({ class: 'ymq-options-box', 'ymq-option-label': 'Left Sleeve Icon' }, [valueItem]);
  const colorBox = fakeElement({ class: 'ymq-options-box', 'ymq-option-label': 'Color' }, [valueItem]);
  const document = linkParents(fakeElement({}, [hidden, sleeveBox, colorBox]));

  const result = extractYmqOptionsFromDocument(document);

  assert.equal(hidden.style.display, 'block');
  assert.equal(result.length, 1);
  assert.equal(result[0].name, 'Left Sleeve Icon');
  assert.deepEqual(result[0].values, [
    {
      image_value: 'Names with Heart',
      source_image_url: 'https://cdn.example.test/heart.png',
      extension: 'png',
    },
  ]);
});

test('extracts YMQ image options from static option set HTML', () => {
  const html = `
    <script>
      ymq_option.option_sets['tem1'] = {
        template: {
          abc: {
            label: 'Choose Your Greeting Card',
            options: {
              a: { value: 'I Love You &quot;Card&quot;', canvas2: 'https://cdn.example.test/card.jpg' },
              b: { value: 'No Thanks', canvas2: 'https://cdn.example.test/no.png' }
            }
          },
          color: {
            label: 'Color',
            options: {
              red: { value: 'Red', canvas2: 'https://cdn.example.test/red.png' }
            }
          }
        }
      };
    </script>
  `;

  const options = extractYmqOptionsFromHtml(html);

  assert.equal(options.length, 1);
  assert.equal(options[0].name, 'Choose Your Greeting Card');
  assert.deepEqual(options[0].values, [
    {
      image_value: 'I Love You "Card"',
      source_image_url: 'https://cdn.example.test/card.jpg',
      extension: 'jpg',
    },
    {
      image_value: 'No Thanks',
      source_image_url: 'https://cdn.example.test/no.png',
      extension: 'png',
    },
  ]);
});

test('filters static YMQ option sets to the current product id', () => {
  const html = `
    <script>var __st = {"rid":12345};</script>
    <script>
      ymq_option.option_sets['current'] = {
        assign: { manual: { product: [{ id: 12345 }] } },
        template: {
          icon: {
            label: 'Icon',
            options: {
              a: { value: 'Current Product', canvas2: 'https://cdn.example.test/current.png' }
            }
          }
        }
      };
      ymq_option.option_sets['other'] = {
        assign: { manual: { product: [{ id: 99999 }] } },
        template: {
          icon: {
            label: 'Icon',
            options: {
              a: { value: 'Other Product', canvas2: 'https://cdn.example.test/other.png' }
            }
          }
        }
      };
    </script>
  `;

  const options = extractYmqOptionsFromHtml(html);

  assert.equal(options.length, 1);
  assert.equal(options[0].name, 'Icon');
  assert.deepEqual(options[0].values, [
    {
      image_value: 'Current Product',
      source_image_url: 'https://cdn.example.test/current.png',
      extension: 'png',
    },
  ]);
});

test('skips static YMQ option sets without product assignment when product id is known', () => {
  const html = `
    <script>var __st = {"rid":12345};</script>
    <script>
      ymq_option.option_sets['unassigned'] = {
        assign: { manual: { product: [] } },
        template: {
          icon: {
            label: 'Icon',
            options: {
              a: { value: 'Ambiguous Product', canvas2: 'https://cdn.example.test/ambiguous.png' }
            }
          }
        }
      };
    </script>
  `;

  const result = collectPagePayloadFromHtml(html);

  assert.equal(result.plugin, 'unknown');
  assert.equal(result.status, 'failed');
  assert.deepEqual(result.options, []);
});

test('collects completed YMQ payload from static HTML fallback', () => {
  const html = `
    <script>{"sku":"TEST-QK1000"}</script>
    <script>
      ymq_option.option_sets['tem1'] = {
        template: {
          abc: {
            label: 'Icon',
            options: {
              a: { value: 'Smile', canvas2: 'https://cdn.example.test/smile.webp' }
            }
          }
        }
      };
    </script>
  `;

  const result = collectPagePayloadFromHtml(html);

  assert.equal(result.plugin, 'ymq');
  assert.equal(result.status, 'completed');
  assert.equal(result.sku, 'TEST-QK1000');
  assert.equal(result.options.length, 1);
});

test('skips numeric image values below 20 when YMQ option name contains names', () => {
  const skippedInput = fakeElement({ class: 'ymq-option-swatches-input', value: '11' });
  const skippedImage = fakeElement({ class: 'ymq-option-swatches-image', src: 'https://cdn.example.test/11.png' });
  const keptInput = fakeElement({ class: 'ymq-option-swatches-input', value: '20' });
  const keptImage = fakeElement({ class: 'ymq-option-swatches-image', src: 'https://cdn.example.test/20.png' });
  const namesBox = fakeElement({ class: 'ymq-options-box', 'ymq-option-label': 'Names Count' }, [
    fakeElement({ class: 'ymq-option-value-item' }, [
      skippedInput,
      fakeElement({ class: 'ymq-option-swatches-image-container' }, [skippedImage]),
    ]),
    fakeElement({ class: 'ymq-option-value-item' }, [
      keptInput,
      fakeElement({ class: 'ymq-option-swatches-image-container' }, [keptImage]),
    ]),
  ]);
  const document = linkParents(fakeElement({}, [namesBox]));

  const result = extractYmqOptionsFromDocument(document);

  assert.equal(result.length, 1);
  assert.deepEqual(result[0].values, [
    {
      image_value: '20',
      source_image_url: 'https://cdn.example.test/20.png',
      extension: 'png',
    },
  ]);
});

test('marks Customily documents as unsupported only when preview button exists', () => {
  const document = linkParents(fakeElement({}, [
    fakeElement({ class: 'customily-preview-button' }),
  ]));

  const result = collectPagePayloadFromDocument(document);

  assert.equal(result.plugin, 'customily');
  assert.equal(result.status, 'unsupported');
  assert.match(result.error, /not supported/i);
});

test('does not mark generic Customily text or classes as Customily without preview button', () => {
  const document = linkParents(fakeElement({}, [
    fakeElement({ class: 'customily-options' }),
  ]));

  const result = collectPagePayloadFromDocument(document);

  assert.equal(result.plugin, 'unknown');
  assert.equal(result.status, 'failed');
});

test('skips 404 pages without SKU instead of returning failed products', () => {
  const document = linkParents(fakeElement({}, []));
  document.title = '404 Not Found';
  document.body = { textContent: '404 page not found' };

  const result = collectPagePayloadFromDocument(document, 404);

  assert.equal(result.plugin, 'unknown');
  assert.equal(result.status, 'skipped');
  assert.equal(result.sku, '');
  assert.match(result.error, /404/i);
});

test('skips HTTP 404 pages even when stale SKU or YMQ option data is present', () => {
  const html = `
    <script>{"sku":"CS-QK3385-TH-Crewneck"}</script>
    <script>
      ymq_option.option_sets['stale'] = {
        template: {
          icon: {
            label: 'Icon',
            options: {
              a: { value: 'Stale Image', canvas2: 'https://cdn.example.test/stale.png' }
            }
          }
        }
      };
    </script>
  `;

  const result = collectPagePayloadFromHtml(html, 404);

  assert.equal(result.plugin, 'unknown');
  assert.equal(result.status, 'skipped');
  assert.equal(result.sku, 'CS-QK3385-TH-Crewneck');
  assert.deepEqual(result.options, []);
  assert.match(result.error, /404/i);
});

test('marks pages without known option plugins as failed unknown pages', () => {
  const document = linkParents(fakeElement({}, []));

  const result = collectPagePayloadFromDocument(document);

  assert.equal(result.plugin, 'unknown');
  assert.equal(result.status, 'failed');
  assert.match(result.error, /No supported option plugin/i);
});
