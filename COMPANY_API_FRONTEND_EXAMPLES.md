# Company API Examples For Frontend

## 1. Get One Company With Latest Active Subscription

### Endpoint

```http
GET /api/companies/{company}
```

### Example

```http
GET /api/companies/1
Authorization: Bearer {token}
Accept: application/json
```

### Success Response

```json
{
  "message": "Success",
  "data": {
    "id": 1,
    "uuid": "3e7489ba-3ed7-4f53-b762-51a7e4195a80",
    "name": "Dental HQ",
    "code": "DENTAL-HQ",
    "email": "office@clinic.com",
    "phone": "+963110000001",
    "address": "Damascus",
    "status": "active",
    "notes": "Seeded company",
    "users_count": 3,
    "active_users_count": 3,
    "latest_active_subscription": {
      "id": 1,
      "company_id": 1,
      "plan_name": "Clinic Company Plan",
      "status": "active",
      "starts_at": "2026-04-08",
      "ends_at": "2027-05-08",
      "max_users": 10,
      "active_users": 3,
      "price": 0,
      "notes": "Seeded company subscription",
      "is_currently_active": true,
      "created_at": "2026-05-08 10:00:00",
      "updated_at": "2026-05-08 10:00:00"
    }
  }
}
```

### Not Found Example

```json
{
  "message": "No query results for model [App\\Models\\Company] 999"
}
```

---

## 2. Get All Subscriptions For One Company

### Endpoint

```http
GET /api/companies/{company}/subscriptions
```

### Example

```http
GET /api/companies/1/subscriptions
Authorization: Bearer {token}
Accept: application/json
```

### Success Response

```json
{
  "message": "Success",
  "data": [
    {
      "id": 2,
      "company_id": 1,
      "plan_name": "Clinic Company Plan 2027",
      "status": "inactive",
      "starts_at": "2027-05-09",
      "ends_at": "2028-05-09",
      "max_users": 15,
      "active_users": 0,
      "price": 500,
      "notes": "Renewal draft",
      "is_currently_active": false,
      "created_at": "2026-05-08 10:05:00",
      "updated_at": "2026-05-08 10:05:00"
    },
    {
      "id": 1,
      "company_id": 1,
      "plan_name": "Clinic Company Plan",
      "status": "active",
      "starts_at": "2026-04-08",
      "ends_at": "2027-05-08",
      "max_users": 10,
      "active_users": 3,
      "price": 0,
      "notes": "Seeded company subscription",
      "is_currently_active": true,
      "created_at": "2026-05-08 10:00:00",
      "updated_at": "2026-05-08 10:00:00"
    }
  ]
}
```

---

## Suggested Frontend Usage

## Load company summary

Use this after login when you know the authenticated user's `company_id`.

```ts
const company = await api.get(`/api/companies/${companyId}`);
```

Use it for:
- company profile card
- dashboard summary
- current active subscription card
- seat usage (`active_users_count`, `latest_active_subscription.max_users`)

## Load subscription history

```ts
const subscriptions = await api.get(`/api/companies/${companyId}/subscriptions`);
```

Use it for:
- subscription details page
- subscription timeline/history table
- renewal planning UI

---

## Ready Prompt For Frontend

```text
Use these backend endpoints for company data:

1. GET /api/companies/{company}
Return one company with its latest active subscription in:
- data.latest_active_subscription

2. GET /api/companies/{company}/subscriptions
Return all subscriptions for the company ordered by latest start date first.

Expected usage:
- On login, call GET /api/auth/me
- Read company_id from authenticated user
- Call GET /api/companies/{company_id} to load company overview and latest active subscription
- Call GET /api/companies/{company_id}/subscriptions to render subscription history/details

Use latest_active_subscription for:
- plan name
- subscription status
- starts_at
- ends_at
- max_users
- active_users
- remaining seats = max_users - active_users

If latest_active_subscription is null:
- show “No active subscription”
- disable actions that require an active subscription if needed
```

