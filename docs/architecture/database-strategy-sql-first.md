# SQL-First Database Strategy

RestaurantPOS employs an explicit SQL-first database strategy rather than relying on Laravel's standard ORM migration engine (`php artisan migrate`) for production bootstrapping.

## Why SQL-First?

This approach was chosen as a deliberate engineering decision to bridge the gap between application developers and production DBAs.
- **Predictability**: Complex databases in a live environment (like a busy restaurant POS) require highly tuned indices, specific column types, and sometimes triggers or views that ORM abstraction layers struggle to represent cleanly.
- **Production Alignment**: The exact SQL applied locally is the exact SQL that will be run in production, reducing "it worked on my machine" errors related to database constraints.
- **Drift Prevention**: It forces explicit awareness of schema changes rather than hiding them behind PHP classes.

*Note: This is not because "migrations are bad", but because a strict SQL contract provides higher guarantees for this specific operational environment.*

## Components of the SQL Contract

### Canonical Schema (`database/schema/mysql-schema.sql`)
This is the single source of truth for the baseline database structure. It represents the "day zero" state of the application.

### SQL Patches (`database/patches/*.sql`)
Instead of PHP migration files, incremental changes are written as raw SQL patches. These are applied in lexical order. They represent "day one and beyond" state transitions.

### Combined State (`db_all.sql`)
A compiled snapshot of the current state. This allows for fast visual inspection of the entire schema without having to mentally compute the baseline plus all patches.

### Bootstrap Tooling (`tools/mysql/bootstrap_release.php`)
This tooling replaces `php artisan migrate`. Running `composer bootstrap:booking` executes this script, which safely provisions the database, loads the canonical schema, applies the patches in order, and seeds reference data. **`php artisan migrate` is not the default bootstrap path.**

## Drift Control

### Risks of Artifact/Schema Drift
If a developer changes database-sensitive behavior in the PHP application (e.g., assuming a new column exists) but forgets to update the SQL contract, the application will crash in production because the release tooling will deploy the old schema.

### How to Verify Drift
Drift is caught locally and in CI through our verification gates:
- Run `composer bootstrap:booking` to ensure the local database is fresh.
- Run `php artisan booking:doctor` and `php artisan booking:deploy-check` to assert that the database matches expected constraints.
- Test suites run against this explicitly provisioned MySQL instance, catching mismatches.

### What to Update When Schema-Sensitive Behavior Changes
Whenever you introduce a schema change (e.g., adding a table, altering a column), you MUST:
1. Create a new patch in `database/patches/`.
2. Update the canonical `database/schema/mysql-schema.sql` (if required by your workflow conventions).
3. Update the `db_all.sql` snapshot.
4. Run `composer bootstrap:booking` to verify the patch applies cleanly.

---

## Interview Explanation

If asked about this architecture in an interview, here is how to explain it concisely:

**English Version:**
"We chose an explicit SQL-first database strategy over standard ORM migrations because we treat the database schema as a strict contract with the production environment. By using raw SQL patches and a canonical schema dump, we ensure the exact database structure applied locally is identical to what runs in production. This eliminates abstraction leaks from the ORM and allows us to easily use advanced database features like triggers or highly tuned indices that are critical for a high-concurrency POS environment."

**Vietnamese Version:**
"Chúng tôi đã chọn chiến lược SQL-first một cách rõ ràng thay vì sử dụng ORM migrations tiêu chuẩn bởi vì chúng tôi coi schema cơ sở dữ liệu là một hợp đồng nghiêm ngặt đối với môi trường production. Bằng cách sử dụng các file patch SQL thuần và một file canonical schema, chúng tôi đảm bảo rằng cấu trúc cơ sở dữ liệu chạy ở local hoàn toàn giống với production. Điều này loại bỏ các lỗi do sự trừu tượng hóa của ORM và cho phép chúng tôi dễ dàng sử dụng các tính năng nâng cao của database như trigger hoặc các index được tinh chỉnh vốn rất quan trọng cho một hệ thống POS đòi hỏi tính đồng thời cao."
