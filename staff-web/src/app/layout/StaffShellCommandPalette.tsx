import type { KeyboardEventHandler } from 'react';
import { Input, Modal } from 'antd';

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
}) {
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
          onKeyDown={onInputKeyDown}
        />

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

        <p className="staff-shell-command-hint">
          Dùng <kbd>Ctrl</kbd> + <kbd>K</kbd> để mở nhanh command palette.
        </p>
      </div>
    </Modal>
  );
}

