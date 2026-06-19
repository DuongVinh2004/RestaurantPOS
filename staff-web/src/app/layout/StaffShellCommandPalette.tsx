import { useMemo, useState, type KeyboardEventHandler } from 'react';
import { Input, Modal, Button, Alert } from 'antd';
import { parseCommand, validateCommand, COMMAND_REGISTRY } from '../../shared/command-palette';

type CommandItem = {
  group: string;
  key: string;
  label: string;
  path: string;
  subtitle: string;
};

export function StaffShellCommandPalette({
  activeIndex,
  groupedItems,
  items,
  open,
  query,
  onActivate,
  onClose,
  onInputKeyDown,
  onOpenPath,
  onQueryChange,
  onExecuteCommand,
}: {
  activeIndex: number;
  groupedItems: Array<[string, CommandItem[]]>;
  items: CommandItem[];
  open: boolean;
  query: string;
  onActivate: (index: number) => void;
  onClose: () => void;
  onInputKeyDown: KeyboardEventHandler<HTMLInputElement>;
  onOpenPath: (path: string) => void;
  onQueryChange: (value: string) => void;
  onExecuteCommand?: (command: string, args: Record<string, string | number | boolean>) => void;
}) {
  const parsedCommand = useMemo(() => parseCommand(query), [query]);
  const validation = useMemo(() => parsedCommand ? validateCommand(parsedCommand) : null, [parsedCommand]);
  const definition = parsedCommand ? COMMAND_REGISTRY[parsedCommand.command] : null;

  const isActionCommand = query.trim().startsWith('/');
  const [confirming, setConfirming] = useState(false);

  function handleExecute() {
    if (validation?.valid && parsedCommand) {
      if (definition?.isDestructive && !confirming) {
        setConfirming(true);
        return;
      }
      onExecuteCommand?.(parsedCommand.command, validation.resolvedArgs);
      setConfirming(false);
    }
  }
  return (
    <Modal
      open={open}
      title="Đi nhanh hoặc tiếp tục việc đang dở"
      footer={null}
      className="staff-shell-command-modal"
      onCancel={onClose}
    >
      <div className="staff-shell-command-modal-body">
        <Input
          autoFocus
          allowClear
          placeholder="Tìm workspace, flow đang dở, hoặc việc đã ghim"
          value={query}
          onChange={(event) => onQueryChange(event.target.value)}
          onKeyDown={(e) => {
            if (isActionCommand && e.key === 'Enter') {
              e.preventDefault();
              handleExecute();
            } else {
              onInputKeyDown(e);
            }
          }}
        />

        {isActionCommand ? (
          <div className="staff-shell-command-action-view" style={{ marginTop: 16 }}>
            {parsedCommand && definition ? (
              <div className="staff-shell-command-preview">
                <h3 style={{ marginBottom: 8, fontWeight: 600 }}>{definition.description}</h3>
                <div style={{ marginBottom: 16, fontFamily: 'monospace', background: '#f1f5f9', padding: 8, borderRadius: 6 }}>
                  <strong>{parsedCommand.command}</strong>{' '}
                  {definition.expectedArgs.map(arg => (
                    <span key={arg.name} style={{ color: validation?.resolvedArgs[arg.name] !== undefined ? '#16a34a' : '#9ca3af' }}>
                      &lt;{arg.name}&gt;{' '}
                    </span>
                  ))}
                </div>
                
                {validation && !validation.valid && (
                  <Alert type="error" message="Lỗi tham số" description={
                    <ul style={{ paddingLeft: 20, margin: 0 }}>
                      {validation.errors.map((err, i) => <li key={i}>{err}</li>)}
                    </ul>
                  } showIcon style={{ marginBottom: 16 }} />
                )}

                {confirming ? (
                  <Alert 
                    type="warning" 
                    message="Xác nhận thao tác" 
                    description="Thao tác này sẽ thay đổi dữ liệu hoặc có rủi ro cao. Bạn có chắc chắn muốn tiếp tục?"
                    showIcon 
                    action={
                      <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
                        <Button size="small" onClick={() => setConfirming(false)}>Hủy</Button>
                        <Button size="small" type="primary" danger onClick={handleExecute}>Thực hiện</Button>
                      </div>
                    }
                  />
                ) : (
                  <Button 
                    type="primary" 
                    disabled={!validation?.valid} 
                    onClick={handleExecute}
                    danger={definition.isDestructive}
                  >
                    Thực thi lệnh (Enter)
                  </Button>
                )}
              </div>
            ) : (
              <Alert type="info" message="Nhập lệnh..." description={
                <ul style={{ paddingLeft: 20, margin: 0 }}>
                  {Object.values(COMMAND_REGISTRY).map(def => (
                    <li key={def.name}><strong>{def.name}</strong> - {def.description}</li>
                  ))}
                </ul>
              } />
            )}
          </div>
        ) : (
          <div className="staff-shell-command-list">
          {groupedItems.map(([groupLabel, groupedCommandItems]) => (
            <div key={groupLabel} className="staff-shell-command-section">
              <span className="staff-shell-command-section-label">{groupLabel}</span>
              <div className="staff-shell-command-section-items" role="listbox" aria-label={groupLabel}>
                {groupedCommandItems.map((item) => {
                  const itemIndex = items.findIndex((candidate) => candidate.key === item.key);
                  const isActive = itemIndex === activeIndex;

                  return (
                    <button
                      key={item.key}
                      type="button"
                      className={`staff-shell-command-item ${isActive ? 'staff-shell-command-item-active' : ''}`}
                      onMouseEnter={() => onActivate(itemIndex)}
                      onClick={() => onOpenPath(item.path)}
                    >
                      <span className="staff-shell-command-group">{item.group}</span>
                      <span className="staff-shell-command-title">{item.label}</span>
                      <span className="staff-shell-command-subtitle">{item.subtitle}</span>
                    </button>
                  );
                })}
              </div>
            </div>
          ))}

          {items.length === 0 ? (
            <div className="staff-shell-command-empty">
              <p>Không tìm thấy workspace hoặc flow phù hợp với từ khóa hiện tại.</p>
            </div>
          ) : null}
        </div>
        )}

        <p className="staff-shell-command-hint" style={{ marginTop: 16 }}>
          Dùng <kbd>Ctrl</kbd> + <kbd>K</kbd> để mở nhanh command palette.
        </p>
      </div>
    </Modal>
  );
}

