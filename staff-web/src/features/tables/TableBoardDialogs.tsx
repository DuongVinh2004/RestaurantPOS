import { useEffect } from 'react';
import { Button, Form, Input, InputNumber, Modal, Select } from 'antd';
import { ReservationCreateModal } from '../reservations/ReservationCreateModal';
import {
  buildDefaultReservationCreateFormValues,
  type ReservationCreateFormValues,
} from '../reservations/reservation-create';
import {
  DEFAULT_WALK_IN_FORM_VALUES,
  type MoveTableFormValues,
  type WalkInFormValues,
} from './table-board-forms';

type MoveTableOption = {
  label: string;
  value: number;
};

export function TableBoardDialogs({
  moveTableOpen,
  moveTableReservationCode,
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
        title={`Khách vãng lai cho ${selectedTableCode ?? 'bàn'}`}
        open={walkInOpen}
        onCancel={() => {
          onWalkInValuesChange(walkInForm.getFieldsValue());
          onWalkInCancel();
        }}
        footer={null}
      >
        <Form<WalkInFormValues>
          form={walkInForm}
          layout="vertical"
          initialValues={DEFAULT_WALK_IN_FORM_VALUES}
          onValuesChange={(_, values) => onWalkInValuesChange(values)}
          onFinish={onWalkInSubmit}
        >
          <Form.Item name="guest_name" label="Tên khách" rules={[{ required: true, message: 'Nhập tên khách.' }]}>
            <Input placeholder="Khách vãng lai" />
          </Form.Item>
          <Form.Item name="phone" label="Số điện thoại">
            <Input placeholder="Không bắt buộc" />
          </Form.Item>
          <Form.Item name="guest_count" label="Số khách" rules={[{ required: true, message: 'Nhập số khách.' }]}>
            <InputNumber min={1} max={30} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="service_minutes" label="Số phút phục vụ">
            <InputNumber min={30} max={480} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="notes" label="Ghi chú">
            <Input.TextArea rows={3} placeholder="Ghi chú phục vụ nếu cần" />
          </Form.Item>
          <div className="staff-modal-footer">
            <Button
              onClick={() => {
                onWalkInValuesChange(walkInForm.getFieldsValue());
                onWalkInCancel();
              }}
            >
              Lưu nháp và đóng
            </Button>
            <Button type="primary" htmlType="submit" loading={walkInSubmitting}>
              Xếp khách vào bàn
            </Button>
          </div>
        </Form>
      </Modal>

      <ReservationCreateModal
        open={phoneReservationOpen}
        title={`Đặt bàn hộ cho ${selectedTableCode ?? 'bàn'}`}
        description={`Đặt bàn mới sẽ được gắn trực tiếp vào ${selectedTableCode ?? 'bàn đang chọn'} và lưu guest snapshot cho khách gọi điện.`}
        form={phoneReservationForm}
        lockedTableLabel={selectedTableCode}
        submitting={phoneReservationSubmitting}
        submitLabel="Tạo đặt bàn hộ"
        onCancel={onPhoneReservationCancel}
        onSubmit={onPhoneReservationSubmit}
      />

      <Modal
        title={`Chuyển bàn cho ${moveTableReservationCode ?? 'lượt phục vụ'}`}
        open={moveTableOpen}
        onCancel={onMoveTableCancel}
        footer={null}
      >
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
            <Button onClick={onMoveTableCancel}>
              Đóng
            </Button>
            <Button
              type="primary"
              htmlType="submit"
              loading={moveTableSubmitting}
              disabled={moveTableTargetOptions.length === 0}
            >
              Chuyển bàn
            </Button>
          </div>
        </Form>
      </Modal>
    </>
  );
}
