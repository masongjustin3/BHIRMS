# Barangay Health Information and Record Management System (BHIRMS)

A PHP 8.1+ / MySQL CRUD application based on the nine-entity ERD, with additional patient-session and transaction tracking.

## Step-by-step XAMPP installation

1. Download and install XAMPP. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Extract the **BHIRMS** folder to `C:\xampp\htdocs\BHIRMS` (Windows).
3. Open `http://localhost/phpmyadmin`.
4. Open the **Import** tab, choose `C:\xampp\htdocs\BHIRMS\database.sql`, and click **Import**. This creates the `bhirms` database and application tables.
5. Open `config.php`. For a default local XAMPP setup, MySQL username is `root` and password is blank. Otherwise, set your actual database credentials.
6. Visit `http://localhost/BHIRMS/setup.php`, create the **first administrator account**, and choose a strong password.
7. **Delete `setup.php`** after the first administrator account is created.
8. Open `http://localhost/BHIRMS/login.php` and sign in.
9. Staff can create a standard BHW account from the **Create account** link on the login page. Administrators can add midwife accounts and manage user roles in **Users**. Create records in this order: **Households → Patients → Visits / Immunizations / Appointments → Medicines → Medicine Dispensing**.
10. Test the dashboard and **Reports**. The print option can save a PDF, and CSV exports can be opened with Excel.

## Included modules

- Dashboard for today's visit counts, vaccine due dates, low stock, and expiring medicines
- Household CRUD and patient CRUD, with patient search
- Visit history and immunization entry, with due/overdue warnings
- Patient sessions with assigned staff, session type, outcome, status, and notes
- Transactions for services, medicine dispensing, and restocking, including payment status and inventory updates
- Medicine inventory; dispensing deducts quantity and deleting a dispensing record restores stock
- Appointments (Scheduled / Completed / Cancelled)
- Admin-only user management and audit logs
- Profile management, monthly statistics, CSV exports, print-to-PDF reports

## Important implementation notes

- The database includes the nine tables specified in the ERD plus `sessions` and `transactions`. An application code table named `medicine_dispensed` represents the ERD's MedicineDispensed entity, and `activity_logs` represents ActivityLogs.
- For an existing installation, import `database.sql` again to create the new tables. Its `CREATE TABLE IF NOT EXISTS` statements preserve existing records.
- The **immunization reminder is an in-app alert** based on `next_due_date`. An external SMS provider is **not** included; real text messages require a separate SMS API, consent and secure credentials.
- The ERD stores vaccination records with `next_due_date`. It does not model an independent vaccine-dose schedule or a "completed" flag, so the dashboard treats each due-date value as outstanding even if the next dose has since been recorded. For actual clinical deployment, extend the schema and use the official local immunization schedule.
- The dashboard uses a **low-stock threshold of 10** units. Adjust this in `index.php` as needed.
- Access to patient data is available to signed-in staff; only user management/audit logs are restricted to administrators. A real deployment needs finer-grained permissions and approval from the health center.
- Public self-registration creates a BHW account with access to signed-in staff features. Disable registration or add administrator approval before deploying with real patient data.
- Deleting parent records is intentionally restricted by foreign keys rather than cascading through patient/health records. Exported CSVs contain sensitive health data; treat them as confidential.
- This is a **local academic prototype**, not a production-certified healthcare records system. Before storing real patient data, add HTTPS, encrypted backups, secure hosting, granular access permissions, privacy notices, data retention policies, backup/restore testing, and applicable Data Privacy Act compliance.
- **Backups:** use phpMyAdmin → select `bhirms` → **Export** → SQL → Go. This is a manual database backup; there is no automated backup scheduler.

## Troubleshooting

- **Database not ready**: make sure MySQL is running and import `database.sql`.
- **Access denied for user root**: update `config.php` to match your MySQL credentials.
- **404 Not Found**: confirm your extracted folder is `htdocs\BHIRMS` and Apache is running.
- **Cannot delete record**: foreign keys prevent removal of patients, users, and other records that still have dependent records.
