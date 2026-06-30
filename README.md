# MacJ Pest Control Management System

A full-stack PHP/MySQL web application for managing pest control operations, including appointment scheduling, job order processing, technician assignment, report submission, and client management.

## Project Structure

```
macj2/
├── root-level entry points and tools
│   ├── index.php                          # Redirects to landing page
│   ├── SignIn.php                         # User authentication
│   ├── SignUp.php                         # New user registration
│   ├── SignOut.php                        # Session logout
│   ├── terms.php                          # Terms and Conditions page
│   ├── db_config.php / db_connect.php     # Database connection config
│   ├── macj_pest_control.sql              # Database schema & seed data
│   ├── notification_functions.php         # Notification helpers
│   ├── get_notifications.php              # Fetch notifications API
│   ├── mark_notification_read.php         # Mark single notification read
│   ├── mark_notifications_read.php        # Bulk mark notifications read
│   ├── chemical_inventory_functions.php   # Chemical inventory logic
│   ├── chemical_display_functions.php     # Chemical display helpers
│   ├── [other fertility/update tools]
│   └── [root-level check_*.php]           # Admin debugging / DB structure tools
├── Admin Side/
│   ├── dashboard.php                      # Admin KPI dashboard
│   ├── clients.php                        # Client management
│   ├── calendar.php                       # Appointment calendar
│   ├── technicians.php                    # Technician management
│   ├── chemicals.php                      # Chemical inventory management
│   ├── assessment_report.php              # Assessment reporting
│   ├── job_order.php                      # Job order management
│   ├── assign_technician*.php             # Technician assignment workflows
│   ├── auto_assign_technician.php         # Automated technician assignment
│   ├── calculate_service_cost.php         # Cost calculation
│   ├── view_chemical_recommendations.php  # Chemical recommendations view
│   ├── upload_service_image.php           # Service image uploads
│   ├── delete_*.php                       # Delete operations (services, clients, etc.)
│   └── api_check_notifications.php        # Admin notification API
├── Technician Side/
│   ├── schedule.php                       # Technician schedule & job list
│   ├── inspection.php / inspection_fixed.php
│   ├── submit_job_order_report.php        # Submit job order report
│   ├── submit_report*.php                 # Report submission variants
│   ├── direct_submit.php                  # Direct report submission
│   ├── get_pest_checkboxes.php            # Fetch pest type options
│   ├── get_complete_job_data.php          # Fetch full job details
│   ├── check_php.php                      # PHP environment check
│   └── css/                               # Technician-specific styles
├── Client Side/
│   ├── schedule.php                       # Client appointment scheduling
│   ├── inspection_report.php              # View inspection reports
│   ├── job_order_report.php               # View job order reports
│   ├── contract.php                       # Service contract page
│   ├── profile.php                        # Client profile
│   ├── view_inspection_details.php        # Inspection detail view
│   ├── submit_joborder_feedback.php       # Feedback submission
│   ├── get_times.php, get_pest_checkboxes.php, get_available_technicians.php
│   └── includes/                          # Shared components
│       ├── job_order_procedure.php
│       ├── inspection_report_procedure.php
│       ├── contract_guide.php
│       └── common-footer.php
├── Landingpage/
│   └── landing_updated.php                # Public landing page
├── Database/
│   ├── userdb.php                         # User database helpers
│   └── connectdb.php                      # DB connection wrapper
├── FUNCTIONS/
│   └── MAIL/
│       └── SEND_EMAIL.php                 # Email notification sender
├── assets/
│   ├── default-profile.php / .html        # Fallback profile assets
├── css/                                   # Shared stylesheets
├── js/                                    # Shared JavaScript
├── logs/                                  # Application logs
├── uploads/                               # File uploads (services, admin, client)
│   ├── services/
│   ├── admin/
│   └── [client-named subdirectories]
└── docs/                                  # Reserved for additional documentation
```

## Features

### Admin (Office Staff)
- Dashboard with weekly sales, growth rate, and completed job metrics
- Client creation, viewing, and management
- Technician and availability management
- Chemical inventory management and usage tracking
- Appointment scheduling and calendar view
- Automated and manual technician assignment
- Assessment report creation and review
- Job order management
- Service cost calculation
- Notification center

### Technician
- Schedule view with upcoming appointments and job orders (sort by time)
- Inspection page with pest checklist and chemical recommendations
- Submit job order inspection reports
- View assigned tasks

### Client
- Book and manage appointments
- View job & inspection reports
- Review service contracts
- Manage profile
- Submit feedback on job orders

## Tech Stack

- **Backend:** PHP 8.x (procedural + PDO/MySQLi)
- **Database:** MariaDB / MySQL (macj_pest_control)
- **Frontend:** HTML, CSS, vanilla JavaScript, Bootstrap
- **Server:** XAMPP (Apache, PHP)

## Getting Started

### Prerequisites
- XAMPP (or equivalent Apache + PHP + MySQL stack)
- PHP 8.0+
- MySQL/MariaDB 10.4+

### Installation

1. Copy the `macj2` folder into your web root (e.g. `C:\xampp\htdocs\macj2` on Windows).
2. Start Apache and MySQL from the XAMPP control panel.
3. Import the database schema:
   ```sql
   mysql -u root < macj2/macj_pest_control.sql
   ```
   Or use phpMyAdmin at `http://localhost/phpmyadmin` to import `macj_pest_control.sql`.
4. Verify `db_config.php` and `db_connect.php` match your local DB credentials.

### Configuration

Database credentials are defined in `db_config.php`:
```php
$dbname = 'macj_pest_control';
$user   = 'root';
$pass   = '';
```

Some account features depend on email notifications configured through `FUNCTIONS/MAIL/SEND_EMAIL.php`.

## Usage

- Open `http://localhost/macj2` in your browser.
- Sign in with an admin, technician, or client account.

### User Roles

| Role | Redirect On Login |
|------|-------------------|
| `office_staff` | Admin Side dashboard |
| `technician` | Technician Side schedule |
| `client` | Client Side schedule |

## Database Schema

The project uses the database `macj_pest_control`. Key tables include:
- `appointments` — Client appointment records
- `appointment_technicians` — Technician assignments per appointment
- `assessment_report` — Inspection and assessment reports
- `job_order` — Job orders placed for clients
- `job_order_pest` — Pest types linked to job orders
- `chemical_inventory` / `chemical_usage` — Chemical stock and usage
- `technician_availability` / `pest_checkboxes` — Scheduling aids
- `users` — Auth and role-based access (see SQL dump for full schema)

Run the included `check_*.php` and `create_*.php` scripts at the project root if you need to verify or rebuild individual tables.

## Notes

- Root-level `check_*` and `fix_*` scripts exist for schema debugging and should not be exposed in production.
- Uploads are stored in `uploads/` and should be outside web root or protected if the app is deployed publicly.
- The notifications system relies on `notification_functions.php`, `get_notifications.php`, and related client-side templates.

## Documentation

The `docs/` folder is reserved for supplementary documentation. See the SQL file for the canonical reference on database structure.
