import React from 'react';
import {
  PieChart,
  Pie,
  Cell,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from 'recharts';

interface DashboardChartsProps {
  tableBoard: any;
  kitchenStations: any;
}

const COLORS = ['#10b981', '#f59e0b', '#ef4444', '#6b7280']; // Emerald, Amber, Red, Gray

export function DashboardCharts({ tableBoard, kitchenStations }: DashboardChartsProps) {
  // 1. Process Table Board data for the Donut Chart
  const tableStats = React.useMemo(() => {
    let available = 0;
    let occupied = 0;
    let dirty = 0;
    let other = 0;

    if (tableBoard && tableBoard.data) {
      tableBoard.data.forEach((table: any) => {
        const status = table.realtime_status || table.board_state;
        if (status === 'Available') available++;
        else if (status === 'Occupied') occupied++;
        else if (status === 'Dirty') dirty++;
        else other++;
      });
    }

    return [
      { name: 'Trống', value: available },
      { name: 'Đang phục vụ', value: occupied },
      { name: 'Chờ dọn', value: dirty },
      { name: 'Khác', value: other },
    ].filter((item) => item.value > 0);
  }, [tableBoard]);

  // 2. Real data for Bar Chart from Kitchen Stations
  const orderStats = React.useMemo(() => {
    if (!kitchenStations || !kitchenStations.data) {
      return [];
    }
    return kitchenStations.data.map((station: any) => ({
      name: station.name,
      'Mới': station.ticket_counts?.queued || 0,
      'Đang nấu': station.ticket_counts?.fired || 0,
      'Xong': station.ticket_counts?.ready || 0,
    }));
  }, [kitchenStations]);

  return (
    <div className="dashboard-charts-grid" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '24px', marginTop: '24px' }}>
      {/* Chart 1: Table Status */}
      <div className="chart-card" style={{ background: '#fff', padding: '16px', borderRadius: '8px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
        <h3 style={{ fontSize: '16px', fontWeight: 600, marginBottom: '16px', color: '#374151' }}>
          Tình trạng Bàn
        </h3>
        <div style={{ height: '300px' }}>
          <ResponsiveContainer width="100%" height="100%">
            <PieChart>
              <Pie
                data={tableStats}
                cx="50%"
                cy="50%"
                innerRadius={60}
                outerRadius={80}
                paddingAngle={5}
                dataKey="value"
                label={({ name, percent }) => `${name} ${((percent ?? 0) * 100).toFixed(0)}%`}
              >
                {tableStats.map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                ))}
              </Pie>
              <Tooltip />
              <Legend />
            </PieChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Chart 2: Kitchen Load */}
      <div className="chart-card" style={{ background: '#fff', padding: '16px', borderRadius: '8px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
        <h3 style={{ fontSize: '16px', fontWeight: 600, marginBottom: '16px', color: '#374151' }}>
          Tải Bếp (Minh hoạ)
        </h3>
        <div style={{ height: '300px' }}>
          <ResponsiveContainer width="100%" height="100%">
            <BarChart
              data={orderStats}
              margin={{ top: 20, right: 30, left: 20, bottom: 5 }}
            >
              <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e5e7eb" />
              <XAxis dataKey="name" axisLine={false} tickLine={false} />
              <YAxis axisLine={false} tickLine={false} />
              <Tooltip cursor={{ fill: '#f3f4f6' }} />
              <Legend />
              <Bar dataKey="Mới" stackId="a" fill="#ef4444" radius={[0, 0, 4, 4]} />
              <Bar dataKey="Đang nấu" stackId="a" fill="#f59e0b" />
              <Bar dataKey="Xong" stackId="a" fill="#10b981" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>
    </div>
  );
}
