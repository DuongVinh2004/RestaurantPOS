## 4. Chay project moi ngay

Moi lan mo project de dev/test local, chi can 3 tien trinh bat buoc va 1 tien trinh tuy chon.

### 4.1. Terminal 1: Redis

Chocolatey package Redis tren may Windows nay khong tu tao service. Cach chay on dinh la mo foreground process trong cua so rieng:

```cmd
start "Redis" cmd /k "cd /d C:\ProgramData\chocolatey\lib\redis\tools && redis-server.exe redis.conf"
```

Kiem tra Redis:

```cmd
redis-cli ping
```

Ket qua dung:

```text
PONG
```

### 4.2. Terminal 2: Laravel app

```cmd
php artisan serve
```

### 4.3. Terminal 3: Scheduler

```cmd
php artisan schedule:work
```

Scheduler la bat buoc cho local neu ban muon business flow dong:

- reservation expiry
- waiting-list expiry
- hold expiry
- reminders
- outbox processing
- reporting freshness

### 4.4. Terminal 4: Frontend assets, neu can

Neu ban co dung giao dien frontend:

```cmd
npm run dev
```

Neu chi test API thi co the bo qua buoc nay.

---

**Xem thêm:** [Hướng dẫn chi tiết cho Windows VSCode](docs/runbooks/booking-local-windows-vscode-cmd-runbook.md)
