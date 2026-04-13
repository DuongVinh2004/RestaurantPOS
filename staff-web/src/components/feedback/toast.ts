const TOAST_CONFIG = {
  duration: 2.4,
  maxCount: 4,
  top: 88,
};

type ToastTone = 'success' | 'error' | 'warning' | 'info';

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

function openToast(tone: ToastTone, content: string) {
  void loadMessageApi()
    .then((messageApi) => {
      messageApi[tone](content);
    })
    .catch((error) => {
      console.error(`Failed to open ${tone} toast.`, error);
    });
}

export const toast = {
  success(content: string) {
    openToast('success', content);
  },
  error(content: string) {
    openToast('error', content);
  },
  warning(content: string) {
    openToast('warning', content);
  },
  info(content: string) {
    openToast('info', content);
  },
};
