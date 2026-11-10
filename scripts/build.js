const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

console.log('=== GPAMS Build & Syntax Verification ===');

const phpBin = 'c:\\xampp\\php\\php.exe';
const rootDir = path.resolve(__dirname, '..');

function collectPhpFiles(dir, list = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (['vendor', 'node_modules', '.git', '.agents', 'uploads', 'templates', 'public'].includes(entry.name)) {
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
console.log(`Found ${phpFiles.length} PHP application files.`);

if (fs.existsSync(phpBin)) {
  console.log(`Running local PHP syntax check (${phpBin})...`);
  for (const file of phpFiles) {
    execSync(`"${phpBin}" -d extension=pdo_pgsql -d extension=pgsql -l "${file}"`, {
      stdio: 'pipe',
    });
  }
  console.log(`✔ All ${phpFiles.length} PHP files passed PHP CLI syntax validation.`);
} else {
  // On Vercel / Linux build container where Windows XAMPP php.exe is not present
  console.log('CI/Cloud build environment detected — verifying PHP & config files...');
  for (const file of phpFiles) {
    const content = fs.readFileSync(file, 'utf8');
    if (!content.includes('<?php') && !content.includes('<?=')) {
      throw new Error(`Invalid PHP file header in ${path.relative(rootDir, file)}`);
    }
  }
  console.log(`✔ All ${phpFiles.length} PHP files verified.`);
}

for (const reqFile of ['neon.ts', 'manifest.json', 'service-worker.js', 'vercel.json', 'database/schema_postgres.sql']) {
  const p = path.join(rootDir, reqFile);
  if (!fs.existsSync(p)) {
    throw new Error(`Missing required file: ${reqFile}`);
  }
  console.log(`✔ Verified ${reqFile}`);
}

// Populate public/ directory for Vercel static output
const publicDir = path.join(rootDir, 'public');
fs.mkdirSync(publicDir, { recursive: true });

for (const staticFile of ['manifest.json', 'service-worker.js', 'favicon.ico']) {
  const src = path.join(rootDir, staticFile);
  if (fs.existsSync(src)) {
    fs.copyFileSync(src, path.join(publicDir, staticFile));
  }
}

for (const staticDir of ['img']) {
  const srcDir = path.join(rootDir, staticDir);
  if (fs.existsSync(srcDir)) {
    fs.cpSync(srcDir, path.join(publicDir, staticDir), { recursive: true });
  }
}

console.log('✔ Prepared public/ output directory for Vercel.');
console.log('=== Build Succeeded ===');
