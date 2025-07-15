ROLE: You are an expert full-stack developer specializing in secure, scalable web applications using the LEMP/LAMP stack.

PROJECT GOAL: Create a complete "AI Licence Manager" web application based on the detailed specifications provided. The application must be secure, fast, and maintainable.

CORE TECHNOLOGY STACK:
- Backend: PHP 8.2+ with the Laravel 11 framework.
- Frontend: Vite, Tailwind CSS 3, Alpine.js for interactivity.
- Database: MySQL 8.0.
- UI Components: Use SweetAlert2 for all notifications and confirmations.

DATABASE SCHEMA:
Implement the exact database schema provided below. Create the necessary Laravel migrations and models with relationships (hasMany, belongsTo, etc.) defined.
[... Paste the full SQL schema from all phases here ...]

KEY FEATURES & LOGIC:
1.  **User System:** Implement a hierarchical user system using the 'role' and 'parent_id' columns. A user can only see and manage users and data underneath them in the hierarchy (e.g., a Vendor can see their Sub-Vendors but not other Vendors).
2.  **Authentication:** Use Laravel's built-in authentication (Breeze or Fortify) as a base. Ensure password reset functionality is secure.
3.  **Virtual Wallet:** Implement logic in the `UserController` and a new `TransactionController` to handle balance transfers between users when creating sub-accounts. Every monetary action must be logged in the `transactions` table.
4.  **License API (`/api/v1/license/verify`):** Create a secure API route. It must perform all validation checks: key existence, product match, status, expiry, and device limit based on the `license_activations` table. Use Laravel Sanctum for API token authentication for the clients that will call this endpoint.
5.  **Dashboard UI:** Design a clean dashboard using Tailwind CSS. All data widgets must load their data asynchronously using fetch() calls to dedicated API endpoints. For example, `GET /api/dashboard/stats`.
6.  **AJAX-driven UI:** The entire user-facing backend should feel like a single-page application. Use AJAX for all form submissions (creating users, products, licenses) to prevent full-page reloads. Return JSON responses and update the UI with JavaScript. For example, when a vendor creates a new product, send the form data via AJAX, and on success, dynamically add the new product to the table on the page without a refresh.
7.  **Blog:** Create a full CRUD system for posts and implement the public-facing like (IP-based) and comment (user-based) systems.
8.  **Logging:** Create a global `ActivityLogger` service that can be called from anywhere in the application to log important actions to the `activity_logs` table.

SECURITY MANDATES (CRITICAL):
- All routes must be protected by appropriate middleware (auth, role checks).
- Use Eloquent's query builder or PDO prepared statements exclusively to prevent SQL injection.
- Escape all data rendered in Blade templates using `{{ }}` to prevent XSS.
- Implement rate limiting on the login and API verification routes.
- Enforce HTTPS through middleware.

TASK BREAKDOWN:
1.  Set up a new Laravel 11 project.
2.  Install and configure Laravel Breeze, Tailwind CSS, and SweetAlert2.
3.  Create all database migrations and models based on the schema.
4.  Implement the user registration and login flow.
5.  Build the core dashboard layout with placeholder widgets.
6.  Implement the logic for the Owner to create Vendors.
7.  Implement the logic for Vendors to manage products.
8.  Build the license creation and management UI for Vendors.
9.  Develop the secure API endpoint for license verification.
10. Implement the virtual wallet and transaction logic for creating sub-accounts.
11. Build the full blog system with likes and comments.
12. Finally, write PHPUnit tests to cover critical functionality, especially the license verification API and the transaction logic.