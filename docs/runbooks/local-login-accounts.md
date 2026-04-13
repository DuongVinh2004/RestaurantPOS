# Local Login Accounts

Tam thoi ghi nhanh de dang dang nhap local.

Nguon du lieu hien tai:

- Manifest: `storage/app/uat/scenario-pack.json`
- Generated at UTC: `2026-04-10T01:05:30+00:00`
- Base URL: `http://127.0.0.1:8000`

## Dung ngay cho local/UAT

### Staff

- Username: `uat.staff`
- Password: `UatDemo!123`
- Role: `Staff`
- API key hien tai: `spk_nafn2q7nt6hghoktjnncxhubyx2cp0wankanxfqk0qv0mveb`

Ghi chu:

- Neu dang dang nhap bang staff auth login flow, dung `uat.staff / UatDemo!123`
- Neu goi thang staff API, dung header `X-Staff-Key: spk_nafn2q7nt6hghoktjnncxhubyx2cp0wankanxfqk0qv0mveb`

### Admin

- Username: `uat.admin`
- Password: `UatDemo!123`
- Role: `Admin`
- API key hien tai: `spk_us3w3qsjkibyygbori6wovon0wbzeibk7wmbordrpames8lm`

Ghi chu:

- Neu goi admin/staff API bang header, dung `X-Staff-Key`

### Customer

- Primary:
  - Username: `uat.customer.primary`
  - Password: `UatDemo!123`
  - Role: `Customer`
- Secondary:
  - Username: `uat.customer.secondary`
  - Password: `UatDemo!123`
  - Role: `Customer`

Ghi chu:

- Customer login flow su dung username/password, sau do nhan token de gui header `X-Customer-Token`

## Bootstrap mac dinh trong code

Day la tai khoan duoc tao boi `php artisan booking:bootstrap-site`, nhung khong co mat khau co dinh trong code:

- Admin bootstrap: `bootstrap-admin`
- Staff bootstrap: `bootstrap-staff`

Quan trong:

- `bootstrap-staff` chu yeu di bang staff API key, khong phai password co san
- Plaintext staff key chi xuat hien luc issue/rotate

Lenh huu ich:

```powershell
php artisan booking:bootstrap-site --json
php artisan staff-auth:api-keys:list --json
php artisan staff-auth:api-keys:issue <USER_ID> "Local staff key" --json
php artisan staff-auth:api-keys:rotate <STAFF_API_KEY_ID> --json
```

## Ghi nho nhanh

- Staff account ban dang hoi toi la: `uat.staff`
- Password local/UAT de dang nhap nhanh: `UatDemo!123`
- Neu web/app cua ban dang goi staff routes truc tiep, dung `X-Staff-Key` thay vi chi username/password
- File nay la ghi chu tam thoi cho local, co the drift neu ban bootstrap UAT pack lai va API key duoc rotate
