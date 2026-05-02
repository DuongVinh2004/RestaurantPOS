import type { PropsWithChildren, ReactNode } from 'react';
import { ConfigProvider } from 'antd';
import viVN from 'antd/locale/vi_VN';
import { QueryClientProvider } from '@tanstack/react-query';
import { queryClient } from './query-client';
import { staffAntTheme } from '../../styles/theme';

const renderStaticFeedbackHolder = (children: ReactNode) => (
  <ConfigProvider locale={viVN} theme={staffAntTheme}>
    {children}
  </ConfigProvider>
);

ConfigProvider.config({
  theme: staffAntTheme,
  holderRender: renderStaticFeedbackHolder,
});

export function AppProviders({ children }: PropsWithChildren) {
  return (
    <ConfigProvider locale={viVN} theme={staffAntTheme}>
      <QueryClientProvider client={queryClient}>
        {children}
      </QueryClientProvider>
    </ConfigProvider>
  );
}
