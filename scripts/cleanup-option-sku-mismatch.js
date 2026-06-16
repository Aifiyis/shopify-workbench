const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const timestamp = new Date().toISOString().replace(/[-:T.Z]/g, '').slice(0, 14);
const jsonPath = path.resolve(root, 'storage/app/private/sku-options-image.json');
const imageRoot = path.resolve(root, 'storage/app/private/sku-options-image');
const backupPath = path.resolve(root, `storage/app/private/sku-options-image.before-option-sku-sync-${timestamp}.json`);
const reportPath = path.resolve(root, `storage/app/private/sku-options-option-sku-sync-report-${timestamp}.json`);

function slug(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/gi, '-')
    .replace(/^-+|-+$/g, '');
}

function publicImagePath(fileName) {
  return `/sku-options-image/${fileName}`;
}

function absoluteImagePath(imagePath) {
  const relativePath = String(imagePath || '')
    .replace(/^\/+/, '')
    .replace(/^sku-options-image[\\/]/, '');

  return path.resolve(imageRoot, relativePath);
}

function plannedImagePath(option, productSku) {
  const currentPath = String(option.image_path || '');
  const currentFileName = path.basename(currentPath);
  const extension = path.extname(currentFileName);
  const oldSkuSlug = slug(option.sku);
  const newSkuSlug = slug(productSku);

  if (!currentFileName || !newSkuSlug) {
    return currentPath;
  }

  if (oldSkuSlug && currentFileName.startsWith(`${oldSkuSlug}_`)) {
    return publicImagePath(`${newSkuSlug}${currentFileName.slice(oldSkuSlug.length)}`);
  }

  const valueSlug = slug(option.image_value);
  const suffix = valueSlug ? `_${valueSlug}` : currentFileName.replace(extension, '').replace(/^[^_]+_?/, '_');

  return publicImagePath(`${newSkuSlug}${suffix}${extension || ''}`);
}

function main() {
  fs.copyFileSync(jsonPath, backupPath);

  const before = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
  const products = before.products || [];
  const options = before.options || [];
  const productsById = new Map(products.map((product) => [Number(product.id), product]));
  const finalReferencedPaths = new Set();
  const mismatches = [];
  const renamePlans = new Map();

  for (const option of options) {
    const product = productsById.get(Number(option.product_id));

    if (!product) {
      if (option.image_path) {
        finalReferencedPaths.add(String(option.image_path));
      }
      continue;
    }

    const productSku = String(product.sku || '').trim();
    const optionSku = String(option.sku || '').trim();

    if (productSku === optionSku) {
      if (option.image_path) {
        finalReferencedPaths.add(String(option.image_path));
      }
      continue;
    }

    const oldImagePath = String(option.image_path || '');
    const newImagePath = plannedImagePath(option, productSku);

    mismatches.push({
      product_id: option.product_id,
      old_sku: optionSku,
      new_sku: productSku,
      old_image_path: oldImagePath,
      new_image_path: newImagePath,
    });

    option.sku = productSku;

    if (oldImagePath && newImagePath && oldImagePath !== newImagePath) {
      option.image_path = newImagePath;

      if (!renamePlans.has(oldImagePath)) {
        renamePlans.set(oldImagePath, newImagePath);
      }
    }

    if (option.image_path) {
      finalReferencedPaths.add(String(option.image_path));
    }
  }

  let renamedImages = 0;
  let reusedExistingTargets = 0;
  let missingSources = 0;
  let skippedUnsafePaths = 0;
  let deletedOldImages = 0;
  const collisions = [];

  for (const [oldImagePath, newImagePath] of renamePlans) {
    const oldAbsolutePath = absoluteImagePath(oldImagePath);
    const newAbsolutePath = absoluteImagePath(newImagePath);

    if (!oldAbsolutePath.startsWith(imageRoot + path.sep) || !newAbsolutePath.startsWith(imageRoot + path.sep)) {
      skippedUnsafePaths += 1;
      continue;
    }

    if (!fs.existsSync(oldAbsolutePath)) {
      missingSources += 1;
      continue;
    }

    if (fs.existsSync(newAbsolutePath)) {
      reusedExistingTargets += 1;

      if (!finalReferencedPaths.has(oldImagePath)) {
        fs.unlinkSync(oldAbsolutePath);
        deletedOldImages += 1;
      }

      collisions.push({ old_image_path: oldImagePath, new_image_path: newImagePath });
      continue;
    }

    fs.mkdirSync(path.dirname(newAbsolutePath), { recursive: true });
    fs.renameSync(oldAbsolutePath, newAbsolutePath);
    renamedImages += 1;
  }

  fs.writeFileSync(jsonPath, JSON.stringify(before, null, 2));

  const report = {
    backup: backupPath,
    reportPath,
    mismatchedOptions: mismatches.length,
    mismatchedProducts: new Set(mismatches.map((item) => item.product_id)).size,
    imagePlans: renamePlans.size,
    images: {
      renamedImages,
      reusedExistingTargets,
      deletedOldImages,
      missingSources,
      skippedUnsafePaths,
      collisions: collisions.slice(0, 50),
    },
    samples: mismatches.slice(0, 50),
  };

  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
}

main();
