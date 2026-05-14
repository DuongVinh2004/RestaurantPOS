import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const currentDir = dirname(fileURLToPath(import.meta.url));
const dashboardPreviewSource = readFileSync(resolve(currentDir, '../../../dev/dashboard-preview.tsx'), 'utf8');
const indexCss = readFileSync(resolve(currentDir, '../../index.css'), 'utf8');
const mainSource = readFileSync(resolve(currentDir, '../../main.tsx'), 'utf8');
const tokensCss = readFileSync(resolve(currentDir, '../../styles/tokens.css'), 'utf8');
const themeSource = readFileSync(resolve(currentDir, '../../styles/theme.ts'), 'utf8');
const uiOverridesCss = readFileSync(resolve(currentDir, '../../styles/ui-overrides.css'), 'utf8');
const providersSource = readFileSync(resolve(currentDir, '../providers/AppProviders.tsx'), 'utf8');
const adminShellSource = readFileSync(resolve(currentDir, './AdminShell.tsx'), 'utf8');
const appFrameNavigationSource = readFileSync(resolve(currentDir, './frame/AppFrameNavigation.tsx'), 'utf8');
const kitchenShellSource = readFileSync(resolve(currentDir, './KitchenShell.tsx'), 'utf8');
const opsShellSource = readFileSync(resolve(currentDir, './OpsShell.tsx'), 'utf8');
const shellCommandPaletteSource = readFileSync(resolve(currentDir, './StaffShellCommandPalette.tsx'), 'utf8');
const shellNavDrawerSource = readFileSync(resolve(currentDir, './StaffShellNavDrawer.tsx'), 'utf8');
const shellSource = readFileSync(resolve(currentDir, './StaffAppShell.tsx'), 'utf8');
const shellContextSource = readFileSync(resolve(currentDir, './useStaffShellContext.ts'), 'utf8');
const workspaceSwitcherSource = readFileSync(resolve(currentDir, './StaffWorkspaceSwitcher.tsx'), 'utf8');

describe('StaffAppShell layout styles', () => {
  it('keeps the shared staff-web theme in a readable light operational palette after bundle overrides load', () => {
    expect(tokensCss).toMatch(/color-scheme:\s*light;/s);
    expect(tokensCss).toMatch(/--staff-bg-page:\s*#f6f8fb;/s);
    expect(tokensCss).toMatch(/--staff-text-primary:\s*#111827;/s);
    expect(tokensCss).toMatch(/--staff-text-secondary:\s*#4b5563;/s);
    expect(tokensCss).toMatch(/--staff-text-tertiary:\s*#6b7280;/s);
    expect(mainSource).toMatch(/import '\.\/styles\/design-bundle-overrides\.css'[\s\S]*import '\.\/styles\/tokens\.css'[\s\S]*import '\.\/styles\/ui-overrides\.css'/);
    expect(dashboardPreviewSource).toMatch(/import '\.\.\/src\/index\.css'[\s\S]*import '\.\.\/src\/styles\/design-bundle-overrides\.css'[\s\S]*import '\.\.\/src\/styles\/tokens\.css'[\s\S]*import '\.\.\/src\/styles\/ui-overrides\.css'/);
    expect(providersSource).toContain('staffAntTheme');
    expect(themeSource).toMatch(/algorithm:\s*\[theme\.defaultAlgorithm,\s*theme\.compactAlgorithm\]/s);
    expect(themeSource).not.toContain('theme.darkAlgorithm');
    expect(themeSource).toMatch(/colorPrimary:\s*staffThemeTokens\.primary/s);
    expect(themeSource).toMatch(/colorBgContainer:\s*staffThemeTokens\.surface1/s);
    expect(themeSource).toMatch(/colorTextSecondary:\s*staffThemeTokens\.textSecondary/s);
    expect(uiOverridesCss).toContain('Readable light surface');
    expect(uiOverridesCss).toMatch(/\.staff-shell-layout,\s*\.staff-shell-layout > \.ant-layout,\s*\.staff-shell-content,\s*\.staff-shell-content-dashboard,\s*\.staff-dashboard-page\s*\{[^}]*var\(--staff-bg-page-accent\)[\s\S]*var\(--staff-bg-page\)[\s\S]*!important;/s);
    expect(uiOverridesCss).toMatch(/\.ant-btn-primary\s*\{[^}]*background:\s*#2563eb\s*!important;[^}]*border-color:\s*#2563eb\s*!important;/s);
    expect(uiOverridesCss).toMatch(/\.ant-table-wrapper \.ant-table-tbody > tr\.ant-table-row:hover > td[\s\S]*background:\s*#f5f9fd\s*!important;/s);
    expect(uiOverridesCss).toContain('color: #1d4ed8 !important;');
  });

  it('keeps the shell header flexible for multi-line route context', () => {
    expect(indexCss).toMatch(/\.staff-shell-header\s*\{[^}]*height:\s*auto;[^}]*overflow:\s*visible;[^}]*padding:\s*16px 20px;/s);
    expect(indexCss).toMatch(/\.staff-shell-header-top\s*\{[^}]*height:\s*auto;[^}]*min-height:\s*0;[^}]*width:\s*100%;/s);
    expect(indexCss).not.toMatch(/\.staff-shell-header-dashboard\s*\{/s);
  });

  it('keeps the active shell header compact instead of stacking large context cards', () => {
    expect(uiOverridesCss).toMatch(/\.staff-shell-header\s*\{[^}]*padding:\s*8px 16px;/s);
    expect(uiOverridesCss).toMatch(/\.staff-shell-header-top\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)\s*auto;/s);
    expect(uiOverridesCss).toMatch(/\.staff-shell-workspace-kicker\s*\{[^}]*border-radius:\s*999px;[^}]*font-size:\s*11px;/s);
    expect(uiOverridesCss).toMatch(/\.staff-shell-header-context\s*\{[^}]*display:\s*none;/s);
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
    expect(appFrameNavigationSource).toContain('<nav className="staff-shell-menu"');
    expect(workspaceSwitcherSource).toContain('<select');
    expect(uiOverridesCss).toContain('.staff-shell-select-wrap::after');
    expect(uiOverridesCss).toContain('.staff-shell-control-button');
  });

  it('routes the shared entrypoint through workspace shells on top of shared frame primitives', () => {
    expect(shellSource).toContain('OpsShell');
    expect(shellSource).toContain('KitchenShell');
    expect(shellSource).toContain('AdminShell');
    expect(shellSource).toContain('AppFrameNavigation');
    expect(opsShellSource).toContain('<AppFrame');
    expect(kitchenShellSource).toContain('<AppFrame');
    expect(adminShellSource).toContain('<AppFrame');
  });

  it('keeps the sidebar brand terse and improves navigation legibility', () => {
    expect(uiOverridesCss).toMatch(/\.staff-sider-brand-copy\s*\{[^}]*display:\s*none !important;/s);
    expect(uiOverridesCss).toMatch(/\.staff-shell-sider[\s\S]*linear-gradient\(180deg,\s*#ffffff\s*0%,\s*#edf3fa\s*100%\)/s);
    expect(uiOverridesCss).toMatch(/\.staff-shell-nav-item\s*\{[^}]*gap:\s*10px;[^}]*min-height:\s*38px;[^}]*padding:\s*8px 10px;/s);
    expect(uiOverridesCss).toMatch(/\.staff-shell-nav-item-selected[\s\S]*border-color:\s*rgba\(37,\s*99,\s*235,\s*0\.34\)/s);
    expect(uiOverridesCss).toMatch(/\.staff-nav-item-icon\s*\{[^}]*flex:\s*0 0 18px;[^}]*width:\s*18px;/s);
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
