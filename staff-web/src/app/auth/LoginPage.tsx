import { useEffect, useMemo, useState } from 'react';
import { Alert, Button, Card, Form, Input, Space, Typography } from 'antd';
import { useNavigate } from 'react-router-dom';
import { useAuthStore, defaultPathForSession } from '../store/auth-store';
import { appTitle } from '../../shared/config/env';
import { formatApiError } from '../../shared/api/errors';

type LoginFormValues = {
  identifier: string;
  password: string;
  deviceName: string;
};

export function LoginPage() {
  const navigate = useNavigate();
  const session = useAuthStore((state) => state.session);
  const notice = useAuthStore((state) => state.notice);
  const clearNotice = useAuthStore((state) => state.clearNotice);
  const login = useAuthStore((state) => state.login);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const initialValues = useMemo<LoginFormValues>(() => ({
    identifier: '',
    password: '',
    deviceName: 'Máy phục vụ',
  }), []);

  async function handleFinish(values: LoginFormValues) {
    setSubmitting(true);
    setError(null);
    clearNotice();

    try {
      const nextSession = await login(values);
      navigate(defaultPathForSession(nextSession), { replace: true });
    } catch (cause) {
      setError(formatApiError(cause, 'Không thể đăng nhập phiên nhân viên.'));
    } finally {
      setSubmitting(false);
    }
  }

  useEffect(() => {
    if (session) {
      navigate(defaultPathForSession(session), { replace: true });
    }
  }, [navigate, session]);

  if (session) {
    return null;
  }

  return (
    <div className="staff-auth-screen">
      <Card className="staff-auth-card">
        <Space orientation="vertical" size={20} style={{ width: '100%' }}>
          <div>
            <Typography.Text className="staff-eyebrow">Đăng nhập nhân viên</Typography.Text>
            <Typography.Title level={2} style={{ marginTop: 8, marginBottom: 8 }}>
              {appTitle}
            </Typography.Title>
            <Typography.Paragraph type="secondary" style={{ marginBottom: 0 }}>
              Đăng nhập bằng phiên nhân viên hiện có. Màn hình này tập trung vào luồng cốt lõi:
              đăng nhập, sơ đồ bàn, đặt bàn, đơn hàng, bếp và thanh toán.
            </Typography.Paragraph>
          </div>

          {notice ? <Alert type={notice.tone === 'success' ? 'success' : notice.tone === 'warning' ? 'warning' : 'error'} showIcon title={notice.message} /> : null}
          {error ? <Alert type="error" showIcon title={error} /> : null}

          <Form<LoginFormValues> layout="vertical" initialValues={initialValues} onFinish={handleFinish}>
            <Form.Item name="identifier" label="Tài khoản / email / số điện thoại" rules={[{ required: true, message: 'Nhập định danh nhân viên.' }]}>
              <Input placeholder="Ví dụ: host01 hoặc staff@example.com" autoComplete="username" />
            </Form.Item>
            <Form.Item name="password" label="Mật khẩu" rules={[{ required: true, message: 'Nhập mật khẩu.' }]}>
              <Input.Password placeholder="Nhập mật khẩu" autoComplete="current-password" />
            </Form.Item>
            <Form.Item name="deviceName" label="Tên thiết bị" rules={[{ required: true, message: 'Đặt tên thiết bị cho phiên nhân viên.' }]}>
              <Input placeholder="Ví dụ: Máy phục vụ tầng trệt" />
            </Form.Item>
            <Button type="primary" htmlType="submit" loading={submitting} block>
              Đăng nhập
            </Button>
          </Form>
        </Space>
      </Card>
    </div>
  );
}
