const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const timestamp = new Date().toISOString().replace(/[-:T.Z]/g, '').slice(0, 14);
const jsonPath = path.resolve(root, 'storage/app/private/sku-options-image.json');
const reportPath = path.resolve(root, `storage/app/private/sku-option-count-conflicts-${timestamp}.json`);
const csvPath = path.resolve(root, `storage/app/private/sku-option-count-conflicts-${timestamp}.csv`);

function csvEscape(value) {
  const text = String(value ?? '');
  return /[",\r\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
}

function main() {
  const data = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
  const products = data.products || [];
  const options = data.options || [];
  const optionCounts = new Map();

  for (const option of options) {
    const productId = Number(option.product_id);
    optionCounts.set(productId, (optionCounts.get(productId) || 0) + 1);
  }

  const groups = new Map();

  for (const product of products) {
    const sku = String(product.sku || '').trim();

    if (!sku) {
      continue;
    }

    if (!groups.has(sku)) {
      groups.set(sku, []);
    }

    groups.get(sku).push({
      id: product.id,
      sku,
      original_sku: product.original_sku,
      cleaned_sku: product.cleaned_sku,
      product_url: product.product_url,
      plugin: product.plugin,
      status: product.status,
      error: product.error,
      options_count: optionCounts.get(Number(product.id)) || 0,
    });
  }

  const conflicts = [];

  for (const [sku, groupProducts] of groups) {
    if (groupProducts.length < 2) {
      continue;
    }

    const distinctCounts = [...new Set(groupProducts.map((product) => product.options_count))].sort((a, b) => a - b);

    if (distinctCounts.length <= 1) {
      continue;
    }

    conflicts.push({
      sku,
      product_count: groupProducts.length,
      distinct_options_counts: distinctCounts,
      min_options: distinctCounts[0],
      max_options: distinctCounts[distinctCounts.length - 1],
      diff: distinctCounts[distinctCounts.length - 1] - distinctCounts[0],
      products: groupProducts.sort((a, b) => a.options_count - b.options_count || Number(a.id) - Number(b.id)),
    });
  }

  conflicts.sort((a, b) => b.diff - a.diff || b.product_count - a.product_count || a.sku.localeCompare(b.sku));

  const report = {
    source: jsonPath,
    generated_at: new Date().toISOString(),
    products: products.length,
    options: options.length,
    sku_groups: groups.size,
    conflict_sku_groups: conflicts.length,
    conflict_products: conflicts.reduce((total, conflict) => total + conflict.product_count, 0),
    conflicts,
  };

  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));

  const csvRows = [[
    'sku',
    'product_count',
    'distinct_options_counts',
    'min_options',
    'max_options',
    'diff',
    'product_id',
    'options_count',
    'original_sku',
    'plugin',
    'status',
    'error',
    'product_url',
  ]];

  for (const conflict of conflicts) {
    for (const product of conflict.products) {
      csvRows.push([
        conflict.sku,
        conflict.product_count,
        conflict.distinct_options_counts.join('|'),
        conflict.min_options,
        conflict.max_options,
        conflict.diff,
        product.id,
        product.options_count,
        product.original_sku,
        product.plugin,
        product.status,
        product.error || '',
        product.product_url,
      ]);
    }
  }

  fs.writeFileSync(csvPath, csvRows.map((row) => row.map(csvEscape).join(',')).join('\n'));

  console.log(JSON.stringify({
    reportPath,
    csvPath,
    products: report.products,
    options: report.options,
    sku_groups: report.sku_groups,
    conflict_sku_groups: report.conflict_sku_groups,
    conflict_products: report.conflict_products,
    top_conflicts: conflicts.slice(0, 10).map((conflict) => ({
      sku: conflict.sku,
      product_count: conflict.product_count,
      distinct_options_counts: conflict.distinct_options_counts,
      diff: conflict.diff,
    })),
  }, null, 2));
}

main();
