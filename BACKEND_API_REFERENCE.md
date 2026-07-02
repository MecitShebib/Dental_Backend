# Dental Backend API Reference

## Tables

- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`
- `personal_access_tokens`
- `roles`
- `permissions`
- `role_user`
- `permission_role`
- `permission_user`
- `clients`
- `treatment_records`
- `treatment_catalog`
- `treatment_record_teeth`
- `doctor_schedules`
- `doctor_schedule_days`
- `appointments`
- `visits`
- `payments`

## Migrations

- `0001_01_01_000000_create_users_table.php`
- `0001_01_01_000001_create_cache_table.php`
- `0001_01_01_000002_create_jobs_table.php`
- `2026_05_07_130400_create_personal_access_tokens_table.php`
- `2026_05_07_130500_create_roles_and_permissions_tables.php`
- `2026_05_07_130600_create_clients_table.php`
- `2026_05_07_130700_create_treatment_tables.php`
- `2026_05_07_130800_create_schedule_appointments_visits_payments_tables.php`

## Models

- `User`
- `Role`
- `Permission`
- `Client`
- `TreatmentRecord`
- `TreatmentCatalog`
- `TreatmentRecordTooth`
- `DoctorSchedule`
- `DoctorScheduleDay`
- `Appointment`
- `Visit`
- `Payment`

## Controllers

- `AuthController`
- `UserController`
- `ClientController`
- `ClientTreatmentRecordController`
- `ClientVisitController`
- `ClientPaymentController`
- `ClientAppointmentController`
- `DoctorScheduleController`
- `DoctorAvailabilityController`
- `AppointmentController`

## API Routes

```text
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
GET    /api/doctors
GET    /api/users
POST   /api/users
GET    /api/users/{user}
PUT    /api/users/{user}
DELETE /api/users/{user}
GET    /api/clients
POST   /api/clients
GET    /api/clients/{client}
PUT    /api/clients/{client}
DELETE /api/clients/{client}
GET    /api/clients/{client}/appointments
GET    /api/clients/{client}/treatment-record
PUT    /api/clients/{client}/treatment-record
GET    /api/clients/{client}/visits
POST   /api/clients/{client}/visits
PUT    /api/visits/{visit}
DELETE /api/visits/{visit}
GET    /api/clients/{client}/payments
POST   /api/clients/{client}/payments
PUT    /api/payments/{payment}
DELETE /api/payments/{payment}
GET    /api/doctors/{doctor}/schedule
PUT    /api/doctors/{doctor}/schedule
GET    /api/doctors/{doctor}/availability?date=YYYY-MM-DD
GET    /api/doctors/{doctor}/available-start-times?date=YYYY-MM-DD&duration_minutes=30
GET    /api/doctors/{doctor}/available-durations?date=YYYY-MM-DD&start_time=10:00
GET    /api/appointments
POST   /api/appointments
GET    /api/appointments/{appointment}
PUT    /api/appointments/{appointment}
DELETE /api/appointments/{appointment}
POST   /api/appointments/{appointment}/check-in
POST   /api/appointments/{appointment}/no-show
```

## Validation Highlights

- `appointments.type`: `booked|unavailable`
- `appointments.status`: `scheduled|completed|no_show|cancelled`
- `appointments.duration_minutes`: `30|60|90`
- `appointments.client_id`: required when `type=booked`, must be null when `type=unavailable`
- appointments are rejected on overlap or if outside doctor working hours
- `payments.payment_method`: `cash|card|bank_transfer`
- `teeth.*.treatment_catalog_id`: required and must exist
- `teeth.*.unit_price`: required numeric

## Response Envelope

### Success

```json
{
  "message": "Success",
  "data": {}
}
```

### Validation Error

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "appointment": [
      "The selected doctor already has a booked or unavailable slot that overlaps with this time range."
    ]
  }
}
```

## Endpoint Examples

### `POST /api/auth/login`

Required fields:
- `email`
- `password`

Request:

```json
{
  "email": "admin@clinic.com",
  "password": "secret"
}
```

Success:

```json
{
  "token": "plain_text_token",
  "user": {
    "id": 1,
    "uuid": "uuid-value",
    "name": "Clinic Admin",
    "email": "admin@clinic.com",
    "phone": "+963900000001",
    "job_title": "System Manager",
    "branch_name": "Damascus",
    "status": "active",
    "is_doctor": false,
    "roles": [
      {
        "id": 1,
        "name": "System Manager",
        "slug": "system_manager"
      }
    ],
    "permissions": []
  }
}
```

### `GET /api/clients`

Query parameters:
- `page` optional

Success item:

```json
{
  "message": "Success",
  "data": [
    {
      "id": 10,
      "uuid": "uuid-value",
      "client_code": "CL-1001",
      "name": "Mohammad",
      "phone": "+963900001001",
      "city": "Damascus",
      "status": "new",
      "last_visit_at": null,
      "next_appointment": {
        "id": 44,
        "uuid": "uuid-value",
        "client_id": 10,
        "client_name": "Mohammad",
        "doctor_id": 2,
        "doctor_name": "Dr. Layan",
        "type": "booked",
        "status": "scheduled",
        "date": "2026-05-10",
        "start_time": "09:00",
        "end_time": "09:30",
        "duration_minutes": 30,
        "notes": null,
        "action_state": "manage",
        "is_past": false,
        "is_future": true,
        "is_within_one_hour": false
      }
    }
  ]
}
```

### `GET /api/clients/{client}`

Success:

```json
{
  "message": "Success",
  "data": {
    "id": 10,
    "uuid": "uuid-value",
    "client_code": "CL-1001",
    "name": "Mohammad",
    "email": null,
    "phone": "+963900001001",
    "gender": "male",
    "age": null,
    "date_of_birth": null,
    "city": "Damascus",
    "address": null,
    "medical_notes": null,
    "status": "under_treatment",
    "last_visit_at": "2026-05-10 09:00:00",
    "next_appointment": null,
    "financial_summary": {
      "total_services_amount": 4500000,
      "total_paid_amount": 1400000,
      "remaining_amount": 3100000
    }
  }
}
```

### `PUT /api/clients/{client}`

Optional fields:
- `client_code`
- `name`
- `email`
- `phone`
- `gender`
- `age`
- `date_of_birth`
- `city`
- `address`
- `medical_notes`
- `status`

Request:

```json
{
  "name": "Mohammad Al Shami",
  "city": "Damascus",
  "status": "under_treatment"
}
```

### `GET /api/clients/{client}/treatment-record`

Success:

```json
{
  "message": "Success",
  "data": {
    "id": 1,
    "uuid": "uuid-value",
    "client_id": 10,
    "treatment_plan": "Treatment details",
    "currency_code": "SYP",
    "total_services_amount": 3980000,
    "notes": null,
    "teeth": [
      {
        "id": 1,
        "tooth_number": "11",
        "treatment_catalog_id": 4,
        "treatment_code": "implant",
        "treatment_name": "Implant",
        "unit_price": 3200000,
        "notes": null
      }
    ]
  }
}
```

### `PUT /api/clients/{client}/treatment-record`

Required fields:
- `teeth.*.tooth_number`
- `teeth.*.treatment_catalog_id`
- `teeth.*.unit_price`

Request:

```json
{
  "treatment_plan": "Treatment details ...",
  "teeth": [
    {
      "tooth_number": "11",
      "treatment_catalog_id": 4,
      "unit_price": 3200000,
      "notes": null
    },
    {
      "tooth_number": "36",
      "treatment_catalog_id": 5,
      "unit_price": 780000,
      "notes": null
    }
  ]
}
```

### `GET /api/clients/{client}/visits`

Success returns `VisitResource` items with:
- `id`
- `uuid`
- `client_id`
- `doctor_id`
- `doctor_name`
- `appointment_id`
- `visit_date`
- `start_time`
- `duration_minutes`
- `summary`
- `notes`
- `attendance_status`

### `POST /api/clients/{client}/visits`

Required fields:
- `doctor_id`
- `visit_date`

Optional fields:
- `appointment_id`
- `start_time`
- `duration_minutes`
- `summary`
- `notes`

Request:

```json
{
  "doctor_id": 2,
  "visit_date": "2026-05-10",
  "start_time": "10:00",
  "duration_minutes": 30,
  "summary": "Walk-in consultation",
  "notes": "Patient came without prior appointment"
}
```

### `PUT /api/visits/{visit}`

Request:

```json
{
  "summary": "Updated summary",
  "notes": "Updated notes"
}
```

### `DELETE /api/visits/{visit}`

Success:

```json
{
  "message": "Visit deleted successfully.",
  "data": null
}
```

### `GET /api/clients/{client}/payments`

Success returns `PaymentResource` items with:
- `id`
- `uuid`
- `client_id`
- `visit_id`
- `payment_date`
- `amount`
- `payment_method`
- `notes`

### `POST /api/clients/{client}/payments`

Required fields:
- `payment_date`
- `amount`
- `payment_method`

Optional fields:
- `visit_id`
- `notes`

Request:

```json
{
  "visit_id": 55,
  "payment_date": "2026-05-10",
  "amount": 500000,
  "payment_method": "cash",
  "notes": "First payment"
}
```

### `PUT /api/payments/{payment}`

Request:

```json
{
  "amount": 750000,
  "payment_method": "card"
}
```

### `DELETE /api/payments/{payment}`

Success:

```json
{
  "message": "Payment deleted successfully.",
  "data": null
}
```

### `GET /api/doctors/{doctor}/schedule`

Success:

```json
{
  "message": "Success",
  "data": {
    "doctor_id": 2,
    "start_time": "09:00",
    "end_time": "17:00",
    "slot_minutes": 30,
    "working_days": ["monday", "tuesday", "wednesday", "thursday", "saturday"]
  }
}
```

### `PUT /api/doctors/{doctor}/schedule`

Request:

```json
{
  "start_time": "09:00",
  "end_time": "17:00",
  "slot_minutes": 30,
  "working_days": ["monday", "tuesday", "wednesday", "thursday", "saturday"]
}
```

### `GET /api/doctors/{doctor}/availability`

Query parameters:
- `date` required

Success:

```json
{
  "message": "Success",
  "data": {
    "doctor_id": 2,
    "date": "2026-05-10",
    "slots": [
      {
        "time": "09:00",
        "status": "free",
        "appointment": null
      }
    ]
  }
}
```

### `GET /api/doctors/{doctor}/available-start-times`

Query parameters:
- `date` required
- `duration_minutes` required

Success:

```json
{
  "message": "Success",
  "data": {
    "doctor_id": 2,
    "date": "2026-05-10",
    "duration_minutes": 60,
    "start_times": ["09:00", "12:00", "12:30"]
  }
}
```

### `GET /api/doctors/{doctor}/available-durations`

Query parameters:
- `date` required
- `start_time` required

Success:

```json
{
  "message": "Success",
  "data": {
    "doctor_id": 2,
    "date": "2026-05-10",
    "start_time": "10:00",
    "durations": [
      { "value": 30, "available": true },
      { "value": 60, "available": false },
      { "value": 90, "available": false }
    ]
  }
}
```

### `GET /api/appointments`

Query parameters:
- `doctor_id` optional
- `client_id` optional
- `date` optional
- `status` optional

### `POST /api/appointments`

Required fields:
- `doctor_id`
- `type`
- `date`
- `start_time`
- `duration_minutes`

Conditionally required:
- `client_id` when `type=booked`

Optional fields:
- `status`
- `notes`

Booked request:

```json
{
  "client_id": 5,
  "doctor_id": 2,
  "type": "booked",
  "date": "2026-05-10",
  "start_time": "09:00",
  "duration_minutes": 30,
  "notes": "New appointment"
}
```

Unavailable request:

```json
{
  "doctor_id": 2,
  "type": "unavailable",
  "date": "2026-05-10",
  "start_time": "15:00",
  "duration_minutes": 60,
  "notes": "Doctor in surgery"
}
```

Success item:

```json
{
  "message": "Appointment created successfully.",
  "data": {
    "id": 10,
    "uuid": "uuid-value",
    "client_id": 5,
    "client_name": "Mohammad Al Shami",
    "doctor_id": 2,
    "doctor_name": "Dr. Layan",
    "type": "booked",
    "status": "scheduled",
    "date": "2026-05-10",
    "start_time": "09:00",
    "end_time": "09:30",
    "duration_minutes": 30,
    "notes": "Follow-up",
    "action_state": "manage",
    "is_past": false,
    "is_future": true,
    "is_within_one_hour": false
  }
}
```

Conflict error:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "appointment": [
      "The selected doctor already has a booked or unavailable slot that overlaps with this time range."
    ]
  }
}
```

### `PUT /api/appointments/{appointment}`

Request:

```json
{
  "start_time": "10:00",
  "duration_minutes": 60,
  "notes": "Updated appointment"
}
```

### `DELETE /api/appointments/{appointment}`

Success:

```json
{
  "message": "Appointment deleted successfully.",
  "data": null
}
```

### `POST /api/appointments/{appointment}/check-in`

Request:

```json
{
  "summary": "Started treatment",
  "notes": "Visit notes",
  "create_payment_after_visit": false
}
```

Success:

```json
{
  "message": "Appointment checked in successfully.",
  "data": {
    "id": 90,
    "uuid": "uuid-value",
    "client_id": 5,
    "doctor_id": 2,
    "doctor_name": "Dr. Layan",
    "appointment_id": 10,
    "visit_date": "2026-05-10",
    "start_time": "09:00",
    "duration_minutes": 30,
    "summary": "Started treatment",
    "notes": "Visit notes",
    "attendance_status": "attended"
  }
}
```

### `POST /api/appointments/{appointment}/no-show`

Request:

```json
{
  "notes": "Patient did not come"
}
```

Success:

```json
{
  "message": "Appointment marked as no show successfully.",
  "data": {
    "attendance_status": "no_show"
  }
}
```
