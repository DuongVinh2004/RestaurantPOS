import { Tag } from 'antd';
import { translateUiLabel } from '../../core/utils/translation';

type StatusTone = 'default' | 'processing' | 'success' | 'warning' | 'error';

export function StatusChip({
  label,
  tone = 'default',
}: {
  label: string;
  tone?: StatusTone;
}) {
  const color = mapToneToColor(tone);

  return (
    <Tag color={color} variant="filled" style={{ marginInlineEnd: 0 }}>
      {translateUiLabel(label)}
    </Tag>
  );
}

function mapToneToColor(tone: StatusTone): string {
  switch (tone) {
    case 'processing':
      return 'blue';
    case 'success':
      return 'green';
    case 'warning':
      return 'gold';
    case 'error':
      return 'red';
    default:
      return 'default';
  }
}
