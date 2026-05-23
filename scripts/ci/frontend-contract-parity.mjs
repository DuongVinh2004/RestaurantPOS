import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, '../../');

const ALLOWLIST_PATH = process.env.ALLOWLIST_PATH || path.join(ROOT_DIR, 'config/frontend-contract-parity.allowlist.json');
let allowlist = [];
if (fs.existsSync(ALLOWLIST_PATH)) {
    allowlist = JSON.parse(fs.readFileSync(ALLOWLIST_PATH, 'utf-8'));
    for (const item of allowlist) {
        if (!item.owner || !item.reason || !item.launch_impact) {
            console.error('ERROR: Allowlist item missing owner, reason, or launch_impact:', item);
            process.exit(1);
        }
    }
}

function isPathAllowed(rawPath, isCustomer) {
    const appName = isCustomer ? 'customer-web' : 'staff-web';
    for (const rule of allowlist) {
        if (rule.frontend_app === appName || rule.frontend_app === 'ANY') {
            const regex = new RegExp(`^${rule.path}$`);
            if (regex.test(rawPath)) {
                return true;
            }
        }
    }
    return false;
}

const OPENAPI_PATH = path.join(ROOT_DIR, 'storage/app/booking_release/openapi-v1.json');

const CUSTOMER_WEB_SRC = process.env.CUSTOMER_WEB_SRC || path.join(ROOT_DIR, 'customer-web/src');
const STAFF_WEB_SRC = process.env.STAFF_WEB_SRC || path.join(ROOT_DIR, 'staff-web/src');

const OUTPUT_JSON = process.env.OUTPUT_JSON || path.join(ROOT_DIR, 'storage/app/booking_release/frontend_contract_parity.json');
const OUTPUT_MD = process.env.OUTPUT_MD || path.join(ROOT_DIR, 'docs/architecture/frontend-contract-parity.md');

// Extract valid routes and operationIds from OpenAPI
function loadOpenApi() {
    if (!fs.existsSync(OPENAPI_PATH)) {
        console.error(`OpenAPI file not found at ${OPENAPI_PATH}`);
        process.exit(1);
    }
    const openapi = JSON.parse(fs.readFileSync(OPENAPI_PATH, 'utf-8'));
    const operationIds = new Set();
    const rawPaths = new Set(); // e.g. "GET /api/v1/health"

    for (const [routePath, methods] of Object.entries(openapi.paths || {})) {
        for (const [method, operation] of Object.entries(methods)) {
            if (operation.operationId) {
                // Convert snake_case or dash-case to camelCase for SDK method matching
                const camelCaseId = operation.operationId.replace(/([-_][a-z])/ig, ($1) => {
                    return $1.toUpperCase()
                        .replace('-', '')
                        .replace('_', '');
                });
                operationIds.add(camelCaseId);
            }
            
            // Convert /api/v1/admin/benefits/{id} to a normalized regex or pattern
            // For simplicity, we will store a generalized path by replacing {param} with regex `[^/]+`
            const generalizedPath = routePath.replace(/{[^}]+}/g, '[^/]+');
            rawPaths.add(`${method.toUpperCase()} ${generalizedPath}`);
        }
    }

    return { operationIds, rawPaths, openapi };
}

function walkSync(dir, filelist = []) {
    if (!fs.existsSync(dir)) return filelist;
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const filepath = path.join(dir, file);
        if (fs.statSync(filepath).isDirectory()) {
            filelist = walkSync(filepath, filelist);
        } else {
            if (filepath.endsWith('.ts') || filepath.endsWith('.tsx') || filepath.endsWith('.js') || filepath.endsWith('.jsx')) {
                filelist.push(filepath);
            }
        }
    }
    return filelist;
}

function matchPathAgainstOpenApi(rawPath, rawPathsOpenApi) {
    // rawPath from code might look like: /api/v1/admin/menu/categories or /admin/menu/categories
    let normalizedPath = rawPath.split('?')[0];
    if (!normalizedPath.startsWith('/api/')) {
        normalizedPath = `/api/v1${normalizedPath.startsWith('/') ? '' : '/'}${normalizedPath}`;
    }

    // Try to find if normalizedPath matches any of the generalized patterns in rawPathsOpenApi
    // Note: since frontend usages might just be raw strings, we don't always know the method.
    // We will just check if the path itself matches.
    for (const entry of rawPathsOpenApi) {
        const methodAndPath = entry.split(' ');
        const pathPattern = methodAndPath[1];
        const regex = new RegExp(`^${pathPattern}$`);
        if (regex.test(normalizedPath)) {
            return true;
        }
    }
    return false;
}

function analyzeFrontend(srcDir, operationIds, rawPathsOpenApi, isCustomer) {
    const files = walkSync(srcDir);
    const results = {
        sdkUsages: {},
        rawUsages: {},
        invalidSdk: [],
        invalidRaw: [],
        bypasses: [],
    };

    // Patterns
    const sdkRegex = isCustomer ? /customerClient\.([a-zA-Z0-9_]+)/g : /staffClient\.([a-zA-Z0-9_]+)/g;
    // Look for string literals that look like paths, handling both quotes and template literals
    // Covers /api/v1/..., /admin/..., /customer/..., /staff/...
    const rawPathRegex = /(?:['"`])(\/api\/v1\/[a-zA-Z0-9_\-\/]+|\/admin\/[a-zA-Z0-9_\-\/]+|\/customer\/[a-zA-Z0-9_\-\/]+|\/staff\/[a-zA-Z0-9_\-\/]+)(?:['"`]|\$|\\|\?)/g;
    // Detect string concatenations meant to bypass parity checker
    const bypassRegex = /(?:['"`])(\/api\/v1|\/admin|\/customer|\/staff)(?:['"`])\s*\+\s*(?:['"`])/g;

    for (const file of files) {
        const content = fs.readFileSync(file, 'utf-8');
        const relativePath = path.relative(srcDir, file);

        let match;
        while ((match = bypassRegex.exec(content)) !== null) {
            results.bypasses.push({ file: relativePath });
        }
        while ((match = sdkRegex.exec(content)) !== null) {
            const method = match[1];
            if (!results.sdkUsages[method]) {
                results.sdkUsages[method] = new Set();
            }
            results.sdkUsages[method].add(relativePath);

            if (!operationIds.has(method)) {
                results.invalidSdk.push({ method, file: relativePath });
            }
        }

        while ((match = rawPathRegex.exec(content)) !== null) {
            const rawPath = match[1];
            if (!results.rawUsages[rawPath]) {
                results.rawUsages[rawPath] = new Set();
            }
            results.rawUsages[rawPath].add(relativePath);

            if (!isPathAllowed(rawPath, isCustomer) && !matchPathAgainstOpenApi(rawPath, rawPathsOpenApi)) {
                results.invalidRaw.push({ path: rawPath, file: relativePath });
            }
        }
    }

    // Convert Sets to Arrays
    for (const key of Object.keys(results.sdkUsages)) {
        results.sdkUsages[key] = Array.from(results.sdkUsages[key]);
    }
    for (const key of Object.keys(results.rawUsages)) {
        results.rawUsages[key] = Array.from(results.rawUsages[key]);
    }

    return results;
}

function main() {
    console.log('Loading OpenAPI contract...');
    const { operationIds, rawPaths, openapi } = loadOpenApi();
    
    console.log(`Found ${operationIds.size} operationIds and ${rawPaths.size} routes in OpenAPI.`);

    console.log('Scanning customer-web...');
    const customerResults = analyzeFrontend(CUSTOMER_WEB_SRC, operationIds, rawPaths, true);
    
    console.log('Scanning staff-web...');
    const staffResults = analyzeFrontend(STAFF_WEB_SRC, operationIds, rawPaths, false);

    const report = {
        generatedAt: new Date().toISOString(),
        customerWeb: customerResults,
        staffWeb: staffResults,
        unusedBackendOperations: []
    };

    // Find API-only/deferred
    const usedOperationIds = new Set([
        ...Object.keys(customerResults.sdkUsages),
        ...Object.keys(staffResults.sdkUsages)
    ]);
    
    for (const id of operationIds) {
        if (!usedOperationIds.has(id)) {
            report.unusedBackendOperations.push(id);
        }
    }

    // JSON Output
    fs.mkdirSync(path.dirname(OUTPUT_JSON), { recursive: true });
    fs.writeFileSync(OUTPUT_JSON, JSON.stringify(report, null, 2));

    // Markdown Output
    let md = `# Frontend API Contract Parity Report\n\nGenerated at: ${report.generatedAt}\n\n`;
    
    md += `## Overview\n`;
    md += `- **Backend Operations:** ${operationIds.size}\n`;
    md += `- **Unused Backend Operations (API-only/Deferred):** ${report.unusedBackendOperations.length}\n\n`;

    md += `## Invalid Usages (Action Required!)\n`;
    const invalidSdkTotal = customerResults.invalidSdk.length + staffResults.invalidSdk.length;
    const invalidRawTotal = customerResults.invalidRaw.length + staffResults.invalidRaw.length;
    const bypassesTotal = customerResults.bypasses.length + staffResults.bypasses.length;
    
    if (bypassesTotal > 0) {
        md += `### Parity Gate Bypasses Detected\n`;
        md += `String concatenations attempting to bypass parity scanner were found.\n`;
        if (customerResults.bypasses.length > 0) {
            customerResults.bypasses.forEach(i => md += `- Bypass in \`${i.file}\`\n`);
        }
        if (staffResults.bypasses.length > 0) {
            staffResults.bypasses.forEach(i => md += `- Bypass in \`${i.file}\`\n`);
        }
        md += `\n`;
    }

    if (invalidSdkTotal === 0 && invalidRawTotal === 0 && bypassesTotal === 0) {
        md += `✅ No invalid API usages found.\n\n`;
    } else {
        if (customerResults.invalidSdk.length > 0) {
            md += `### Customer Web - Invalid SDK Methods\n`;
            customerResults.invalidSdk.forEach(i => md += `- \`${i.method}\` in \`${i.file}\`\n`);
        }
        if (customerResults.invalidRaw.length > 0) {
            md += `### Customer Web - Invalid Raw Paths\n`;
            customerResults.invalidRaw.forEach(i => md += `- \`${i.path}\` in \`${i.file}\`\n`);
        }
        if (staffResults.invalidSdk.length > 0) {
            md += `### Staff Web - Invalid SDK Methods\n`;
            staffResults.invalidSdk.forEach(i => md += `- \`${i.method}\` in \`${i.file}\`\n`);
        }
        if (staffResults.invalidRaw.length > 0) {
            md += `### Staff Web - Invalid Raw Paths\n`;
            staffResults.invalidRaw.forEach(i => md += `- \`${i.path}\` in \`${i.file}\`\n`);
        }
    }

    md += `\n## Raw Path Usages (Should Migrate to SDK)\n`;
    md += `Raw paths are valid in the OpenAPI contract but should ideally use the generated SDK.\n\n`;
    
    if (Object.keys(customerResults.rawUsages).length > 0) {
        md += `### Customer Web\n`;
        for (const [raw, files] of Object.entries(customerResults.rawUsages)) {
            md += `- \`${raw}\` (used in ${files.length} files)\n`;
        }
    }
    if (Object.keys(staffResults.rawUsages).length > 0) {
        md += `### Staff Web\n`;
        for (const [raw, files] of Object.entries(staffResults.rawUsages)) {
            md += `- \`${raw}\` (used in ${files.length} files)\n`;
        }
    }

    md += `\n## Unused Backend Operations\n`;
    if (report.unusedBackendOperations.length > 0) {
        md += `<details><summary>Click to view ${report.unusedBackendOperations.length} deferred/API-only operations</summary>\n\n`;
        report.unusedBackendOperations.forEach(op => {
            md += `- \`${op}\`\n`;
        });
        md += `\n</details>\n`;
    } else {
        md += `All backend operations are used by the frontends.\n`;
    }

    fs.mkdirSync(path.dirname(OUTPUT_MD), { recursive: true });
    fs.writeFileSync(OUTPUT_MD, md);

    console.log(`Reports generated at:\n- ${OUTPUT_JSON}\n- ${OUTPUT_MD}`);

    if (invalidSdkTotal > 0 || invalidRawTotal > 0 || bypassesTotal > 0) {
        console.error('ERROR: Found invalid API usages, missing allowlist entries, or parity gate bypasses.');
        process.exit(1);
    } else {
        console.log('Gate passed. Parity is good.');
        process.exit(0);
    }
}

main();
