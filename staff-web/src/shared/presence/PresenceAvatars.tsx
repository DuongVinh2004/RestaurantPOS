import { Avatar, Tooltip } from 'antd';
import { usePresence } from './usePresence';

export function PresenceAvatars({ channel }: { channel: string | null }) {
  const members = usePresence(channel);

  if (!channel || members.length === 0) {
    return null;
  }

  return (
    <div style={{ display: 'flex', alignItems: 'center', marginLeft: 8 }}>
      <Avatar.Group size="small" max={{ count: 3 }}>
        {members.map((m) => (
          <Tooltip key={m.id} title={`${m.name} đang xem`}>
            <Avatar style={{ backgroundColor: m.color }}>
              {m.name.charAt(0).toUpperCase()}
            </Avatar>
          </Tooltip>
        ))}
      </Avatar.Group>
    </div>
  );
}
