import { Button, Card, Col, Input, InputNumber, Row, Space, Switch, Typography } from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, useMemo } from 'react';
import {
  createAdminMenuModifierGroup,
  deleteAdminMenuModifierGroup,
  listAdminMenuModifierGroups,
  updateAdminMenuModifierGroup,
  type AdminMenuModifierGroup,
  type AdminMenuModifier,
} from '../../../../../shared/api/staff-api';
import { ApiStateBlock } from '../../../../../shared/ui/states/StateBlocks';
import { formatApiError } from '../../../../../shared/api/errors';
import { toast } from '../../../../../shared/ui/feedback/toast';

export function AdminModifierGroupsPanel() {
  const queryClient = useQueryClient();
  const [search, setSearch] = useState('');
  const [editingGroup, setEditingGroup] = useState<AdminMenuModifierGroup | null>(null);
  const [formState, setFormState] = useState<{
    name: string;
    description: string;
    minSelections: number;
    maxSelections: number;
    isActive: boolean;
    modifiers: Array<{ modifier_id?: number; name: string; price_adjustment: number }>;
  }>({
    name: '',
    description: '',
    minSelections: 0,
    maxSelections: 1,
    isActive: true,
    modifiers: [],
  });

  const groupsQuery = useQuery({
    queryKey: ['admin-modifier-groups', search],
    queryFn: () => listAdminMenuModifierGroups({ q: search, per_page: 100 }),
  });

  const groups = useMemo(() => groupsQuery.data?.data ?? [], [groupsQuery.data?.data]);

  const saveMutation = useMutation({
    mutationFn: () => {
      if (formState.name.trim() === '') {
        throw new Error('Tên nhóm không được để trống.');
      }
      if (formState.modifiers.length === 0) {
        throw new Error('Cần ít nhất 1 tùy chọn.');
      }
      const payload = {
        name: formState.name,
        description: formState.description,
        min_selections: formState.minSelections,
        max_selections: formState.maxSelections,
        is_active: formState.isActive,
        modifiers: formState.modifiers.map((m, i) => ({
          modifier_id: m.modifier_id,
          name: m.name,
          price_adjustment: m.price_adjustment,
          sort_order: i,
        })),
      };

      if (editingGroup) {
        return updateAdminMenuModifierGroup(editingGroup.group_id, payload);
      }
      return createAdminMenuModifierGroup(payload);
    },
    onSuccess: () => {
      toast.success('Đã lưu nhóm tùy chọn.');
      setEditingGroup(null);
      setFormState({ name: '', description: '', minSelections: 0, maxSelections: 1, isActive: true, modifiers: [] });
      void queryClient.invalidateQueries({ queryKey: ['admin-modifier-groups'] });
    },
    onError: (err) => {
      toast.error(formatApiError(err, 'Chưa thể lưu nhóm tùy chọn.'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (groupId: number) => deleteAdminMenuModifierGroup(groupId),
    onSuccess: () => {
      toast.success('Đã xóa nhóm tùy chọn.');
      if (editingGroup) {
        setEditingGroup(null);
        setFormState({ name: '', description: '', minSelections: 0, maxSelections: 1, isActive: true, modifiers: [] });
      }
      void queryClient.invalidateQueries({ queryKey: ['admin-modifier-groups'] });
    },
    onError: (err) => {
      toast.error(formatApiError(err, 'Chưa thể xóa nhóm tùy chọn.'));
    },
  });

  function startEdit(group: AdminMenuModifierGroup) {
    setEditingGroup(group);
    setFormState({
      name: group.name,
      description: group.description || '',
      minSelections: group.min_selections,
      maxSelections: group.max_selections,
      isActive: group.is_active,
      modifiers: group.modifiers?.map((m) => ({
        modifier_id: m.modifier_id,
        name: m.name,
        price_adjustment: m.price_adjustment,
      })) ?? [],
    });
  }

  function cancelEdit() {
    setEditingGroup(null);
    setFormState({ name: '', description: '', minSelections: 0, maxSelections: 1, isActive: true, modifiers: [] });
  }

  function addModifier() {
    setFormState({
      ...formState,
      modifiers: [...formState.modifiers, { name: '', price_adjustment: 0 }],
    });
  }

  function updateModifier(index: number, updates: { name?: string; price_adjustment?: number }) {
    const newMods = [...formState.modifiers];
    newMods[index] = { ...newMods[index], ...updates };
    setFormState({ ...formState, modifiers: newMods });
  }

  function removeModifier(index: number) {
    const newMods = [...formState.modifiers];
    newMods.splice(index, 1);
    setFormState({ ...formState, modifiers: newMods });
  }

  return (
    <Card title="Quản lý Nhóm Tùy Chọn Món (Modifier Groups)" className="staff-workspace-detail-card">
      <Row gutter={16}>
        <Col xs={24} md={12}>
          <Space direction="vertical" style={{ width: '100%' }}>
            <Input.Search placeholder="Tìm nhóm..." value={search} onChange={(e) => setSearch(e.target.value)} />
            <ApiStateBlock error={groupsQuery.error} onRetry={() => void groupsQuery.refetch()} fallback="Lỗi tải nhóm tùy chọn" />
            <div style={{ maxHeight: '400px', overflowY: 'auto', paddingRight: '8px' }}>
              {groups.map((group) => (
                <Card
                  key={group.group_id}
                  size="small"
                  style={{ marginBottom: '8px', cursor: 'pointer', borderColor: editingGroup?.group_id === group.group_id ? '#1677ff' : undefined }}
                  onClick={() => startEdit(group)}
                >
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Typography.Text strong>{group.name}</Typography.Text>
                    {!group.is_active && <Typography.Text type="danger">Vô hiệu</Typography.Text>}
                  </div>
                  <Typography.Text type="secondary" style={{ fontSize: '12px' }}>
                    Chọn từ {group.min_selections} đến {group.max_selections} tùy chọn. ({group.modifiers?.length ?? 0} tùy chọn)
                  </Typography.Text>
                </Card>
              ))}
            </div>
          </Space>
        </Col>

        <Col xs={24} md={12}>
          <Card size="small" title={editingGroup ? `Sửa nhóm: ${editingGroup.name}` : 'Tạo nhóm mới'}>
            <Space direction="vertical" style={{ width: '100%' }}>
              <Input
                placeholder="Tên nhóm (vd: Kích cỡ, Topping)"
                value={formState.name}
                onChange={(e) => setFormState({ ...formState, name: e.target.value })}
              />
              <Input.TextArea
                placeholder="Mô tả..."
                value={formState.description}
                onChange={(e) => setFormState({ ...formState, description: e.target.value })}
              />
              <Row gutter={8}>
                <Col span={12}>
                  Lựa chọn tối thiểu: <InputNumber min={0} value={formState.minSelections} onChange={(val) => setFormState({ ...formState, minSelections: val || 0 })} style={{ width: '100%' }} />
                </Col>
                <Col span={12}>
                  Lựa chọn tối đa: <InputNumber min={1} value={formState.maxSelections} onChange={(val) => setFormState({ ...formState, maxSelections: val || 1 })} style={{ width: '100%' }} />
                </Col>
              </Row>
              <Space>
                <Switch checked={formState.isActive} onChange={(val) => setFormState({ ...formState, isActive: val })} />
                <Typography.Text>Đang hoạt động</Typography.Text>
              </Space>

              <Typography.Title level={5} style={{ marginTop: '16px' }}>Các Tùy Chọn (Modifiers)</Typography.Title>
              {formState.modifiers.map((mod, idx) => (
                <Row gutter={8} key={idx} style={{ marginBottom: '8px' }}>
                  <Col span={12}>
                    <Input placeholder="Tên (vd: Lớn, Ít đá)" value={mod.name} onChange={(e) => updateModifier(idx, { name: e.target.value })} />
                  </Col>
                  <Col span={8}>
                    <InputNumber prefix="₫" min={0} value={mod.price_adjustment} onChange={(val) => updateModifier(idx, { price_adjustment: val || 0 })} style={{ width: '100%' }} />
                  </Col>
                  <Col span={4}>
                    <Button danger onClick={() => removeModifier(idx)}>Xóa</Button>
                  </Col>
                </Row>
              ))}
              <Button type="dashed" onClick={addModifier} style={{ width: '100%' }}>+ Thêm tùy chọn</Button>

              <Space style={{ marginTop: '16px', display: 'flex', justifyContent: 'flex-end' }}>
                {editingGroup && (
                  <Button danger type="text" onClick={() => {
                    if (window.confirm('Chắc chắn xóa nhóm này?')) {
                      deleteMutation.mutate(editingGroup.group_id);
                    }
                  }}>
                    Xóa nhóm
                  </Button>
                )}
                {editingGroup && <Button onClick={cancelEdit}>Hủy</Button>}
                <Button type="primary" loading={saveMutation.isPending} onClick={() => saveMutation.mutate()}>
                  {editingGroup ? 'Lưu cập nhật' : 'Tạo nhóm'}
                </Button>
              </Space>
            </Space>
          </Card>
        </Col>
      </Row>
    </Card>
  );
}
