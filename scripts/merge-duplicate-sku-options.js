const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const timestamp = new Date().toISOString().replace(/[-:T.Z]/g, '').slice(0, 14);
const jsonPath = path.resolve(root, 'storage/app/private/sku-options-image.json');
const backupPath = path.resolve(root, `storage/app/private/sku-options-image.before-duplicate-sku-merge-${timestamp}.json`);
const reportPath = path.resolve(root, `storage/app/private/sku-options-duplicate-sku-merge-report-${timestamp}.json`);
const csvPath = path.resolve(root, `storage/app/private/sku-options-duplicate-sku-merge-report-${timestamp}.csv`);
const dryRunReportPath = path.resolve(root, `storage/app/private/sku-options-duplicate-sku-merge-report-dry-run-${timestamp}.json`);
const dryRunCsvPath = path.resolve(root, `storage/app/private/sku-options-duplicate-sku-merge-report-dry-run-${timestamp}.csv`);

function parseArgs(argv) {
  const args = {
    canonicalOverrides: {},
    dryRun: false,
    excludedProductIds: new Set(),
    skus: [],
    writeDryRunReport: false,
  };

  for (const arg of argv) {
    if (arg === '--dry-run') {
      args.dryRun = true;
      continue;
    }

    if (arg === '--write-dry-run-report') {
      args.writeDryRunReport = true;
      continue;
    }

    if (arg.startsWith('--canonical-conflict=')) {
      const value = arg.slice('--canonical-conflict='.length);
      const [sku, productId] = value.split(':');
      const normalizedSku = normalizeSku(sku);

      if (normalizedSku && Number(productId) > 0) {
        args.canonicalOverrides[normalizedSku] = Number(productId);
      }

      continue;
    }

    if (arg.startsWith('--exclude-product-id=')) {
      const productId = Number(arg.slice('--exclude-product-id='.length));

      if (productId > 0) {
        args.excludedProductIds.add(productId);
      }

      continue;
    }

    if (arg.startsWith('--skus=')) {
      args.skus.push(...arg.slice('--skus='.length).split(','));
      continue;
    }

    args.skus.push(...arg.split(','));
  }

  args.skus = [...new Set(args.skus.map(normalizeSku).filter(Boolean))];

  return args;
}

function normalizeSku(value) {
  return String(value || '').trim().toUpperCase();
}

function optionProductId(option) {
  return Number(option.product_id);
}

function optionSortOrder(option) {
  return Number(option.sort_order || 0);
}

function imageFileName(option) {
  return path.basename(String(option.image_path || '')).toLowerCase();
}

function isAllowedPlaceholderFileName(fileName) {
  const extension = path.extname(String(fileName || '')).toLowerCase();

  if (!['.jpg', '.jpeg', '.png', '.webp'].includes(extension)) {
    return false;
  }

  const stem = path.basename(String(fileName || '').toLowerCase(), extension);
  const token = stem.includes('_') ? stem.slice(stem.lastIndexOf('_') + 1) : stem;

  return ['no', 'no-thanks', 'yes'].includes(token);
}

function sourceUrl(option) {
  return String(option.source_image_url || '').trim();
}

function sourceUrlConflictKey(option) {
  const url = sourceUrl(option);

  try {
    const parsed = new URL(url);
    return `${parsed.origin}${parsed.pathname}`;
  } catch (_error) {
    return url.split('?')[0].split('#')[0];
  }
}

function normalizedImageValue(value) {
  return String(value || '')
    .normalize('NFKC')
    .trim()
    .toLowerCase()
    .replace(/[._,\uFF0C]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function optionImageKey(option) {
  const fileName = imageFileName(option);

  return [
    normalizedImageValue(option.image_value),
    String(option.image_path || '').trim(),
    isAllowedPlaceholderFileName(fileName) ? '' : sourceUrl(option),
  ].join('\x1f');
}

function csvEscape(value) {
  const text = String(value ?? '');
  return /[",\r\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
}

function cloneOptionForProduct(option, product, sortOrder) {
  return {
    ...option,
    product_id: product.id,
    sort_order: sortOrder,
    sku: product.sku,
  };
}

function optionSummary(option) {
  return {
    product_id: option.product_id,
    sort_order: option.sort_order,
    sku: option.sku,
    option_name: option.option_name,
    image_value: option.image_value,
    image_path: option.image_path,
    source_image_url: option.source_image_url,
  };
}

function detectFilenameSourceConflicts(groupOptions) {
  const byFileName = new Map();

  for (const option of groupOptions) {
    const fileName = imageFileName(option);

    if (!fileName) {
      continue;
    }

    if (!byFileName.has(fileName)) {
      byFileName.set(fileName, new Map());
    }

    const bySource = byFileName.get(fileName);
    const source = sourceUrlConflictKey(option);

    if (!bySource.has(source)) {
      bySource.set(source, []);
    }

    bySource.get(source).push(optionSummary(option));
  }

  const conflicts = [];

  for (const [fileName, bySource] of byFileName) {
    if (bySource.size <= 1) {
      continue;
    }

    conflicts.push({
      image_file_name: fileName,
      distinct_source_image_urls: [...bySource.keys()],
      examples: [...bySource.entries()].map(([source, options]) => ({
        source_image_url: source,
        options: options.slice(0, 10),
      })),
    });
  }

  return conflicts;
}

function blockingFilenameSourceConflicts(conflicts) {
  return conflicts.filter((conflict) => !isAllowedPlaceholderFileName(conflict.image_file_name));
}

function enrichConflictsWithProductLinks(conflicts, productsById) {
  return conflicts.map((conflict) => {
    const productMap = new Map();

    for (const example of conflict.examples || []) {
      for (const option of example.options || []) {
        const productId = optionProductId(option);
        const product = productsById.get(productId) || {};

        if (!productMap.has(productId)) {
          productMap.set(productId, {
            product_id: productId,
            product_url: product.product_url || '',
            sku: product.sku || option.sku || '',
            image_values: [],
            source_image_urls: [],
          });
        }

        const item = productMap.get(productId);

        if (option.image_value && !item.image_values.includes(option.image_value)) {
          item.image_values.push(option.image_value);
        }

        if (option.source_image_url && !item.source_image_urls.includes(option.source_image_url)) {
          item.source_image_urls.push(option.source_image_url);
        }
      }
    }

    return {
      ...conflict,
      conflicting_products: [...productMap.values()].sort((left, right) => left.product_id - right.product_id),
    };
  });
}

function applyCanonicalConflictOverride(groupOptions, conflicts, canonicalProductId) {
  const conflictFileNames = new Set((conflicts || []).map((conflict) => String(conflict.image_file_name || '').toLowerCase()));

  if (!canonicalProductId || conflictFileNames.size === 0) {
    return {
      applied: false,
      options: groupOptions,
      canonical_product_id: canonicalProductId || null,
      resolved_file_names: [],
      missing_canonical_file_names: [],
    };
  }

  const canonicalFileNames = new Set(
    groupOptions
      .filter((option) => optionProductId(option) === Number(canonicalProductId))
      .map(imageFileName)
      .filter((fileName) => conflictFileNames.has(fileName))
  );
  const missingCanonicalFileNames = [...conflictFileNames].filter((fileName) => !canonicalFileNames.has(fileName));

  if (missingCanonicalFileNames.length > 0) {
    return {
      applied: false,
      options: groupOptions,
      canonical_product_id: Number(canonicalProductId),
      resolved_file_names: [...canonicalFileNames],
      missing_canonical_file_names: missingCanonicalFileNames,
    };
  }

  return {
    applied: true,
    options: groupOptions.filter((option) => {
      const fileName = imageFileName(option);

      return !conflictFileNames.has(fileName) || optionProductId(option) === Number(canonicalProductId);
    }),
    canonical_product_id: Number(canonicalProductId),
    resolved_file_names: [...canonicalFileNames],
    missing_canonical_file_names: [],
  };
}

function buildMergedOptions(groupOptions) {
  const seen = new Set();
  const merged = [];

  const sorted = [...groupOptions].sort((left, right) => {
    return optionProductId(left) - optionProductId(right)
      || optionSortOrder(left) - optionSortOrder(right)
      || String(left.option_name || '').localeCompare(String(right.option_name || ''))
      || String(left.image_value || '').localeCompare(String(right.image_value || ''));
  });

  for (const option of sorted) {
    const key = optionImageKey(option);

    if (seen.has(key)) {
      continue;
    }

    seen.add(key);
    merged.push(option);
  }

  return merged;
}

function main() {
  const args = parseArgs(process.argv.slice(2));

  if (args.skus.length === 0) {
    console.error('Usage: node scripts/merge-duplicate-sku-options.js --skus=SKU1,SKU2 [--dry-run]');
    process.exitCode = 1;
    return;
  }

  const data = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
  const products = Array.isArray(data.products) ? data.products : [];
  const options = Array.isArray(data.options) ? data.options : [];
  const targetSkuSet = new Set(args.skus);
  const productsBySku = new Map();
  const productsById = new Map(products.map((product) => [Number(product.id), product]));
  const optionsByProductId = new Map();

  for (const option of options) {
    const productId = optionProductId(option);

    if (!optionsByProductId.has(productId)) {
      optionsByProductId.set(productId, []);
    }

    optionsByProductId.get(productId).push(option);
  }

  for (const product of products) {
    if (args.excludedProductIds.has(Number(product.id))) {
      continue;
    }

    const sku = normalizeSku(product.cleaned_sku || product.sku);

    if (!targetSkuSet.has(sku)) {
      continue;
    }

    if (!productsBySku.has(sku)) {
      productsBySku.set(sku, []);
    }

    productsBySku.get(sku).push(product);
  }

  const missingSkus = args.skus.filter((sku) => !productsBySku.has(sku));
  const mergedSkuReports = [];
  const skippedSkuReports = [];
  const unchangedSkuReports = [];
  const replaceProductIds = new Set();
  const replacementOptions = [];

  for (const sku of args.skus) {
    const groupProducts = productsBySku.get(sku) || [];

    if (groupProducts.length < 2) {
      unchangedSkuReports.push({
        sku,
        reason: groupProducts.length === 0 ? 'SKU was not found in products.' : 'SKU has fewer than two product records.',
        product_count: groupProducts.length,
      });
      continue;
    }

    groupProducts.sort((left, right) => Number(left.id) - Number(right.id));

    const groupOptions = groupProducts.flatMap((product) => optionsByProductId.get(Number(product.id)) || []);
    const conflicts = detectFilenameSourceConflicts(groupOptions);
    const blockingConflicts = blockingFilenameSourceConflicts(conflicts);
    const ignoredPlaceholderConflicts = conflicts.filter((conflict) => isAllowedPlaceholderFileName(conflict.image_file_name));
    const canonicalProductId = args.canonicalOverrides[sku] || null;
    const canonicalOverride = applyCanonicalConflictOverride(groupOptions, blockingConflicts, canonicalProductId);
    const remainingBlockingConflicts = canonicalOverride.applied ? [] : blockingConflicts;
    const beforeCounts = groupProducts.map((product) => ({
      product_id: product.id,
      options_count: (optionsByProductId.get(Number(product.id)) || []).length,
      product_url: product.product_url,
    }));

    if (remainingBlockingConflicts.length > 0) {
      skippedSkuReports.push({
        sku,
        product_count: groupProducts.length,
        before_counts: beforeCounts,
        conflict_count: remainingBlockingConflicts.length,
        ignored_placeholder_conflict_count: ignoredPlaceholderConflicts.length,
        canonical_override: canonicalOverride,
        conflicts: enrichConflictsWithProductLinks(remainingBlockingConflicts, productsById),
        ignored_placeholder_conflicts: enrichConflictsWithProductLinks(ignoredPlaceholderConflicts, productsById),
      });
      continue;
    }

    const mergedOptions = buildMergedOptions(canonicalOverride.options);

    for (const product of groupProducts) {
      replaceProductIds.add(Number(product.id));

      mergedOptions.forEach((option, index) => {
        replacementOptions.push(cloneOptionForProduct(option, product, index + 1));
      });
    }

    mergedSkuReports.push({
      sku,
      product_count: groupProducts.length,
      product_ids: groupProducts.map((product) => product.id),
      before_counts: beforeCounts,
      merged_option_count: mergedOptions.length,
      ignored_placeholder_conflict_count: ignoredPlaceholderConflicts.length,
      canonical_override: canonicalOverride,
      ignored_placeholder_conflicts: enrichConflictsWithProductLinks(ignoredPlaceholderConflicts, productsById),
      after_counts: groupProducts.map((product) => ({
        product_id: product.id,
        options_count: mergedOptions.length,
      })),
    });
  }

  const outputOptions = options.filter((option) => !replaceProductIds.has(optionProductId(option))).concat(replacementOptions);

  const report = {
    source: jsonPath,
    generated_at: new Date().toISOString(),
    dry_run: args.dryRun,
    requested_skus: args.skus,
    excluded_product_ids: [...args.excludedProductIds].sort((a, b) => a - b),
    missing_skus: missingSkus,
    before: {
      products: products.length,
      options: options.length,
    },
    after: {
      products: products.length,
      options: outputOptions.length,
    },
    merged_sku_groups: mergedSkuReports.length,
    skipped_sku_groups: skippedSkuReports.length,
    unchanged_sku_groups: unchangedSkuReports.length,
    replaced_product_ids: [...replaceProductIds].sort((a, b) => a - b),
    merged: mergedSkuReports,
    skipped: skippedSkuReports,
    unchanged: unchangedSkuReports,
  };

  const selectedReportPath = args.dryRun ? dryRunReportPath : reportPath;
  const selectedCsvPath = args.dryRun ? dryRunCsvPath : csvPath;
  const shouldWriteReport = !args.dryRun || args.writeDryRunReport;

  if (shouldWriteReport) {
    fs.writeFileSync(selectedReportPath, JSON.stringify(report, null, 2));
  }

  const rows = [[
    'status',
    'sku',
    'product_count',
    'product_ids',
    'before_counts',
    'merged_option_count',
    'conflict_count',
    'ignored_placeholder_conflict_count',
    'conflict_file_names',
  ]];

  for (const item of mergedSkuReports) {
    rows.push([
      'merged',
      item.sku,
      item.product_count,
      item.product_ids.join('|'),
      item.before_counts.map((product) => `${product.product_id}:${product.options_count}`).join('|'),
      item.merged_option_count,
      '',
      item.ignored_placeholder_conflict_count,
      '',
    ]);
  }

  for (const item of skippedSkuReports) {
    rows.push([
      'skipped_conflict',
      item.sku,
      item.product_count,
      item.before_counts.map((product) => product.product_id).join('|'),
      item.before_counts.map((product) => `${product.product_id}:${product.options_count}`).join('|'),
      '',
      item.conflict_count,
      item.ignored_placeholder_conflict_count,
      item.conflicts.map((conflict) => conflict.image_file_name).join('|'),
    ]);
  }

  for (const item of unchangedSkuReports) {
    rows.push([
      'unchanged',
      item.sku,
      item.product_count,
      '',
      item.reason,
      '',
      '',
      '',
      '',
    ]);
  }

  if (shouldWriteReport) {
    fs.writeFileSync(selectedCsvPath, rows.map((row) => row.map(csvEscape).join(',')).join('\n'));
  }

  if (!args.dryRun) {
    fs.copyFileSync(jsonPath, backupPath);
    fs.writeFileSync(jsonPath, JSON.stringify({
      ...data,
      products,
      options: outputOptions,
    }, null, 2));
  }

  console.log(JSON.stringify({
    dry_run: args.dryRun,
    backupPath: args.dryRun ? null : backupPath,
    reportPath: shouldWriteReport ? selectedReportPath : null,
    csvPath: shouldWriteReport ? selectedCsvPath : null,
    before: report.before,
    after: report.after,
    merged_sku_groups: report.merged_sku_groups,
    skipped_sku_groups: report.skipped_sku_groups,
    unchanged_sku_groups: report.unchanged_sku_groups,
    skipped_skus: skippedSkuReports.map((item) => item.sku),
  }, null, 2));
}

if (require.main === module) {
  main();
}

module.exports = {
  applyCanonicalConflictOverride,
  buildMergedOptions,
  blockingFilenameSourceConflicts,
  detectFilenameSourceConflicts,
  isAllowedPlaceholderFileName,
  normalizedImageValue,
  optionImageKey,
  parseArgs,
};
