# Enterprise RBAC ERP SaaS API

A robust, enterprise-grade multi-tenant Role-Based Access Control (RBAC) API with a fully functional interactive Demo UI. Built on **Laravel 13**, this system is designed for complex SaaS products requiring strict data isolation, hierarchical roles, and module-based subscription management.

## ✨ Key Features & Architecture

### 🏢 Hierarchical Multi-Tenancy
- **Structure:** `Company` -> `Organization` -> `User`.
- **Isolation:** Every user, role, permission, and module subscription is strictly scoped to an `Organization` and its parent `Company`.
- **Context Middleware:** The `org.context` middleware resolves the `X-ORG-ID` header, ensuring users cannot access data across tenant boundaries.
- **Billing Awareness:** Features subscription modeling (`OrganizationPlan`) to automatically gate access based on active trials or payments.

### 🛡️ Advanced RBAC System
- **Super Admin Bypass:** Built-in absolute bypass for platform owners via the `is_super_admin` flag.
- **Role Hierarchies:** Infinite role inheritance (`parent_role_id`). Permissions propagate down the tree, allowing granular delegation.
- **Module Subscriptions:** Organizations subscribe to modules (HRM, Sales, Finance, etc.). Access is granted only if *both* the organization has the module and the user's role permits it.
- **Permission Overrides:** Supports both role-based permissions and direct user permission overrides (explicit grant/deny).

### 🧩 Dynamic Navigation & UI Sync
- **Intelligent Sidebar API:** Resolves the navigation tree in real-time by checking module availability, role permissions, and menu assignments.
- **Audit & Security:** Integrated audit logging (`audit_logs`), login tracking (`user_login_attempts`), and password history.

### 🏗️ Security First
- **UUID Masking:** Leverages the `HasUuidColumn` trait. Internal integer IDs are completely hidden; all public API interactions use UUIDs.
- **Safe Migrations:** All migrations are idempotent (using existence checks), allowing them to safely run on existing databases without data loss or "duplicate table" errors.

---

## 🚀 Live Demo UI

The repository includes a sleek, dark-mode Single Page Application (SPA) built with Vanilla CSS/JS to test the API architecture.

### To Access the Demo:
1. Start your local server: `php artisan serve`
2. Navigate to: **`http://localhost:8000/demo/index.html`**

**Test Accounts (Password: `Password@123`):**
- **Super Admin:** `superadmin@atilimited.net`
- **Org Admin:** `admin@atilimited.net`
- **Employee:** `employee@atilimited.net`

---

## 🛠️ Tech Stack & Requirements

- **PHP:** 8.2+
- **Framework:** Laravel 13
- **Database:** PostgreSQL (Optimized) / MySQL ready
- **Authentication:** Laravel Passport for OAuth2 Bearer Tokens
- **Frontend Demo:** Pure Vanilla CSS (Glassmorphism) + Vanilla JS

---

## 📦 Local Installation & Setup

1. **Clone & Install Dependencies**
   ```bash
   composer install
   ```

2. **Environment Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Install Passport Keys**
   ```bash
   php artisan passport:install --force
   php artisan passport:client --personal --name="ERP Personal Access Client"
   ```

4. **Database Migration &  **
   ```bash
   php artisan migrate:fresh --seed
   ```

---

## 📖 API Usage

### Authentication
**POST** `/api/v1/auth/login`
```json
{
    "email": "admin@atilimited.net",
    "password": "Password@123"
}
```

### Accessing Scoped Data
Most routes require the `X-ORG-ID` header (Organization UUID):
```http
GET /api/v1/sidebar
X-ORG-ID: 414e33dc-312a-4536-9fe6-bf42b15678fd
Authorization: Bearer <token>
```

---

## 📂 Project Structure

- `/app/Http/Controllers/Api/V1/*`: RESTful API Endpoints
- `/app/Http/Middleware/*`: Multi-tenancy and Permission gates
- `/app/Services/*`: Core business logic (`RBACService`, `SidebarService`)
- `/database/migrations/*`: Idempotent schema definitions
- `/public/demo/*`: Interactive test harness UI
