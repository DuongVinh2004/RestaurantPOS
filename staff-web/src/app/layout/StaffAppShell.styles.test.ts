import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const currentDir = dirname(fileURLToPath(import.meta.url));
const indexCss = readFileSync(resolve(currentDir, '../../index.css'), 'utf8');
const mainSource = readFileSync(resolve(currentDir, '../../main.tsx'), 'utf8');
const tokensCss = readFileSync(resolve(currentDir, '../../styles/tokens.css'), 'utf8');
const uiOverridesCss = readFileSync(resolve(currentDir, '../../styles/ui-overrides.css'), 'utf8');
const providersSource = readFileSync(resolve(currentDir, '../providers/AppProviders.tsx'), 'utf8');
const shellCommandPaletteSource = readFileSync(resolve(currentDir, './StaffShellCommandPalette.tsx'), 'utf8');
const shellNavDrawerSource = readFileSync(resolve(currentDir, './StaffShellNavDrawer.tsx'), 'utf8');
const shellSource = readFileSync(resolve(currentDir, './StaffAppShell.tsx'), 'utf8');
const shellContextSource = readFileSync(resolve(currentDir, './useStaffShellContext.ts'), 'utf8');

describe('StaffAppShell layout styles', () => {
  it('keeps the shared staff-web theme in a light palette after bundle overrides load', () => {
    expect(tokensCss).toMatch(/color-scheme:\s*light;/s);
    expect(tokensCss).toMatch(/--staff-bg-page:\s*#f6f1e8;/s);
    expect(tokensCss).toMatch(/--staff-text-primary:\s*#201914;/s);
    expect(tokensCss).toMatch(/--staff-text-secondary:\s*#5d5248;/s);
    expect(tokensCss).toMatch(/--staff-text-tertiary:\s*#756452;/s);
    expect(mainSource).toMatch(/import '\.\/styles\/design-bundle-overrides\.css'[\s\S]*import '\.\/styles\/tokens\.css'[\s\S]*import '\.\/styles\/ui-overrides\.css'/);
    expect(providersSource).toMatch(/algorithm:\s*\[theme\.defaultAlgorithm,\s*theme\.compactAlgorithm\]/s);
    expect(providersSource).toMatch(/colorPrimary:\s*'#b8652c'/s);
    expect(providersSource).toMatch(/colorBgContainer:\s*'#fffbf5'/s);
    expect(providersSource).toMatch(/colorTextSecondary:\s*'#5d5248'/s);
    expect(uiOverridesCss).toMatch(/\.staff-shell-layout,\s*\.staff-shell-layout > \.ant-layout,\s*\.staff-shell-content,\s*\.staff-shell-content-dashboard,\s*\.staff-dashboard-page\s*\{[^}]*background:\s*linear-gradient\(180deg,\s*var\(--staff-bg-page-accent\)\s*0%,\s*var\(--staff-bg-page\)\s*100%\)\s*!important;/s);
    expect(uiOverridesCss).toMatch(/\.staff-dashboard-topbar\s*\{[^}]*background:\s*linear-gradient\(135deg,\s*rgba\(255,\s*251,\s*245,\s*0\.98\),\s*rgba\(248,\s*241,\s*232,\s*0\.98\)\)\s*!important;[^}]*border-color:\s*var\(--staff-border-default\)\s*!important;/s);
    expect(uiOverridesCss).toMatch(/\.ant-btn-primary\s*\{[^}]*background:\s*linear-gradient\(135deg,\s*#f1dbc5,\s*#e3bea0\)\s*!important;[^}]*border-color:\s*#dbb28d\s*!important;[^}]*color:\s*#4d3626\s*!important;/s);
    expect(uiOverridesCss).toMatch(/\.ant-table-wrapper \.ant-table-tbody > tr\.ant-table-row:hover > td[\s\S]*background:\s*rgba\(255,\s*246,\s*236,\s*0\.98\)\s*!important;/s);
    expect(uiOverridesCss).toMatch(/\.staff-eyebrow,\s*\.staff-shell-context-label,\s*\.staff-shell-command-group,\s*\.staff-dashboard-topbar-metric-label,\s*\.staff-dashboard-kpi-label,\s*\.staff-dashboard-metric-label\s*\{[^}]*color:\s*#8a6746\s*!important;/s);
  });

  it('keeps the shell header flexible for multi-line route context', () => {
    expect(indexCss).toMatch(/\.staff-shell-header\s*\{[^}]*height:\s*auto;[^}]*overflow:\s*visible;[^}]*padding:\s*16px 20px;/s);
    expect(indexCss).toMatch(/\.staff-shell-header-top\s*\{[^}]*height:\s*auto;[^}]*min-height:\s*0;[^}]*width:\s*100%;/s);
    expect(indexCss).not.toMatch(/\.staff-shell-header-dashboard\s*\{/s);
  });

  it('keeps the active shell header compact instead of stacking large context cards', () => {
    expect(uiOverridesCss).toMatch(/\.staff-shell-header\s*\{[^}]*padding:\s*12px 18px;/s);
    expect(uiOverridesCss).toMatch(/\.staff-shell-header-top\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)\s*minmax\(260px,\s*320px\);/s);
    expect(uiOverridesCss).toMatch(/\.staff-shell-header-context\s*\{[^}]*display:\s*flex;[^}]*flex-wrap:\s*wrap;[^}]*gap:\s*8px;/s);
    expect(uiOverridesCss).toMatch(/\.staff-shell-context-card\s*\{[^}]*border-radius:\s*999px;[^}]*display:\s*inline-flex;[^}]*padding:\s*7px 10px;/s);
    expect(uiOverridesCss).toMatch(/\.staff-shell-context-meta\s*\{[^}]*display:\s*none;/s);
    expect(uiOverridesCss).toMatch(/\.staff-page-header\s*\{[^}]*grid-template-columns:\s*repeat\(auto-fit,\s*minmax\(min\(100%,\s*360px\),\s*1fr\)\);/s);
    expect(uiOverridesCss).toMatch(/\.staff-page-header-actions-wrap\s*\{[^}]*min-width:\s*min\(100%,\s*360px\);/s);
    expect(shellSource).not.toContain('staff-shell-header-description');
    expect(shellSource).not.toContain('staff-shell-inline-note');
    expect(shellSource).not.toContain('shellStatusCopy');
    expect(shellContextSource).not.toMatch(/\bshellStatusCopy,\s*$/m);
  });

  it('keeps shell navigation and branch controls on native elements so the shared shell chunk avoids AntD chrome', () => {
    expect(shellSource).not.toContain("from 'antd'");
    expect(shellSource).toContain('<nav className="staff-shell-menu"');
    expect(shellSource).toContain('<select');
    expect(uiOverridesCss).toContain('.staff-shell-select-wrap::after');
    expect(uiOverridesCss).toContain('.staff-shell-control-button');
  });

  it('keeps the sidebar brand terse and improves navigation legibility', () => {
    expect(uiOverridesCss).toMatch(/\.staff-sider-brand-copy\s*\{[^}]*display:\s*none !important;/s);
    expect(uiOverridesCss).toMatch(/\.staff-shell-nav-item\s*\{[^}]*gap:\s*12px;[^}]*min-height:\s*42px;[^}]*padding:\s*10px 12px;/s);
    expect(uiOverridesCss).toMatch(/\.staff-nav-item-icon\s*\{[^}]*flex:\s*0 0 20px;[^}]*width:\s*20px;/s);
    expect(uiOverridesCss).toMatch(/\.staff-shell-sider::-webkit-scrollbar-thumb\s*\{[^}]*border-radius:\s*999px;/s);
    expect(uiOverridesCss).toMatch(/\.staff-shell-sider\s*\{[^}]*scrollbar-width:\s*thin;/s);
  });

  it('uses Drawer styles instead of the deprecated width prop on shell navigation', () => {
    expect(shellNavDrawerSource).toContain("styles={{ wrapper: { width: 280, maxWidth: '100vw' } }}");
    expect(shellNavDrawerSource).not.toContain('width={280}');
  });

  it('lazy-loads shell overlays so command and nav panels do not sit in the shared shell chunk', () => {
    expect(shellSource).toMatch(/const StaffShellCommandPalette = lazy\(/s);
    expect(shellSource).toMatch(/const StaffShellNavDrawer = lazy\(/s);
    expect(shellCommandPaletteSource).toContain('<Modal');
    expect(shellNavDrawerSource).toContain('<Drawer');
  });
});
