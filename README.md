# RBAC ERP SaaS API

A robust, enterprise-grade multi-tenant Role-Based Access Control (RBAC) API with a fully functional interactive Demo UI. Built entirely on **Laravel 13** and tailored for modern SaaS products.

## ✨ Key Features & Architecture

### 🏢 True Multi-Tenancy (Organization Context)
- **Isolation:** Every user, role, permission, and module subscription is strictly scoped to an `Organization`.
- **Context Middleware:** The custom `X-ORG-ID` header enforces access control via the `OrganizationContext` middleware. Users cannot access data across tenants.
- **Billing Awareness:** Features subscription modeling (`OrganizationPlan`) to automatically gate access when trials or payments expire.

### 🛡️ Global Access Control (GAC) & Granular RBAC
- **Super Admin Bypass:** Built-in absolute bypass capability for platform owners (accessible via the `is_super_admin` flag on the `users` table). 
- **Role Hierarchies:** Roles support infinite `parent_role_id` inheritance. An `Org Admin` role passes its permissions down the tree if configured.
- **Module Subscriptions:** Roles are assigned modules. The API serves modules precisely based on the intersection of what the exact *Organization* has paid for and what the *User's Role* grants.
- **Permission Caching:** A highly optimized `RBACService` aggregates hierarchy-inherited permissions and direct user overrides, caching the final resolved map locally per-user per-org.

### 🧩 Dynamic Navigation Ecosystem
- **Intelligent Sidebar API:** The `SidebarService` resolves the navigation tree in real-time. It checks:
  1. Does the Org have the Module?
  2. Does the User's Role have access to that Module?
  3. Are the specific Menus granted to the Role?
  4. Does the User have the required `permission_id` to view deep Submenus?
- Fully automates the frontend rendering of layouts so the client-side remains completely dumb and secure.

### 🏗️ Complete UUID First Implementation
- Security-first approach leveraging the `HasUuidColumn` trait. Internal integer IDs are completely masked from the public REST API payloads in favor of `uuid` parameters. Implicit route binding intercepts and casts UUIDs directly to Models.

---

## 🚀 Live Demo UI

The repository ships with an incredibly sleek, dark-mode native HTML/CSS/JS Single Page Application frontend built strictly to test this exact architecture. 

It implements interactive token-based Authentication, Topbar Organization switching, seamless API REST requests, dynamic Grid module layouts, and JSON API response debugging.

### To Access the Demo:
Run your local dev server:
```bash
php artisan serve
```
Then navigate to: **`http://localhost:8000/demo/index.html`**

**Test Accounts Provided (Password is `Password@123` for all):**
- `superadmin@erp.test` *(Full GAC View)*
- `admin@demo-corp.test` *(Org Admin View)*
- `employee@demo-corp.test` *(Restricted Employee View)*

---

## 🛠️ Tech Stack & Requirements

- **PHP:** 8.2 or higher
- **Framework:** Laravel 13 
- **Database:** SQLite (dev) / MySQL 8.0+ ready
- **Authentication:** Laravel Passport (`v12+` / `v13`) for OAuth2 Bearer Tokens
- **Frontend Demo:** Pure Vanilla CSS (Glassmorphism & Orbs) + Vanilla JS state management

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
*Note: Ensure your `DB_CONNECTION` is mapped properly (to either MySQL or the provided SQLite database file).*

3. **Install Passport Clients & Keys**
Because Passport handles OAuth, generate the cryptographic keys and a Personal Access Client.
```bash
php artisan passport:install --force
php artisan passport:client --personal --name="ERP Personal Access Client"
```
*(Add the returned Client ID and Secret to `.env` if prompted by your Passport version)*

4. **Fresh Migration & Complete Seeding**
Deploy the complete architecture with the deterministic seeders (Creates the Demo Corp, Users, Roles, Modules, Menus, Permissions).
```bash
php artisan migrate:fresh --seed
```

## 📖 Key Directories

- `/app/Http/Controllers/Api/V1/*` : RESTful endpoints
- `/app/Http/Middleware/*` : Org, Billing, and Permission gates
- `/app/Models/Traits/*` : Architecture traits (like UUID behavior)
- `/app/Services/*` : Heavy business logic isolating permissions (`RBACService`, `SidebarService`)
- `/public/demo/*` : The interactive test harness UI
