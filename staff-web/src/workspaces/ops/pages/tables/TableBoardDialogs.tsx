import { useEffect } from 'react';
import { Button, Form, Input, InputNumber, Modal, Select, Space, Typography } from 'antd';
import { ReservationCreateModal } from '../reservations/ReservationCreateModal';
import {
  buildDefaultReservationCreateFormValues,
  type ReservationCreateFormValues,
} from '../../../../domains/reservations/reservation-create';
import {
  DEFAULT_WALK_IN_FORM_VALUES,
  type MoveTableFormValues,
  type WalkInFormValues,
} from '../../../../domains/tables/table-board-forms';

type MoveTableOption = {
  label: string;
  value: number;
};

export function TableBoardDialogs({
  moveTableOpen,
  moveTableReservationCode,
  moveTableSourceCode,
  moveTableSubmitting,
  moveTableTargetOptions,
  onMoveTableCancel,
  onMoveTableSubmit,
  onPhoneReservationCancel,
  onPhoneReservationSubmit,
  onWalkInCancel,
  onWalkInSubmit,
  onWalkInValuesChange,
  phoneReservationOpen,
  phoneReservationSubmitting,
  selectedTableCode,
  walkInDraft,
  walkInOpen,
  walkInSubmitting,
}: {
  moveTableOpen: boolean;
  moveTableReservationCode: string | null;
  moveTableSourceCode: string | null;
  moveTableSubmitting: boolean;
  moveTableTargetOptions: Array<MoveTableOption>;
  onMoveTableCancel: () => void;
  onMoveTableSubmit: (values: MoveTableFormValues) => void;
  onPhoneReservationCancel: () => void;
  onPhoneReservationSubmit: (values: ReservationCreateFormValues) => void;
  onWalkInCancel: () => void;
  onWalkInSubmit: (values: WalkInFormValues) => void;
  onWalkInValuesChange: (values: Partial<WalkInFormValues>) => void;
  phoneReservationOpen: boolean;
  phoneReservationSubmitting: boolean;
  selectedTableCode: string | null;
  walkInDraft: Partial<WalkInFormValues>;
  walkInOpen: boolean;
  walkInSubmitting: boolean;
}) {
  const [walkInForm] = Form.useForm<WalkInFormValues>();
  const [phoneReservationForm] = Form.useForm<ReservationCreateFormValues>();
  const [moveTableForm] = Form.useForm<MoveTableFormValues>();
  const selectedMoveTargetId = Form.useWatch('to_table_id', moveTableForm);
  const selectedMoveTargetLabel = moveTableTargetOptions.find((option) => option.value === selectedMoveTargetId)?.label
    ?? 'Chưa chọn bàn đích';

  useEffect(() => {
    if (!walkInOpen) {
      return;
    }

    walkInForm.resetFields();
    walkInForm.setFieldsValue({
      ...DEFAULT_WALK_IN_FORM_VALUES,
      ...walkInDraft,
    });
  }, [walkInDraft, walkInForm, walkInOpen]);

  useEffect(() => {
    if (!phoneReservationOpen) {
      return;
    }

    phoneReservationForm.resetFields();
    phoneReservationForm.setFieldsValue(buildDefaultReservationCreateFormValues());
  }, [phoneReservationForm, phoneReservationOpen]);

  useEffect(() => {
    if (!moveTableOpen) {
      return;
    }

    moveTableForm.resetFields();
    moveTableForm.setFieldsValue({
      to_table_id: moveTableTargetOptions[0]?.value,
    });
  }, [moveTableForm, moveTableOpen, moveTableTargetOptions]);

  return (
    <>
      <Modal
        title={`Khach vang lai cho ${selectedTableCode ?? 'ban'}`}
        open={walkInOpen}
        onCancel={() => {
          onWalkInValuesChange(walkInForm.getFieldsValue());
          onWalkInCancel();
        }}
        footer={null}
        maskClosable={!walkInSubmitting}
        closable={!walkInSubmitting}
      >
        <Form<WalkInFormValues>
          form={walkInForm}
          layout="vertical"
          initialValues={DEFAULT_WALK_IN_FORM_VALUES}
          onValuesChange={(_, values) => onWalkInValuesChange(values)}
          onFinish={onWalkInSubmit}
        >
          <Form.Item name="guest_name" label="Ten khach" rules={[{ required: true, message: 'Nhap ten khach.' }]}>
            <Input placeholder="Khach vang lai" />
          </Form.Item>
          <Form.Item name="phone" label="So dien thoai">
            <Input placeholder="Khong bat buoc" />
          </Form.Item>
          <Form.Item name="guest_count" label="So khach" rules={[{ required: true, message: 'Nhap so khach.' }]}>
            <InputNumber min={1} max={30} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="service_minutes" label="So phut phuc vu">
            <InputNumber min={30} max={480} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="notes" label="Ghi chu">
            <Input.TextArea rows={3} placeholder="Ghi chu phuc vu neu can" />
          </Form.Item>
          <div className="staff-modal-footer">
            <Button
              onClick={() => {
                onWalkInValuesChange(walkInForm.getFieldsValue());
                onWalkInCancel();
              }}
              disabled={walkInSubmitting}
            >
              Luu nhap va dong
            </Button>
            <Button type="primary" htmlType="submit" loading={walkInSubmitting}>
              Xep khach vao ban
            </Button>
          </div>
        </Form>
      </Modal>

      <ReservationCreateModal
        open={phoneReservationOpen}
        title={`Dat ban ho cho ${selectedTableCode ?? 'ban'}`}
        description={`Dat ban moi se duoc gan truc tiep vao ${selectedTableCode ?? 'ban dang chon'} va luu guest snapshot cho khach goi dien.`}
        form={phoneReservationForm}
        lockedTableLabel={selectedTableCode}
        submitting={phoneReservationSubmitting}
        submitLabel="Tao dat ban ho"
        onCancel={onPhoneReservationCancel}
        onSubmit={onPhoneReservationSubmit}
      />

      <Modal
        title={`Chuyển bàn cho ${moveTableReservationCode ?? 'lượt phục vụ'}`}
        open={moveTableOpen}
        onCancel={moveTableSubmitting ? undefined : onMoveTableCancel}
        footer={null}
        maskClosable={!moveTableSubmitting}
        closable={!moveTableSubmitting}
      >
        <Space direction="vertical" size={12} style={{ width: '100%' }}>
          <Typography.Text type="secondary">
            Đây là thao tác rủi ro trên sơ đồ bàn. Hãy xác nhận lại bàn nguồn, bàn đích và reservation trước khi bấm chuyển.
          </Typography.Text>
          <div className="staff-mini-list">
            <div className="staff-mini-list-item">
              <Typography.Text strong>Reservation</Typography.Text>
              <Typography.Text type="secondary">{moveTableReservationCode ?? 'Chưa có mã đặt bàn'}</Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Bàn nguồn</Typography.Text>
              <Typography.Text type="secondary">{moveTableSourceCode ?? selectedTableCode ?? 'Chưa có bàn nguồn'}</Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Bàn đích đang chọn</Typography.Text>
              <Typography.Text type="secondary">{selectedMoveTargetLabel}</Typography.Text>
            </div>
          </div>
          <Form<MoveTableFormValues>
            form={moveTableForm}
            layout="vertical"
            onFinish={onMoveTableSubmit}
          >
            <Form.Item
              name="to_table_id"
              label="Bàn đích"
              rules={[{ required: true, message: 'Chọn bàn đích trước khi chuyển bàn.' }]}
            >
              <Select
                showSearch
                optionFilterProp="label"
                placeholder="Chọn bàn còn trống"
                options={moveTableTargetOptions}
              />
            </Form.Item>
            <div className="staff-modal-footer">
              <Button onClick={onMoveTableCancel} disabled={moveTableSubmitting}>
                Đóng
              </Button>
              <Button
                type="primary"
                htmlType="submit"
                loading={moveTableSubmitting}
                disabled={moveTableTargetOptions.length === 0}
              >
                Xác nhận chuyển bàn
              </Button>
            </div>
          </Form>
        </Space>
      </Modal>
    </>
  );
}
