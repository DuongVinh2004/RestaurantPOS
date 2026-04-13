import type { PropsWithChildren, ReactNode } from 'react';
import { ConfigProvider, theme, type ThemeConfig } from 'antd';
import viVN from 'antd/locale/vi_VN';
import { QueryClientProvider } from '@tanstack/react-query';
import { queryClient } from './query-client';

const appTheme: ThemeConfig = {
  algorithm: [theme.defaultAlgorithm, theme.compactAlgorithm],
  token: {
    colorPrimary: '#b8652c',
    borderRadius: 14,
    colorBgLayout: '#f6f1e8',
    colorBgContainer: '#fffbf5',
    colorBgElevated: '#fffbf5',
    colorBorder: '#ded2bf',
    colorText: '#201914',
    colorTextSecondary: '#5d5248',
    fontSize: 13,
  },
  components: {
    Layout: {
      siderBg: '#11161f',
      headerBg: '#f4efe8',
      bodyBg: '#f6f1e8',
    },
    Menu: {
      darkItemBg: '#11161f',
      darkSubMenuItemBg: '#11161f',
      darkItemSelectedBg: '#1f2c39',
    },
  },
};

const renderStaticFeedbackHolder = (children: ReactNode) => (
  <ConfigProvider locale={viVN} theme={appTheme}>
    {children}
  </ConfigProvider>
);

ConfigProvider.config({
  theme: appTheme,
  holderRender: renderStaticFeedbackHolder,
});

export function AppProviders({ children }: PropsWithChildren) {
  return (
    <ConfigProvider locale={viVN} theme={appTheme}>
      <QueryClientProvider client={queryClient}>
        {children}
      </QueryClientProvider>
    </ConfigProvider>
  );
}
