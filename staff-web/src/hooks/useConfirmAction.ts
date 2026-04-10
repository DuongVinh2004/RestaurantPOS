import { App } from 'antd';

type ConfirmActionOptions = {
  title: string;
  content: string;
  okText?: string;
  cancelText?: string;
  danger?: boolean;
};

export function useConfirmAction() {
  const { modal } = App.useApp();

  return (options: ConfirmActionOptions) =>
    new Promise<boolean>((resolve) => {
      const instance = modal.confirm({
        title: options.title,
        content: options.content,
        okText: options.okText ?? 'Xác nhận',
        cancelText: options.cancelText ?? 'Hủy',
        okButtonProps: options.danger ? { danger: true } : undefined,
        onOk: () => {
          resolve(true);
          instance.destroy();
        },
        onCancel: () => {
          resolve(false);
          instance.destroy();
        },
      });
    });
}
