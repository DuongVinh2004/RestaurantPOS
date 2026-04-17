const TOAST_CONFIG = {
  duration: 2.4,
  maxCount: 4,
  top: 88,
};

type ToastTone = 'success' | 'error' | 'warning' | 'info';
type ToastLoadingTone = ToastTone | 'loading';
type ToastOptions = {
  duration?: number;
  key?: string;
};

let messageModulePromise: Promise<typeof import('antd/es/message')> | null = null;
let messageConfigured = false;

async function loadMessageApi() {
  const messageApi = (await (messageModulePromise ??= import('antd/es/message'))).default;

  if (!messageConfigured) {
    messageApi.config(TOAST_CONFIG);
    messageConfigured = true;
  }

  return messageApi;
}

function openToast(tone: ToastLoadingTone, content: string, options: ToastOptions = {}) {
  void loadMessageApi()
    .then((messageApi) => {
      messageApi.open({
        type: tone,
        content,
        key: options.key,
        duration: options.duration ?? (tone === 'loading' ? 0 : undefined),
      });
    })
    .catch((error) => {
      console.error(`Failed to open ${tone} toast.`, error);
    });
}

export const toast = {
  success(content: string, options?: ToastOptions) {
    openToast('success', content, options);
  },
  error(content: string, options?: ToastOptions) {
    openToast('error', content, options);
  },
  warning(content: string, options?: ToastOptions) {
    openToast('warning', content, options);
  },
  info(content: string, options?: ToastOptions) {
    openToast('info', content, options);
  },
  loading(content: string, options?: ToastOptions) {
    openToast('loading', content, options);
  },
  destroy(key?: string) {
    void loadMessageApi()
      .then((messageApi) => {
        messageApi.destroy(key);
      })
      .catch((error) => {
        console.error('Failed to destroy toast.', error);
      });
  },
};
