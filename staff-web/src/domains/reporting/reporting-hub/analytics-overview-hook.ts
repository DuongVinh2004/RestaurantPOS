import { useQuery } from '@tanstack/react-query';
import { getStaffToken } from '../../../shared/api/client';

export interface AnalyticsOverviewData {
  overview: {
    total_reservations: number;
    cancelled_count: number;
    no_show_count: number;
    total_revenue: number;
  };
  payment_summary: Array<{ method: string; amount: number }>;
  revenue_heatmap: Array<{ period: string; revenue: number }>;
  top_items: Array<{ name: string; quantity: number }>;
}

export interface AnalyticsOverviewEnvelope {
  data: AnalyticsOverviewData;
  meta: any;
}

export function useAnalyticsOverview(dateFrom?: string, dateTo?: string) {
  return useQuery({
    queryKey: ['analytics-overview', dateFrom, dateTo],
    queryFn: async (): Promise<AnalyticsOverviewData> => {
      const token = getStaffToken();
      if (!token) throw new Error('Unauthorized');
      
      const queryParams = new URLSearchParams();
      if (dateFrom) queryParams.append('date_from', dateFrom);
      if (dateTo) queryParams.append('date_to', dateTo);

      const url = `/api/v1/staff/reporting/analytics-overview?${queryParams.toString()}`;
      
      const res = await fetch(url, {
        headers: {
          'X-Staff-Key': token,
          'Accept': 'application/json',
        },
      });

      if (!res.ok) {
        throw new Error('Failed to fetch analytics overview');
      }

      const envelope: AnalyticsOverviewEnvelope = await res.json();
      return envelope.data;
    },
  });
}
