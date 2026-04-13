import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const currentDir = dirname(fileURLToPath(import.meta.url));
const pageSource = readFileSync(resolve(currentDir, './TableBoardPage.tsx'), 'utf8');
const dialogsSource = readFileSync(resolve(currentDir, './TableBoardDialogs.tsx'), 'utf8');
const assignmentTablesSource = readFileSync(resolve(currentDir, './TableBoardAssignmentTables.tsx'), 'utf8');

describe('TableBoardPage bundle guards', () => {
  it('moves heavy dialogs and table surfaces behind lazy boundaries', () => {
    expect(pageSource).toContain("const TableBoardDialogs = lazy(");
    expect(pageSource).toContain("const TableBoardUnassignedReservationsTable = lazy(");
    expect(pageSource).toContain("const TableBoardCandidateReservationsTable = lazy(");
    expect(pageSource).toContain('<Suspense fallback={<InlineLoading tip="Đang tải danh sách đặt bàn chưa gán..." />}>');
    expect(pageSource).toContain('<Suspense fallback={<InlineLoading tip="Đang tải gợi ý gán cho bàn này..." />}>');
    expect(pageSource).not.toContain('  Form,');
    expect(pageSource).not.toContain('  Input,');
    expect(pageSource).not.toContain('  InputNumber,');
    expect(pageSource).not.toContain('  Modal,');
    expect(pageSource).not.toContain('  Select,');
    expect(pageSource).not.toContain('  Table,');
    expect(dialogsSource).toContain('Form.useForm');
    expect(dialogsSource).toContain('<Modal');
    expect(assignmentTablesSource).toContain("import { Button, Space, Typography } from 'antd';");
    expect(assignmentTablesSource).not.toContain('<Table');
    expect(assignmentTablesSource).toContain('staff-table-board-assignment-list');
  });
});
