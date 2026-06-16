const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const timestamp = new Date().toISOString().replace(/[-:T.Z]/g, '').slice(0, 14);
const jsonPath = path.resolve(root, 'storage/app/private/sku-options-image.json');
const imageRoot = path.resolve(root, 'storage/app/private/sku-options-image');
const backupPath = path.resolve(root, `storage/app/private/sku-options-image.before-empty-sku-cleanup-${timestamp}.json`);
const reportPath = path.resolve(root, `storage/app/private/sku-options-empty-sku-cleanup-report-${timestamp}.json`);

function main() {
  fs.copyFileSync(jsonPath, backupPath);

  const before = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
  const products = before.products || [];
  const options = before.options || [];
  const removeIds = new Set(
    products
      .filter((product) => String(product.sku || '').trim() === '')
      .map((product) => Number(product.id))
  );

  const removedProducts = products.filter((product) => removeIds.has(Number(product.id)));
  const keptProducts = products.filter((product) => !removeIds.has(Number(product.id)));
  const removedOptions = options.filter((option) => removeIds.has(Number(option.product_id)));
  const keptOptions = options.filter((option) => !removeIds.has(Number(option.product_id)));

  const keptPaths = new Set(keptOptions.map((option) => String(option.image_path || '')).filter(Boolean));
  const candidatePaths = [...new Set(removedOptions.map((option) => String(option.image_path || '')).filter(Boolean))]
    .filter((imagePath) => !keptPaths.has(imagePath));

  let deletedImages = 0;
  let missingImages = 0;
  let skippedImages = 0;

  for (const imagePath of candidatePaths) {
    const relativePath = imagePath.replace(/^\/+/, '').replace(/^sku-options-image[\\/]/, '');
    const absolutePath = path.resolve(imageRoot, relativePath);

    if (!absolutePath.startsWith(imageRoot + path.sep)) {
      skippedImages += 1;
      continue;
    }

    if (!fs.existsSync(absolutePath)) {
      missingImages += 1;
      continue;
    }

    fs.unlinkSync(absolutePath);
    deletedImages += 1;
  }

  const after = {
    products: keptProducts,
    options: keptOptions,
  };
  fs.writeFileSync(jsonPath, JSON.stringify(after, null, 2));

  const report = {
    backup: backupPath,
    reportPath,
    before: {
      products: products.length,
      options: options.length,
    },
    removed: {
      products: removedProducts.length,
      options: removedOptions.length,
      productIds: removedProducts.map((product) => product.id),
    },
    after: {
      products: keptProducts.length,
      options: keptOptions.length,
    },
    images: {
      candidatePaths: candidatePaths.length,
      deletedImages,
      missingImages,
      skippedImages,
    },
  };

  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
}

main();
