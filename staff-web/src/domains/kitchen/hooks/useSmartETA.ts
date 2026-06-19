import { useQuery } from '@tanstack/react-query';
import type { KitchenOrderItemTicket } from '../../../shared/api/sdk';
import { getKitchenSmartETA } from '../../../shared/api/staff-api';

export type SmartETAConfidence = 'high' | 'medium' | 'low';

export type SmartETA = {
  estimatedMinutes: number;
  confidence: SmartETAConfidence;
  reason: string;
};

export function useSmartETA(ticket: KitchenOrderItemTicket | null): SmartETA | null {
  const itemId = ticket?.item?.item_id;

  const query = useQuery({
    queryKey: ['smart-eta', itemId],
    queryFn: async () => {
      if (!itemId) return null;
      const res = await getKitchenSmartETA([itemId]);
      return res.data[itemId] || null;
    },
    enabled: !!itemId,
    staleTime: 5 * 60 * 1000, // 5 minutes cache
  });

  if (!ticket || !itemId) {
    return null;
  }

  if (query.data) {
    return query.data;
  }

  // Fallback to heuristic while loading or on error
  const itemName = (ticket.item?.name || '').toLowerCase();
  
  if (itemName.includes('nướng') || itemName.includes('hấp') || itemName.includes('lẩu') || itemName.includes('hầm') || itemName.includes('steak')) {
    return {
      estimatedMinutes: 20,
      confidence: 'medium',
      reason: 'Đang tải (Món cần nhiệt lâu)',
    };
  }

  if (itemName.includes('xào') || itemName.includes('chiên') || itemName.includes('salad') || itemName.includes('gỏi') || itemName.includes('súp')) {
    return {
      estimatedMinutes: 10,
      confidence: 'low',
      reason: 'Đang tải (Chế biến nhanh)',
    };
  }

  if (itemName.includes('trà') || itemName.includes('cà phê') || itemName.includes('nước') || itemName.includes('sinh tố') || itemName.includes('bia')) {
    return {
      estimatedMinutes: 3,
      confidence: 'low',
      reason: 'Đang tải (Đồ uống)',
    };
  }

  return {
    estimatedMinutes: 12,
    confidence: 'low',
    reason: 'Đang tải (Trung bình)',
  };
}
