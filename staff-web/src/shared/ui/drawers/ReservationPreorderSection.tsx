import React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Card, Table, Typography, Tag, Button, Space, message } from 'antd';
import { 
  getStaffReservationPreorder, 
  confirmStaffReservationPreorder, 
  rejectStaffReservationPreorder,
  convertStaffReservationPreorder 
} from '../../api/staff-api';

const { Text } = Typography;

interface ReservationPreorderSectionProps {
  reservationId: number;
  reservationStatus: string;
  onConverted?: (orderId: number) => void;
}

export const ReservationPreorderSection: React.FC<ReservationPreorderSectionProps> = ({ 
  reservationId, 
  reservationStatus,
  onConverted 
}) => {
  const queryClient = useQueryClient();
  
  const { data, isLoading } = useQuery({
    queryKey: ['staff-reservation-preorder', reservationId],
    queryFn: () => getStaffReservationPreorder(reservationId),
  });

  const confirmMutation = useMutation({
    mutationFn: () => confirmStaffReservationPreorder(reservationId),
    onSuccess: () => {
      message.success('Đã xác nhận món đặt trước');
      queryClient.invalidateQueries({ queryKey: ['staff-reservation-preorder', reservationId] });
    },
    onError: () => {
      message.error('Không thể xác nhận món đặt trước');
    }
  });

  const rejectMutation = useMutation({
    mutationFn: () => rejectStaffReservationPreorder(reservationId),
    onSuccess: () => {
      message.success('Đã từ chối món đặt trước');
      queryClient.invalidateQueries({ queryKey: ['staff-reservation-preorder', reservationId] });
    },
    onError: () => {
      message.error('Không thể từ chối món đặt trước');
    }
  });

  const convertMutation = useMutation({
    mutationFn: () => convertStaffReservationPreorder(reservationId),
    onSuccess: (res) => {
      message.success('Đã chuyển thành order chính thức');
      queryClient.invalidateQueries({ queryKey: ['staff-reservation-preorder', reservationId] });
      if (onConverted && res.data.order_id) {
        onConverted(res.data.order_id);
      }
    },
    onError: () => {
      message.error('Không thể chuyển món đặt trước thành order');
    }
  });

  if (isLoading) return null;

  const preorder = data?.data?.pre_order;
  if (!preorder || !preorder.present) {
    return null;
  }

  const statusColors: Record<string, string> = {
    'draft': 'default',
    'submitted': 'blue',
    'confirmed': 'green',
    'rejected': 'red',
    'converted': 'purple',
  };

  const statusText: Record<string, string> = {
    'draft': 'Bản nháp',
    'submitted': 'Chờ xác nhận',
    'confirmed': 'Đã xác nhận',
    'rejected': 'Bị từ chối',
    'converted': 'Đã chuyển Order',
  };

  const columns = [
    {
      title: 'Món ăn',
      dataIndex: 'name',
      key: 'name',
    },
    {
      title: 'SL',
      dataIndex: 'quantity',
      key: 'quantity',
      width: 60,
    },
    {
      title: 'Giá',
      dataIndex: 'unit_price',
      key: 'unit_price',
      render: (val: string) => parseFloat(val).toLocaleString(),
    },
    {
      title: 'Thành tiền',
      dataIndex: 'line_total',
      key: 'line_total',
      render: (val: string) => parseFloat(val).toLocaleString(),
    },
  ];

  return (
    <Card 
      size="small" 
      title={<Space>
        <span>Món Đặt Trước</span>
        {preorder.order_status && (
          <Tag color={statusColors[preorder.order_status] || 'default'}>
            {statusText[preorder.order_status] || preorder.order_status}
          </Tag>
        )}
      </Space>}
      style={{ marginTop: 16 }}
    >
      <Table 
        dataSource={preorder.lines} 
        columns={columns} 
        rowKey="order_item_id"
        pagination={false}
        size="small"
        summary={() => (
          <Table.Summary.Row>
            <Table.Summary.Cell index={0} colSpan={3}><strong>Tổng cộng</strong></Table.Summary.Cell>
            <Table.Summary.Cell index={1}>
              <strong>{parseFloat(preorder.totals?.subtotal || '0').toLocaleString()}</strong>
            </Table.Summary.Cell>
          </Table.Summary.Row>
        )}
      />

      {preorder.order_status === 'submitted' && (
        <Space style={{ marginTop: 16, display: 'flex', justifyContent: 'flex-end' }}>
          <Button 
            danger 
            onClick={() => rejectMutation.mutate()} 
            loading={rejectMutation.isPending}
            disabled={confirmMutation.isPending}
          >
            Từ chối
          </Button>
          <Button 
            type="primary" 
            onClick={() => confirmMutation.mutate()} 
            loading={confirmMutation.isPending}
            disabled={rejectMutation.isPending}
          >
            Xác nhận
          </Button>
        </Space>
      )}

      {preorder.order_status === 'confirmed' && (
        <Space style={{ marginTop: 16, display: 'flex', justifyContent: 'flex-end' }}>
          <Button 
            type="primary" 
            onClick={() => convertMutation.mutate()} 
            loading={convertMutation.isPending}
            disabled={reservationStatus !== 'CheckedIn'}
            title={reservationStatus !== 'CheckedIn' ? 'Chỉ có thể chuyển thành order khi khách đã check-in' : ''}
          >
            Chuyển thành Order
          </Button>
        </Space>
      )}
    </Card>
  );
};
