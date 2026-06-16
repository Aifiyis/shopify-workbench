const test = require('node:test');
const assert = require('node:assert/strict');

const {
  buildMergedOptions,
  applyCanonicalConflictOverride,
  detectFilenameSourceConflicts,
  isAllowedPlaceholderFileName,
  normalizedImageValue,
  parseArgs,
} = require('../scripts/merge-duplicate-sku-options');

test('buildMergedOptions deduplicates repeated image rows across different option names', () => {
  const rows = [
    {
      product_id: 424,
      sort_order: 1,
      option_name: 'Add Embroidery Icon Options On Left Sleeve?',
      image_value: 'No\uff0cthanks',
      image_path: '/sku-options-image/cs-qk0050-cx_no-thanks.jpg',
      source_image_url: 'https://cdn.example.com/no-thanks.jpg',
    },
    {
      product_id: 424,
      sort_order: 53,
      option_name: 'Add Embroidery Options On Left Sleeve?',
      image_value: 'No Thanks',
      image_path: '/sku-options-image/cs-qk0050-cx_no-thanks.jpg',
      source_image_url: 'https://cdn.example.com/no-thanks.jpg',
    },
  ];

  const merged = buildMergedOptions(rows);

  assert.equal(merged.length, 1);
  assert.equal(merged[0].sort_order, 1);
});

test('normalizedImageValue treats common no-thanks punctuation variants as the same value', () => {
  assert.equal(normalizedImageValue('No\uff0cthanks'), 'no thanks');
  assert.equal(normalizedImageValue('No. Thanks'), 'no thanks');
  assert.equal(normalizedImageValue('No Thanks'), 'no thanks');
});

test('isAllowedPlaceholderFileName allows yes and no placeholder files with sku prefixes', () => {
  assert.equal(isAllowedPlaceholderFileName('cs-qk2567-cx_no-thanks.jpg'), true);
  assert.equal(isAllowedPlaceholderFileName('hm-qk5319_no.jpg'), true);
  assert.equal(isAllowedPlaceholderFileName('cs-qk1962-th_yes.jpg'), true);
  assert.equal(isAllowedPlaceholderFileName('cs-qk3347-cx_no-thanks.webp'), true);
  assert.equal(isAllowedPlaceholderFileName('cs-qk2429-cx_full-color-embroidery.png'), false);
});

test('buildMergedOptions deduplicates allowed placeholder files even when source urls differ', () => {
  const rows = [
    {
      product_id: 574,
      sort_order: 1,
      option_name: 'Left Sleeve Icon',
      image_value: 'No Thanks',
      image_path: '/sku-options-image/cs-qk2567-cx_no-thanks.jpg',
      source_image_url: 'https://cdn.example.com/first.jpg',
    },
    {
      product_id: 2544,
      sort_order: 1,
      option_name: 'Left Sleeve Icon',
      image_value: 'No Thanks',
      image_path: '/sku-options-image/cs-qk2567-cx_no-thanks.jpg',
      source_image_url: 'https://cdn.example.com/second.jpg',
    },
  ];

  assert.equal(buildMergedOptions(rows).length, 1);
});

test('applyCanonicalConflictOverride keeps conflicting image rows from the canonical product', () => {
  const rows = [
    {
      product_id: 559,
      sort_order: 1,
      image_value: 'Full Color Embroidery',
      image_path: '/sku-options-image/cs-qk2429-cx_full-color-embroidery.png',
      source_image_url: 'https://cdn.example.com/wrong.png',
    },
    {
      product_id: 576,
      sort_order: 1,
      image_value: 'Full Color Embroidery',
      image_path: '/sku-options-image/cs-qk2429-cx_full-color-embroidery.png',
      source_image_url: 'https://cdn.example.com/right.png',
    },
    {
      product_id: 559,
      sort_order: 2,
      image_value: 'Smile',
      image_path: '/sku-options-image/cs-qk2429-cx_smile.png',
      source_image_url: 'https://cdn.example.com/smile.png',
    },
  ];
  const conflicts = [
    {
      image_file_name: 'cs-qk2429-cx_full-color-embroidery.png',
    },
  ];

  const result = applyCanonicalConflictOverride(rows, conflicts, 576);

  assert.equal(result.applied, true);
  assert.deepEqual(result.missing_canonical_file_names, []);
  assert.equal(result.options.length, 2);
  assert.equal(result.options.find((option) => option.image_value === 'Full Color Embroidery').source_image_url, 'https://cdn.example.com/right.png');
});

test('detectFilenameSourceConflicts ignores CDN query parameter differences', () => {
  const rows = [
    {
      product_id: 255,
      image_path: '/sku-options-image/cs-qk3385-th_dual-color-design.jpg',
      source_image_url: 'https://cdn.shopify.com/s/files/1/0669/5600/1470/files/yfb9il-_-ymq.jpg?width=300&height=300',
    },
    {
      product_id: 265,
      image_path: '/sku-options-image/cs-qk3385-th_dual-color-design.jpg',
      source_image_url: 'https://cdn.shopify.com/s/files/1/0669/5600/1470/files/yfb9il-_-ymq.jpg?v=1757000437&width=300&height=300',
    },
  ];

  assert.deepEqual(detectFilenameSourceConflicts(rows), []);
});

test('parseArgs reads product ids excluded from duplicate sku merging', () => {
  const args = parseArgs(['--skus=CS-QK0241', '--exclude-product-id=1199', '--exclude-product-id=1200']);

  assert.deepEqual(args.skus, ['CS-QK0241']);
  assert.deepEqual([...args.excludedProductIds].sort((a, b) => a - b), [1199, 1200]);
});
