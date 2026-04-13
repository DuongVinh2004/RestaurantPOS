type ConfirmActionOptions = {
  title: string;
  content: string;
  okText?: string;
  cancelText?: string;
  danger?: boolean;
};

let modalModulePromise: Promise<typeof import('antd/es/modal')> | null = null;

async function loadModalApi() {
  return (await (modalModulePromise ??= import('antd/es/modal'))).default;
}

export function useConfirmAction() {
  return async (options: ConfirmActionOptions) => {
    try {
      const modalApi = await loadModalApi();

      return await new Promise<boolean>((resolve) => {
        const instance = modalApi.confirm({
          title: options.title,
          content: options.content,
          okText: options.okText ?? 'Xác nhận',
          cancelText: options.cancelText ?? 'Hủy',
          okButtonProps: options.danger ? { danger: true } : undefined,
          centered: true,
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
    } catch (error) {
      console.error('Failed to load confirm modal.', error);
      return false;
    }
  };
}
