import { useEffect, useState } from 'react';
import { usePresenceContext, type PresenceUser } from './PresenceContext';

export function usePresence(channel: string | null): PresenceUser[] {
  const { subscribe, unsubscribe, getMembers } = usePresenceContext();
  const [members, setMembers] = useState<PresenceUser[]>([]);

  useEffect(() => {
    if (!channel) {
      setMembers([]);
      return;
    }

    subscribe(channel);
    
    // Polling to simulate reactive updates
    const interval = setInterval(() => {
      setMembers([...getMembers(channel)]);
    }, 1000);

    return () => {
      clearInterval(interval);
      unsubscribe(channel);
    };
  }, [channel, subscribe, unsubscribe, getMembers]);

  return members;
}
