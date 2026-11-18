# IT Asset Management Backend

This README is the frontend integration guide for the backend HTTP API. The API is JSON over REST, with the exception of the asset CSV report and file upload/download endpoints.

## Run locally

Requirements: Docker Desktop with Docker Compose enabled.

From this directory, prepare the environment and start the application:

```powershell
Copy-Item .env.example .env
docker compose build app
docker compose run --rm --no-deps app php artisan key:generate
docker compose up
```

The API base URL is `http://localhost:8000/api`. To load demo users and records:

```powershell
docker compose exec app php artisan db:seed
```

Demo accounts use password `password`: `admin@example.com` (Super Admin), `itadmin@example.com` (IT Admin), `itstaff@example.com` (IT Staff), `manager@example.com` (Department Manager), `employee@example.com` (Employee), and `management@example.com` (Management). These are for local development only; change or remove them before deployment.

## Frontend connection

Send JSON requests with `Accept: application/json` and `Content-Type: application/json`. Except for `POST /login`, include the personal access token returned by login:

```http
Authorization: Bearer <token>
```

Login example:

```http
POST /api/login
Accept: application/json
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "password"
}
```

Successful login returns a `token` and a `user` object with `id`, `name`, `email`, `employee_id`, and `roles`. Store the token using the frontend's approved secure storage approach, then send it as a bearer token. `GET /api/me` returns the authenticated user; `POST /api/logout` revokes the current token.

Example fetch helper:

```js
const API_BASE_URL = "http://localhost:8000/api";

async function apiRequest(path, { token, ...options } = {}) {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers: {
      Accept: "application/json",
      ...(options.body && !(options.body instanceof FormData)
        ? { "Content-Type": "application/json" }
        : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({}));
    throw Object.assign(new Error(error.message || `HTTP ${response.status}`), {
      status: response.status,
      errors: error.errors,
    });
  }

  return response.status === 204 ? null : response.json();
}
```

### CORS

The backend currently allows browser requests from `http://localhost:5173`. If the frontend uses a different origin, update `allowed_origins` in `config/cors.php` and redeploy. Credentials are enabled in CORS configuration.

### Common response behavior

- List endpoints return Laravel paginator JSON: `data`, `current_page`, `last_page`, `per_page`, `total`, and pagination links. Use the `page` query parameter to move through results.
- Asset lists contain 20 items per page; generic resource lists contain 50; inventory audits and notifications contain 30; activity logs and users contain 50.
- Validation failures return `422` with a `message` and an `errors` object keyed by field. Invalid login credentials also return `422`.
- Missing/invalid authentication returns `401`; insufficient role or permission returns `403`; missing resources return `404`.
- Logout, successful deletes, and password reset return `204 No Content`.
- Dates are returned as JSON date/datetime values. Treat IDs as identifiers, not array positions.

## Endpoint reference

All paths below are relative to `/api`. Routes other than `POST /login` require authentication. The endpoint inventory reflects routes registered in `routes/api.php`.

### Authentication and account

| Method | Path | Request | Response / notes |
| --- | --- | --- | --- |
| POST | `/login` | `{ "email": "...", "password": "..." }` | `{ "token": "...", "user": { ... } }` |
| GET | `/me` | — | Current user and role names |
| POST | `/logout` | — | `204`; revokes current token |
| GET | `/me/assets` | — | Assets assigned to the signed-in employee |

### Assets

| Method | Path | Request | Response / notes |
| --- | --- | --- | --- |
| GET | `/assets` | Query: `status`, `category_id`, `location_id`, `search`, `page` | Paginated assets with category, location, warranty, and current assignment |
| POST | `/assets` | Asset JSON | Created asset (`201`) |
| GET | `/assets/{asset}` | — | Asset detail with related records |
| PUT | `/assets/{asset}` | Asset fields to update | Updated asset; fields may be partial |
| DELETE | `/assets/{asset}` | — | `204` |
| POST | `/assets/{asset}/assign` | `employee_id` or `department_id`; optional `expected_return_at`, `handover_confirmed`, `notes` | Created assignment (`201`); assigning closes any active assignment |
| POST | `/assets/{asset}/return` | Optional `condition` (`good`, `fair`, `poor`, `damaged`) and `notes` | `{ "ok": true, "return": { ... } }`; `422` if there is no active assignment |

Asset list filters: `status` accepts `active`, `damaged`, `in_repair`, or `retired`; `search` matches name, asset tag, serial number, and model. Asset writes use fields such as `asset_tag`, `name`, `category_id`, `brand_id`, `supplier_id`, `purchase_id`, `model`, `serial_no`, `status`, `location_id`, `technician_id`, `purchase_date`, and `purchase_cost`. Refer to validation responses for required and invalid fields.

### Master data and maintenance

These resources use the generic CRUD controller. List responses are paginated (50 per page), and create/update responses return the resource object.

| Resource path | Methods | Notes |
| --- | --- | --- |
| `/employees` | GET, POST, PUT `/{id}`, DELETE `/{id}` | Employee directory |
| `/departments` | GET, POST, PUT `/{id}`, DELETE `/{id}` | Departments and managers |
| `/locations` | GET, POST, PUT `/{id}`, DELETE `/{id}` | Supports parent/child locations |
| `/categories` | GET, POST, PUT `/{id}`, DELETE `/{id}` | Asset categories |
| `/brands` | GET, POST, PUT `/{id}`, DELETE `/{id}` | Asset brands |
| `/suppliers` | GET, POST, PUT `/{id}`, DELETE `/{id}` | Supplier directory |
| `/purchases` | GET, POST, PUT `/{id}`, DELETE `/{id}` | Purchase records |
| `/warranties` | GET, POST, PUT `/{id}`, DELETE `/{id}` | Warranty records |
| `/maintenances` | GET, POST, PUT `/{id}`, DELETE `/{id}` | Maintenance records |

For generic resource updates, send only the fields being changed. For supported field names and accepted values, see the resource validators in `app/Http/Controllers/CrudController.php`.

### Attachments, notifications, and reports

| Method | Path | Request | Response / notes |
| --- | --- | --- | --- |
| POST | `/assets/{asset}/attachments` | `multipart/form-data`, file field named `file` | Upload an asset attachment |
| POST | `/purchases/{purchase}/attachments` | `multipart/form-data`, file field named `file` | Upload a purchase attachment |
| GET | `/attachments/{attachment}/download` | — | Downloads the original file |
| DELETE | `/attachments/{attachment}` | — | `204` |
| GET | `/notifications` | Query: `page` | Paginated notifications (30 per page) |
| PATCH | `/notifications/{notification}` | — | Marks the signed-in user's notification as read; `204` |
| GET | `/reports/assets.csv` | — | Downloads `assets-report.csv` |
| GET | `/activity-logs` | Optional query: `user_id`, `action`, `from`, `to`, `page` | Paginated activity logs (50 per page) |
| GET | `/dashboard/stats` | — | Dashboard totals, breakdowns, expiring warranties, due maintenance, and recent activity |

Attachment upload accepts JPG/JPEG, PNG, WEBP, PDF, DOC/DOCX, XLS/XLSX, and CSV files up to 10 MB. Use `FormData` in the browser; do not set the multipart `Content-Type` header manually.

### Inventory audits

| Method | Path | Request | Response / notes |
| --- | --- | --- | --- |
| GET | `/inventory-audits` | Query: `page` | Paginated audits (30 per page) |
| POST | `/inventory-audits` | `audit_number`; optional `location_id`, `notes` | Creates an audit in progress (`201`) |
| GET | `/inventory-audits/{inventory_audit}` | — | Audit and items with related asset/location details |
| PUT or PATCH | `/inventory-audits/{inventory_audit}` | `status`; optional `notes` | Updates audit status |
| DELETE | `/inventory-audits/{inventory_audit}` | — | `204` |
| POST | `/inventory-audits/{inventory_audit}/items` | `{ "asset_ids": [1, 2] }` | Adds assets to the audit (`201`) |
| PATCH | `/inventory-audit-items/{inventory_audit_item}` | `status`; optional `actual_location_id`, `condition`, `notes` | Verifies an item |

Audit item status accepts `verified`, `missing`, or `damaged`. If the audit has a location, all submitted assets must be assigned to that location.

### User, role, and permission administration

These endpoints are restricted to Super Admin users with the `users.manage` permission.

| Method | Path | Request | Response / notes |
| --- | --- | --- | --- |
| GET | `/users` | Query: `page` | Paginated users (50 per page) |
| POST | `/users` | `name`, `email`, `password`, `role`; optional `employee_id`, `is_active` | Created user (`201`) |
| PUT or PATCH | `/users/{user}` | Optional `name`, `email`, `employee_id`, `is_active` | Updated user |
| DELETE | `/users/{user}` | — | Deactivates user and revokes tokens (`204`) |
| POST | `/users/{user}/role` | `{ "role": "IT Staff" }` | Replaces the user's role |
| POST | `/users/{user}/password` | `{ "password": "..." }` | Resets password and revokes tokens (`204`) |
| GET | `/roles` | — | Roles and their permissions |
| GET | `/permissions` | — | Permission list |
| PUT | `/roles/{role}/permissions` | `{ "permissions": ["assets.view"] }` | Replaces permissions assigned to the role |

## Roles and access

Authentication is required for every endpoint except login. Authorization is enforced on the backend; the frontend should use the `roles` returned by login or `/me` to tailor the interface, but must still handle `403` responses.

| Role | Typical API access |
| --- | --- |
| Super Admin | User/role administration and all system operations |
| IT Admin | Asset, master data, maintenance, audit, and reporting operations |
| IT Staff | Asset operations, maintenance, inventory audits, and permitted lookups |
| Department Manager | Department-scoped asset visibility, permitted employee/department lookups, dashboard and reports |
| Management | Permitted asset viewing, dashboard, and reports |
| Employee | Own profile, assigned assets, and notifications |

Additional permissions apply per endpoint and resource. Examples include `assets.view`, `assets.create`, `assets.update`, `assets.assign`, `assets.return`, `reports.view`, and `inventory-audits.manage`.

## Endpoint implementation reference

The canonical route and behavior definitions are in [routes/api.php](routes/api.php); request validation and response details are implemented in [app/Http/Controllers/](app/Http/Controllers/).
