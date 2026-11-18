# Frontend Integration Guide

This guide describes how a frontend can connect to the IT Asset Management API.

## Base URL and local setup

With the backend running locally, use:

```text
http://localhost:8000/api
```

Start it from the `ams/backend` directory:

```powershell
Copy-Item .env.example .env
docker compose build app
docker compose run --rm --no-deps app php artisan key:generate
docker compose up
```

The configured browser origin is `http://localhost:5173`. If your frontend runs from another origin, add it to `allowed_origins` in `config/cors.php`.

## Authentication

Login does not require a token. Send JSON and request JSON responses:

```http
POST /api/login
Accept: application/json
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "password"
}
```

A successful login returns:

```json
{
  "token": "<personal-access-token>",
  "user": {
    "id": 1,
    "name": "Super Admin",
    "email": "admin@example.com",
    "employee_id": null,
    "roles": ["Super Admin"]
  }
}
```

For all other endpoints, send the token in the `Authorization` header:

```http
Authorization: Bearer <personal-access-token>
Accept: application/json
```

`GET /api/me` returns the signed-in user. `POST /api/logout` revokes the current token and returns `204 No Content`. Use the returned role names to tailor frontend navigation, but always handle `403` responses because permissions are enforced by the backend.

Example request helper (pass a JSON-stringified body for JSON requests):

```js
const API_BASE_URL = "http://localhost:8000/api";

async function apiRequest(path, { token, ...options } = {}) {
  const isFormData = options.body instanceof FormData;
  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers: {
      Accept: "application/json",
      ...(options.body && !isFormData ? { "Content-Type": "application/json" } : {}),
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

Example usage:

```js
const login = await apiRequest("/login", {
  method: "POST",
  body: JSON.stringify({ email, password }),
});

const assets = await apiRequest("/assets?page=1", { token: login.token });
```

## Endpoints

All paths below are relative to the API base URL. Except for login, endpoints require a bearer token.

### Authentication and assets

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/login` | Authenticate and return a token and user |
| GET | `/me` | Get current user and roles |
| POST | `/logout` | Revoke current token |
| GET | `/me/assets` | List assets assigned to the signed-in employee |
| GET | `/assets` | Search/filter assets and get a paginated list |
| POST | `/assets` | Create an asset |
| GET | `/assets/{asset}` | Get asset details |
| PUT | `/assets/{asset}` | Update an asset |
| DELETE | `/assets/{asset}` | Delete an asset |
| POST | `/assets/{asset}/assign` | Assign asset to an employee or department |
| POST | `/assets/{asset}/return` | Return the active assignment |

Asset list query parameters: `status` (`active`, `damaged`, `in_repair`, `retired`), `category_id`, `location_id`, `search`, and `page`.

### Master data and maintenance

The following resources support `GET /{resource}`, `POST /{resource}`, `PUT /{resource}/{id}`, and `DELETE /{resource}/{id}`:

`employees`, `departments`, `locations`, `categories`, `brands`, `suppliers`, `purchases`, and `warranties`.

Maintenance routes are `GET /maintenances`, `POST /maintenances`, `PUT /maintenances/{id}`, and `DELETE /maintenances/{id}`.

### Other endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/assets/{asset}/attachments` | Upload an asset file |
| POST | `/purchases/{purchase}/attachments` | Upload a purchase file |
| GET | `/attachments/{attachment}/download` | Download an attachment |
| DELETE | `/attachments/{attachment}` | Delete an attachment |
| GET | `/notifications` | List notifications |
| PATCH | `/notifications/{notification}` | Mark notification as read |
| GET | `/dashboard/stats` | Get dashboard summary |
| GET | `/reports/assets.csv` | Download the assets CSV report |
| GET | `/activity-logs` | List activity logs |

Attachment uploads use `multipart/form-data` with a `file` field. Allowed file types are JPG/JPEG, PNG, WEBP, PDF, DOC/DOCX, XLS/XLSX, and CSV; maximum size is 10 MB. When using `FormData`, let the browser set the multipart `Content-Type` and boundary.

### Inventory audits

| Method | Path | Purpose |
| --- | --- | --- |
| GET, POST | `/inventory-audits` | List or start an audit |
| GET, PUT, PATCH, DELETE | `/inventory-audits/{inventory_audit}` | View, update, or delete an audit |
| POST | `/inventory-audits/{inventory_audit}/items` | Add assets using `{ "asset_ids": [1, 2] }` |
| PATCH | `/inventory-audit-items/{inventory_audit_item}` | Verify an audit item |

### User and role administration

These endpoints require the Super Admin role and `users.manage` permission.

| Method | Path | Purpose |
| --- | --- | --- |
| GET, POST | `/users` | List or create users |
| PUT, PATCH, DELETE | `/users/{user}` | Update or deactivate a user |
| POST | `/users/{user}/role` | Set a user's role |
| POST | `/users/{user}/password` | Reset a user's password |
| GET | `/roles` | List roles and permissions |
| GET | `/permissions` | List permissions |
| PUT | `/roles/{role}/permissions` | Replace permissions assigned to a role |

## Responses, pagination, and errors

- List endpoints return Laravel paginator JSON with `data`, `current_page`, `last_page`, `per_page`, `total`, and pagination links. Pass `page` to request another page.
- Page sizes vary by endpoint: assets 20; generic resources, users, and activity logs 50; inventory audits and notifications 30.
- Successful deletes and logout return `204 No Content`; do not attempt to parse a JSON body for these responses.
- Validation errors return `422` with `message` and field-specific `errors`. Invalid login credentials also return `422`.
- `401` means authentication is missing or invalid; `403` means the user lacks access; `404` means the requested resource was not found.

For exact validation rules and the canonical route definitions, see [routes/api.php](routes/api.php) and the controllers in [app/Http/Controllers/](app/Http/Controllers/).
