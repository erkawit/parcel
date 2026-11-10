const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

console.log('=== GPAMS Build & Syntax Verification ===');

const phpBin = 'c:\\xampp\\php\\php.exe';
const rootDir = path.resolve(__dirname, '..');

function collectPhpFiles(dir, list = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (['vendor', 'node_modules', '.git', 'uploads', 'templates'].includes(entry.name)) {
      continue;
    }
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      collectPhpFiles(fullPath, list);
    } else if (entry.isFile() && entry.name.endsWith('.php')) {
      list.push(fullPath);
    }
  }
  return list;
}

const phpFiles = collectPhpFiles(rootDir);
console.log(`Checking syntax of ${phpFiles.length} PHP files...`);

for (const file of phpFiles) {
  const rel = path.relative(rootDir, file);
  execSync(`"${phpBin}" -d extension=pdo_pgsql -d extension=pgsql -l "${file}"`, {
    stdio: 'pipe',
  });
}
console.log(`✔ All ${phpFiles.length} PHP files passed syntax validation.`);

for (const reqFile of ['neon.ts', 'manifest.json', 'service-worker.js', 'database/schema_postgres.sql']) {
  const p = path.join(rootDir, reqFile);
  if (!fs.existsSync(p)) {
    throw new Error(`Missing required file: ${reqFile}`);
  }
  console.log(`✔ Verified ${reqFile}`);
}

console.log('=== Build Succeeded ===');
