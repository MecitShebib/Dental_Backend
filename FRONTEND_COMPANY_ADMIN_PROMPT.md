# Company Admin Frontend Prompt

## Purpose

This file is for the Front-end team to build the **Company Admin Panel** for the dental system.

Important distinction:
- `Project Admin` is a special backend-only admin for the whole project.
- `Company Admin` is a normal company user who has the role `system_manager`.
- This frontend is for the **Company Admin of a single company**, not for the project-wide admin.

The Company Admin should be able to:
- manage company users
- view company subscription details
- update user status to `active` or `inactive`
- create users
- update users
- delete users
- view company information
- view subscription limits such as `max_users` and `active_users`

---

## Core Data Model

### 1. Company

Represents the clinic/company/organization.

Suggested fields:

```json
{
  "id": 1,
  "uuid": "company-uuid",
  "name": "Dental HQ",
  "code": "DENTAL-HQ",
  "email": "office@clinic.com",
  "phone": "+963110000001",
  "address": "Damascus",
  "status": "active",
  "notes": "..."
}
```

### 2. Subscription

Subscription belongs to a company, not to a user.

Suggested fields:

```json
{
  "id": 5,
  "company_id": 1,
  "plan_name": "Clinic Company Plan",
  "status": "active",
  "starts_at": "2026-05-01",
  "ends_at": "2027-05-01",
  "max_users": 10,
  "active_users": 3,
  "price": 0,
  "notes": "..."
}
```

### 3. User

User belongs to a company.

Suggested fields:

```json
{
  "id": 10,
  "uuid": "user-uuid",
  "company_id": 1,
  "company_name": "Dental HQ",
  "name": "Ahmad",
  "email": "ahmad@clinic.com",
  "phone": "+963...",
  "job_title": "Receptionist",
  "branch_name": "Damascus",
  "status": "active",
  "is_project_admin": false,
  "is_doctor": false,
  "notes": null,
  "roles": [
    {
      "id": 1,
      "name": "System Manager",
      "slug": "system_manager"
    }
  ],
  "permissions": []
}
```

---

## Business Rules For Frontend

### Company Admin scope

The Company Admin must only work inside **his own company**.

Frontend must:
- load the authenticated user
- read `company_id`
- restrict all company management screens to that company
- never show project-wide company lists to company admin users

### User limit rules

Frontend must visually show:
- current subscription `max_users`
- current subscription `active_users`
- remaining active seats = `max_users - active_users`

When creating or activating a user:
- if `active_users >= max_users`
- disable saving if possible
- still display backend validation errors if backend rejects the request

### User status rules

User status values:
- `active`
- `inactive`
- `suspended`

Behavior:
- inactive or suspended users should not be considered active seats
- only `active` users count against subscription `max_users`

---

## Required Pages

## 1. Company Admin Login

### Purpose
- allow company admin to log in

### API
```http
POST /api/auth/login
```

### Request
```json
{
  "email": "manager@clinic.com",
  "password": "secret"
}
```

### Success handling
- store Sanctum token
- load authenticated user
- redirect to Company Dashboard

### Error handling
- show validation message from backend

---

## 2. Company Dashboard

### Purpose
Single overview page for company admin.

### Sections
- company information card
- current subscription card
- user summary card
- quick actions

### Show
- company name
- company code
- company status
- subscription plan name
- subscription status
- starts_at
- ends_at
- max_users
- active_users
- remaining seats
- total users count
- total active users count

### Quick actions
- Create User
- View Users
- View Subscription

---

## 3. Company Profile Page

### Purpose
Read-only or limited-edit page for company info, depending on product decision.

### Show
- name
- code
- email
- phone
- address
- status
- notes

If editing is allowed for company admin:
- Update Company button
- modal or form page for editing company details

---

## 4. Subscription Page

### Purpose
Allow company admin to view subscription details clearly.

### Show
- plan_name
- status
- starts_at
- ends_at
- price
- max_users
- active_users
- remaining seats
- notes

### Visual indicators
- green badge if `status = active`
- red/orange badge if `status = inactive`
- warning banner if active seats are full
- warning banner if subscription is near expiration

### Optional sections
- history of previous subscriptions if backend exposes it

---

## 5. Users List Page

### Purpose
Main management screen for company users.

### Table columns
- name
- email
- phone
- role
- job_title
- branch_name
- status
- is_doctor
- actions

### Actions
- Create User
- Update User
- Toggle status
- Delete User

### Recommended UX
- table view on desktop
- card list on mobile
- top summary:
  - `active_users / max_users`
  - remaining seats

### Filters
- search by name/email/phone
- filter by status
- filter by role
- filter by doctor/non-doctor

---

## 6. Create User Modal/Page

### Purpose
Allow company admin to create a user inside his own company.

### Fields
- `name` required
- `email` required
- `phone` optional
- `password` required
- `job_title` optional
- `branch_name` optional
- `status` required
- `is_doctor` required
- `role_id` required single select
- `notes` optional

### Important
- `company_id` should be automatically assigned from authenticated company context
- do not ask the company admin to choose company
- role selection should be a **single select**

### Suggested role options
- `system_manager`
- `receptionist`
- `treatment_coordinator`
- `doctor`

### Validation UX
- inline field errors
- top toast or alert on failure

---

## 7. Update User Modal/Page

### Purpose
Edit existing company user.

### Fields
- same as Create User
- password optional and blank means keep current password

### Actions
- save changes
- change status to `active` / `inactive`
- delete user

### Important
- if activating a user exceeds subscription seat limit:
  - show backend validation message clearly

---

## Suggested Navigation

- Dashboard
- Users
- Subscription
- Company Profile
- Logout

---

## Current Backend APIs Available

These are already present in the current backend:

### Authentication
```http
POST /api/auth/login
POST /api/auth/logout
GET /api/auth/me
```

### Users
```http
GET /api/users
POST /api/users
GET /api/users/{user}
PUT /api/users/{user}
DELETE /api/users/{user}
GET /api/doctors
```

### Notes
- `UserResource` includes `company_id` and `company_name`
- current backend user APIs are generic
- frontend should filter by current authenticated user's `company_id`

---

## Recommended Additional API Endpoints For Company Admin

If the frontend should avoid loading global data and filtering client-side, backend should expose company-scoped endpoints like:

```http
GET    /api/company/me
GET    /api/company/subscriptions
GET    /api/company/users
POST   /api/company/users
PUT    /api/company/users/{user}
DELETE /api/company/users/{user}
PATCH  /api/company/users/{user}/status
```

Recommended subscription endpoint:

```http
GET /api/company/current-subscription
```

Recommended response shape:

```json
{
  "message": "Success",
  "data": {
    "company": {},
    "subscription": {},
    "users": []
  }
}
```

If these endpoints are not yet available, frontend may temporarily:
- call `GET /api/auth/me`
- get `company_id`
- call generic users endpoint
- filter users by `company_id`

But company-scoped endpoints are strongly preferred.

---

## Frontend State Requirements

Store:
- auth token
- authenticated user
- company info
- current subscription
- users list

Derived state:
- `remainingSeats = max_users - active_users`
- `isSeatLimitReached = remainingSeats <= 0`
- `canCreateActiveUser = subscription.status === 'active' && remainingSeats > 0`

---

## UX Rules

- do not expose project admin controls to company admin
- do not show other companies
- company admin should never choose company manually
- creating or editing user should happen in modal or dedicated form
- after successful create/update/delete:
  - refresh current page data
  - close modal
  - show success toast

---

## Recommended Component Breakdown

- `CompanyAdminLayout`
- `CompanyDashboardPage`
- `CompanyProfileCard`
- `SubscriptionSummaryCard`
- `UsersSummaryCard`
- `UsersTable`
- `CreateUserModal`
- `UpdateUserModal`
- `SubscriptionDetailsCard`
- `StatusBadge`
- `SeatUsageMeter`

---

## Ready Prompt For Frontend Team

```text
Build a Company Admin Panel for a dental clinic SaaS system.

This panel is NOT for the project-wide admin. It is only for a company-level admin user whose role is `system_manager` inside one company.

The authenticated company admin must only see data for their own company.

Pages required:
1. Login page
2. Company dashboard
3. Users management page
4. Subscription details page
5. Company profile page

Core entities:
- Company
- Subscription
- User

Important backend rules:
- Subscription belongs to company, not user
- Users belong to company
- Subscription contains:
  - max_users
  - active_users
- Active users must never exceed max_users
- Only users with status `active` count against max_users
- Backend may reject creating or activating a user if the limit is reached

Frontend requirements:
- After login, call `/api/auth/me`
- Read the authenticated user's `company_id`
- Restrict all data shown to that company only
- Do not expose project-admin controls
- Role selection for user create/update must be single-select, not multi-select

Build these features:
- Create user
- Update user
- Delete user
- Activate or deactivate user
- View subscription details
- View company details
- Show seat usage:
  - active_users / max_users
  - remaining seats
- Show warning when no seats remain

User fields:
- name
- email
- phone
- password
- job_title
- branch_name
- status
- is_doctor
- role
- notes

Subscription fields to display:
- plan_name
- status
- starts_at
- ends_at
- price
- max_users
- active_users
- remaining seats
- notes

UI requirements:
- Clean admin layout
- Desktop table for users
- Responsive mobile cards or stacked rows
- Modals for create/update user
- Success/error toast notifications
- Inline validation messages

API integration:
- Use Sanctum token auth
- Existing endpoints:
  - POST /api/auth/login
  - POST /api/auth/logout
  - GET /api/auth/me
  - GET /api/users
  - POST /api/users
  - PUT /api/users/{user}
  - DELETE /api/users/{user}

If company-specific endpoints are not yet available, temporarily filter user records by `company_id` from the authenticated user.

Preferred output:
- Production-ready pages
- Reusable components
- Clear loading, empty, and error states
- Proper form validation and disabled states when seat limit is reached
```

---

## Recommended File To Send

Send this file directly to the Front-end team:
- `FRONTEND_COMPANY_ADMIN_PROMPT.md`

