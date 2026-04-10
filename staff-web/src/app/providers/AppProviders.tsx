import type { PropsWithChildren } from 'react';
import { App as AntdApp, ConfigProvider, theme } from 'antd';
import viVN from 'antd/locale/vi_VN';
import { QueryClientProvider } from '@tanstack/react-query';
import { queryClient } from './query-client';

export function AppProviders({ children }: PropsWithChildren) {
  return (
    <ConfigProvider
      locale={viVN}
      theme={{
        algorithm: theme.compactAlgorithm,
        token: {
          colorPrimary: '#b8652c',
          borderRadius: 10,
          colorBgLayout: '#f5f5f2',
          colorBgContainer: '#ffffff',
          colorText: '#1f1f1f',
          colorTextSecondary: '#5b5b57',
          fontSize: 13,
        },
        components: {
          Layout: {
            siderBg: '#101828',
            headerBg: '#ffffff',
            bodyBg: '#f5f5f2',
          },
          Menu: {
            darkItemBg: '#101828',
            darkSubMenuItemBg: '#101828',
            darkItemSelectedBg: '#b8652c',
          },
        },
      }}
    >
      <AntdApp>
        <QueryClientProvider client={queryClient}>
          {children}
        </QueryClientProvider>
      </AntdApp>
    </ConfigProvider>
  );
}
