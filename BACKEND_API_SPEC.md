# Laravel Backend Specification For Dental Clinic Frontend

هذا الملف مخصص لإرساله مباشرة إلى فريق الـ backend.

الهدف:
- بناء Backend كامل بلغة Laravel يخدم الواجهة الحالية بالكامل
- إنشاء قاعدة البيانات
- إنشاء الـ migrations
- إنشاء الـ models / controllers / requests / resources / routes
- تطبيق قواعد التحقق ومنع تضارب المواعيد
- إعادة أمثلة كاملة لاستخدام الـ API بعد الانتهاء

مهم:
- الواجهة الأمامية الحالية تعتمد على مفاهيم: المستخدمين، العملاء، خطط العلاج، الأسنان المحددة، الزيارات، الدفعات، المواعيد، وأوقات عدم التوفر للطبيب
- يجب أن تكون بنية الـ API قابلة للتوسع لاحقاً
- يفضل استخدام Laravel 11
- يفضل استخدام API Resources و Form Requests
- يفضل استخدام Sanctum للمصادقة
- يجب إعادة أمثلة responses و requests لكل endpoint بعد الإنجاز

---

## 1. التقنية المطلوبة

الرجاء بناء المشروع بالافتراضات التالية:

- Framework: `Laravel 11`
- Auth: `Laravel Sanctum`
- Database: `MySQL 8+`
- API style: `REST JSON API`
- Validation: `FormRequest`
- Responses: `API Resources`
- PDF: ليس مطلوباً من الـ backend حالياً إلا إذا طلب لاحقاً

---

## 2. الوحدات المطلوبة

الوحدات الرئيسية في النظام:

1. Authentication
2. Users / Doctors / Staff
3. Roles / Permissions
4. Clients / Patients
5. Client Treatment Record
6. Selected Teeth and Treatments
7. Visits
8. Payments
9. Doctor Working Schedule
10. Appointments
11. Unavailable Doctor Slots

ملاحظة مهمة:
- أفضل تصميم هنا هو أن `الموعد العادي` و`الوقت غير المتاح` يكونان داخل نفس جدول `appointments`
- الفرق يتم تحديده عبر حقل `type`

مثال:
- `type = booked`
- `type = unavailable`

وحالة الموعد تكون في حقل منفصل:
- `status = scheduled`
- `status = completed`
- `status = no_show`
- `status = cancelled`

---

## 3. الـ Database Schema

## 3.1 users

جدول المستخدمين والطاقم الطبي والإداري.

### Migration: `users`

الحقول:

- `id` bigint unsigned PK
- `uuid` uuid unique
- `name` string
- `email` string unique
- `phone` string nullable
- `password` string
- `job_title` string nullable
- `branch_name` string nullable
- `status` enum: `active`, `inactive`, `suspended`
- `is_doctor` boolean default false
- `notes` text nullable
- `last_login_at` timestamp nullable
- `email_verified_at` timestamp nullable
- `remember_token` string nullable
- `created_at`
- `updated_at`
- `deleted_at` nullable soft delete

ملاحظات:
- بعض المستخدمين أطباء وبعضهم موظفو استقبال أو منسقو علاج
- يمكن لاحقاً ربطهم بأدوار وصلاحيات

Indexes:
- unique: `uuid`
- unique: `email`
- index: `status`
- index: `is_doctor`

---

## 3.2 roles

إذا أردتم بناءها يدوياً:

- `id`
- `name` unique
- `slug` unique
- `created_at`
- `updated_at`

أمثلة:
- `system_manager`
- `receptionist`
- `treatment_coordinator`
- `doctor`

## 3.3 permissions

- `id`
- `name`
- `slug` unique
- `created_at`
- `updated_at`

## 3.4 role_user

- `id`
- `role_id`
- `user_id`

## 3.5 permission_role

- `id`
- `permission_id`
- `role_id`

ملاحظة:
- يمكن استخدام `spatie/laravel-permission` بدلاً من بناء الجداول يدوياً
- إذا استخدمتم `spatie` فهو أفضل

---

## 3.6 clients

المرضى / العملاء.

### Migration: `clients`

الحقول:

- `id` bigint unsigned PK
- `uuid` uuid unique
- `client_code` string unique
- `name` string
- `email` string nullable
- `phone` string
- `gender` enum: `male`, `female`
- `age` unsigned integer nullable
- `date_of_birth` date nullable
- `city` string nullable
- `address` text nullable
- `medical_notes` longText nullable
- `status` enum: `new`, `under_treatment`, `completed`, `inactive`
- `last_visit_at` datetime nullable
- `created_by` foreignId nullable -> users.id
- `updated_by` foreignId nullable -> users.id
- `created_at`
- `updated_at`
- `deleted_at` nullable soft delete

Indexes:
- unique: `uuid`
- unique: `client_code`
- index: `phone`
- index: `status`
- index: `last_visit_at`

---

## 3.7 treatment_records

هذا الجدول يمثل سجل العلاج الحالي المرتبط بالعميل.

### Migration: `treatment_records`

الحقول:

- `id` bigint unsigned PK
- `uuid` uuid unique
- `client_id` foreignId -> clients.id
- `treatment_plan` longText nullable
- `currency_code` string default `SYP`
- `total_services_amount` decimal(12,2) default 0
- `notes` longText nullable
- `created_by` foreignId nullable -> users.id
- `updated_by` foreignId nullable -> users.id
- `created_at`
- `updated_at`

Indexes:
- unique: `uuid`
- unique: `client_id`

ملاحظة:
- لكل عميل سجل علاج نشط واحد حالياً
- إذا أردتم لاحقاً دعم أكثر من treatment record تاريخياً يمكن تحويل unique client_id إلى one-to-many

---

## 3.8 treatment_catalog

كتالوج الإجراءات العلاجية.

### Migration: `treatment_catalog`

الحقول:

- `id`
- `code` string unique
- `name_ar` string
- `name_en` string nullable
- `name_tr` string nullable
- `color` string nullable
- `default_price` decimal(12,2)
- `is_active` boolean default true
- `sort_order` integer default 0
- `created_at`
- `updated_at`

أمثلة `code`:
- `consultation`
- `filling`
- `crown`
- `implant`
- `root_canal`
- `extraction`

---

## 3.9 treatment_record_teeth

الأسنان المحددة داخل خطة العلاج.

### Migration: `treatment_record_teeth`

الحقول:

- `id` bigint unsigned PK
- `treatment_record_id` foreignId -> treatment_records.id
- `tooth_number` string
- `treatment_catalog_id` foreignId -> treatment_catalog.id
- `unit_price` decimal(12,2)
- `notes` text nullable
- `created_at`
- `updated_at`

Indexes:
- unique composite: `treatment_record_id + tooth_number`
- index: `tooth_number`

ملاحظات:
- `tooth_number` يساوي FDI notation مثل `11`, `16`, `36`
- لا تخزنوا الأسنان بصيغة JSON فقط، بل في جدول تفصيلي كما هنا

---

## 3.10 visits

سجل الزيارات الفعلية.

### Migration: `visits`

الحقول:

- `id` bigint unsigned PK
- `uuid` uuid unique
- `client_id` foreignId -> clients.id
- `doctor_id` foreignId -> users.id
- `appointment_id` foreignId nullable -> appointments.id
- `visit_date` date
- `start_time` time nullable
- `duration_minutes` unsigned integer nullable
- `summary` longText nullable
- `notes` longText nullable
- `attendance_status` enum: `attended`, `no_show`, `walk_in`
- `created_by` foreignId nullable -> users.id
- `updated_by` foreignId nullable -> users.id
- `created_at`
- `updated_at`
- `deleted_at` nullable soft delete

Indexes:
- unique: `uuid`
- index: `client_id`
- index: `doctor_id`
- index: `appointment_id`
- index: `visit_date`

ملاحظات:
- إذا كانت الزيارة ناتجة عن موعد سابق:
  - `appointment_id` يكون موجوداً
  - `attendance_status` يكون `attended` أو `no_show`
- إذا كانت زيارة مباشرة بدون موعد:
  - `appointment_id` يكون null
  - `attendance_status` يكون `walk_in`

---

## 3.11 payments

### Migration: `payments`

الحقول:

- `id` bigint unsigned PK
- `uuid` uuid unique
- `client_id` foreignId -> clients.id
- `visit_id` foreignId nullable -> visits.id
- `payment_date` date
- `amount` decimal(12,2)
- `payment_method` enum: `cash`, `card`, `bank_transfer`
- `notes` longText nullable
- `created_by` foreignId nullable -> users.id
- `updated_by` foreignId nullable -> users.id
- `created_at`
- `updated_at`
- `deleted_at` nullable soft delete

Indexes:
- unique: `uuid`
- index: `client_id`
- index: `visit_id`
- index: `payment_date`

---

## 3.12 doctor_schedules

إعدادات الطبيب الأساسية.

### Migration: `doctor_schedules`

الحقول:

- `id`
- `doctor_id` foreignId -> users.id
- `start_time` time
- `end_time` time
- `slot_minutes` unsigned integer default 30
- `created_at`
- `updated_at`

Indexes:
- unique: `doctor_id`

---

## 3.13 doctor_schedule_days

الأيام التي يعمل بها الطبيب.

### Migration: `doctor_schedule_days`

الحقول:

- `id`
- `doctor_schedule_id` foreignId -> doctor_schedules.id
- `weekday` enum: `monday`, `tuesday`, `wednesday`, `thursday`, `friday`, `saturday`, `sunday`
- `created_at`
- `updated_at`

Indexes:
- unique composite: `doctor_schedule_id + weekday`

---

## 3.14 appointments

### Migration: `appointments`

هذا أهم جدول في المشروع.

الحقول:

- `id` bigint unsigned PK
- `uuid` uuid unique
- `client_id` foreignId nullable -> clients.id
- `doctor_id` foreignId -> users.id
- `type` enum: `booked`, `unavailable`
- `status` enum: `scheduled`, `completed`, `no_show`, `cancelled`
- `date` date
- `start_time` time
- `duration_minutes` unsigned integer
- `end_time` time nullable
- `notes` longText nullable
- `created_by` foreignId nullable -> users.id
- `updated_by` foreignId nullable -> users.id
- `created_at`
- `updated_at`
- `deleted_at` nullable soft delete

Indexes:
- unique: `uuid`
- index: `doctor_id`
- index: `client_id`
- index: `type`
- index: `status`
- index: `date`
- composite index: `doctor_id + date`
- composite index: `doctor_id + date + start_time`

قواعد مهمة:
- إذا `type = booked` فإن `client_id` مطلوب
- إذا `type = unavailable` فإن `client_id` يجب أن يكون null
- يجب منع أي overlap زمني بين:
  - booked vs booked
  - booked vs unavailable
  - unavailable vs unavailable

هذه القاعدة يجب أن تُطبَّق داخل الـ backend حتماً، وليس في الواجهة فقط.

---

## 4. Business Rules Required In Backend

هذه القواعد يجب تنفيذها في الـ backend:

### 4.1 منع تضارب المواعيد

عند إنشاء أو تعديل أي appointment:

- احسب:
  - start datetime
  - end datetime = start + duration
- امنع الحفظ إذا وُجد موعد آخر للطبيب نفسه في نفس التاريخ يتداخل زمنياً

معادلة التداخل:

- يوجد تضارب إذا:
  - `new_start < existing_end`
  - AND `new_end > existing_start`

### 4.2 التحقق من حدود ساعات العمل

الموعد يجب أن يقع ضمن:
- `doctor_schedule.start_time`
- `doctor_schedule.end_time`

ولا يجوز أن ينتهي بعد وقت نهاية الدوام.

### 4.3 حساب الأوقات المتاحة

عند طلب الأوقات المتاحة:
- خذ اليوم المحدد
- تحقق أن اليوم موجود ضمن `doctor_schedule_days`
- أنشئ time slots حسب `slot_minutes`
- احذف أي slot متداخل مع appointment قائم

### 4.4 حساب المدد المتاحة

عند اختيار:
- doctor
- date
- start_time

يجب أن يعيد backend أي من المدد التالية صالحة:
- 30
- 60
- 90

مثال:
- إذا slot الحالي فارغ لكن slot الذي بعده ممتلئ
  - اسمح بـ 30 فقط
  - امنع 60 و90
- إذا slot الحالي والبعده فارغان والثالث ممتلئ
  - اسمح بـ 30 و60
  - امنع 90

### 4.5 منطق الموعد قبل ساعة

backend يجب أن يعيد في response field مثل:
- `action_state`

القيم:
- `manage`
- `checkin`
- `locked`

المعنى:
- `manage`: يمكن تعديل/حذف الموعد
- `checkin`: دخلنا نافذة الساعة الأخيرة أو بعد بدء الموعد، ويجب أن تظهر إجراءات `أتى / لم يأت`
- `locked`: الموعد انتهى أو لم يعد قابلاً للإدارة

القاعدة:
- قبل الموعد بساعة: يتحول إلى `checkin`

### 4.6 تحويل الموعد إلى زيارة

عند الضغط على `أتى`:
- لا ينشئ appointment جديد
- بل يسجل visit مرتبطاً بـ appointment
- ويغير appointment.status إلى `completed`

عند الضغط على `لم يأت`:
- ينشئ visit بحالة `no_show`
- ويربطه بـ appointment
- ويغير appointment.status إلى `no_show`

---

## 5. العلاقات بين الجداول

- `User hasOne DoctorSchedule`
- `DoctorSchedule hasMany DoctorScheduleDay`
- `Client hasOne TreatmentRecord`
- `TreatmentRecord hasMany TreatmentRecordTooth`
- `TreatmentRecordTooth belongsTo TreatmentCatalog`
- `Client hasMany Visits`
- `Client hasMany Payments`
- `Client hasMany Appointments`
- `Appointment belongsTo Client nullable`
- `Appointment belongsTo Doctor(User)`
- `Visit belongsTo Client`
- `Visit belongsTo Doctor(User)`
- `Visit belongsTo Appointment nullable`
- `Payment belongsTo Client`
- `Payment belongsTo Visit nullable`

---

## 6. المطلوب في الـ Models

أنشئ Models التالية:

- `User`
- `Role` أو استخدام spatie
- `Permission` أو استخدام spatie
- `Client`
- `TreatmentRecord`
- `TreatmentCatalog`
- `TreatmentRecordTooth`
- `Visit`
- `Payment`
- `DoctorSchedule`
- `DoctorScheduleDay`
- `Appointment`

يفضل:
- casts للحقول الزمنية
- casts للحقول enum
- accessors إذا لزم

---

## 7. الـ Controllers المطلوبة

أنشئ Controllers التالية:

### Auth
- `AuthController`

### Users
- `UserController`

### Clients
- `ClientController`
- `ClientTreatmentRecordController`
- `ClientVisitController`
- `ClientPaymentController`
- `ClientAppointmentController`

### Doctors / Schedule
- `DoctorScheduleController`
- `DoctorAvailabilityController`

### Appointments
- `AppointmentController`

---

## 8. الـ API Routes المطلوبة

## 8.1 Auth Routes

```php
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
```

### Login Request

```json
{
  "email": "admin@clinic.com",
  "password": "secret"
}
```

### Login Response

```json
{
  "token": "plain_text_token",
  "user": {
    "id": 1,
    "uuid": "....",
    "name": "Dr. Example",
    "email": "admin@clinic.com",
    "phone": "+963...",
    "job_title": "Doctor",
    "branch_name": "Damascus",
    "status": "active",
    "is_doctor": true,
    "roles": [],
    "permissions": []
  }
}
```

---

## 8.2 Users Routes

```php
GET    /api/users
POST   /api/users
GET    /api/users/{user}
PUT    /api/users/{user}
DELETE /api/users/{user}
```

اختياري:

```php
GET /api/doctors
```

يفضل إرجاع الأطباء فقط في endpoint مستقل لأنه مستخدم بكثرة في المواعيد.

---

## 8.3 Clients Routes

```php
GET    /api/clients
POST   /api/clients
GET    /api/clients/{client}
PUT    /api/clients/{client}
DELETE /api/clients/{client}
```

### Fields returned in client list

يجب أن يعيد:

- `id`
- `uuid`
- `client_code`
- `name`
- `phone`
- `city`
- `status`
- `last_visit_at`
- `next_appointment`

### next_appointment object

```json
{
  "id": 44,
  "date": "2026-05-10",
  "start_time": "09:00",
  "duration_minutes": 30,
  "doctor_id": 2,
  "doctor_name": "Dr. Layan",
  "action_state": "manage"
}
```

---

## 8.4 Client Treatment Record Routes

```php
GET    /api/clients/{client}/treatment-record
PUT    /api/clients/{client}/treatment-record
```

### Response shape

```json
{
  "id": 1,
  "client_id": 10,
  "treatment_plan": "....",
  "currency_code": "SYP",
  "total_services_amount": 1200000,
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
```

### Update request

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

مهم:
- backend يجب أن يعيد حساب `total_services_amount`
- واستبدال قائمة الأسنان الحالية بالقائمة الجديدة

---

## 8.5 Visits Routes

```php
GET    /api/clients/{client}/visits
POST   /api/clients/{client}/visits
PUT    /api/visits/{visit}
DELETE /api/visits/{visit}
POST   /api/appointments/{appointment}/check-in
POST   /api/appointments/{appointment}/no-show
```

### Add walk-in visit request

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

### Add visit from appointment

يفضل استخدام endpoint مستقل:

```json
POST /api/appointments/{appointment}/check-in
{
  "summary": "Started treatment",
  "notes": "Visit notes",
  "create_payment_after_visit": false
}
```

### No show endpoint

```json
POST /api/appointments/{appointment}/no-show
{
  "notes": "Patient did not come"
}
```

السلوك المطلوب:
- ينشئ visit
- يربطها بالموعد
- يغير appointment.status

---

## 8.6 Payments Routes

```php
GET    /api/clients/{client}/payments
POST   /api/clients/{client}/payments
PUT    /api/payments/{payment}
DELETE /api/payments/{payment}
```

### Create payment request

```json
{
  "visit_id": 55,
  "payment_date": "2026-05-10",
  "amount": 500000,
  "payment_method": "cash",
  "notes": "First payment"
}
```

---

## 8.7 Doctor Schedule Routes

```php
GET /api/doctors/{doctor}/schedule
PUT /api/doctors/{doctor}/schedule
```

### Response

```json
{
  "doctor_id": 2,
  "start_time": "09:00",
  "end_time": "17:00",
  "slot_minutes": 30,
  "working_days": ["monday", "tuesday", "wednesday", "thursday", "saturday"]
}
```

### Update request

```json
{
  "start_time": "09:00",
  "end_time": "17:00",
  "slot_minutes": 30,
  "working_days": ["monday", "tuesday", "wednesday", "thursday", "saturday"]
}
```

---

## 8.8 Doctor Availability Routes

هذه مهمة جداً للواجهة.

```php
GET /api/doctors/{doctor}/availability?date=2026-05-10
GET /api/doctors/{doctor}/available-start-times?date=2026-05-10&duration_minutes=30
GET /api/doctors/{doctor}/available-durations?date=2026-05-10&start_time=10:00
```

### Availability response

```json
{
  "doctor_id": 2,
  "date": "2026-05-10",
  "slots": [
    {
      "time": "09:00",
      "status": "free",
      "appointment": null
    },
    {
      "time": "09:30",
      "status": "filled",
      "appointment": {
        "id": 10,
        "type": "booked",
        "status": "scheduled",
        "client_id": 5,
        "client_name": "Mohammad",
        "doctor_id": 2,
        "doctor_name": "Dr. Layan",
        "date": "2026-05-10",
        "start_time": "09:30",
        "duration_minutes": 60,
        "notes": "Follow-up",
        "action_state": "manage"
      }
    }
  ]
}
```

### Available start times response

```json
{
  "doctor_id": 2,
  "date": "2026-05-10",
  "duration_minutes": 60,
  "start_times": ["09:00", "12:00", "12:30"]
}
```

### Available durations response

```json
{
  "doctor_id": 2,
  "date": "2026-05-10",
  "start_time": "10:00",
  "durations": [
    { "value": 30, "available": true },
    { "value": 60, "available": false },
    { "value": 90, "available": false }
  ]
}
```

---

## 8.9 Appointments Routes

```php
GET    /api/appointments
POST   /api/appointments
GET    /api/appointments/{appointment}
PUT    /api/appointments/{appointment}
DELETE /api/appointments/{appointment}
```

### Create booked appointment request

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

### Create unavailable slot request

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

### Response for appointment item

```json
{
  "id": 10,
  "uuid": "....",
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
  "action_state": "manage"
}
```

---

## 9. الـ Form Requests المطلوبة

أنشئ Form Requests على الأقل:

- `LoginRequest`
- `StoreUserRequest`
- `UpdateUserRequest`
- `StoreClientRequest`
- `UpdateClientRequest`
- `UpdateTreatmentRecordRequest`
- `StoreVisitRequest`
- `UpdateVisitRequest`
- `StorePaymentRequest`
- `UpdatePaymentRequest`
- `UpdateDoctorScheduleRequest`
- `StoreAppointmentRequest`
- `UpdateAppointmentRequest`
- `CheckInAppointmentRequest`
- `NoShowAppointmentRequest`

---

## 10. Validation Rules المهمة

### Appointment validations

- `doctor_id` required exists
- `client_id` required if `type = booked`
- `client_id` nullable if `type = unavailable`
- `type` in: `booked, unavailable`
- `status` in: `scheduled, completed, no_show, cancelled`
- `date` required date
- `start_time` required time
- `duration_minutes` required integer in `30,60,90`
- يجب منع التداخل مع أي appointment آخر
- يجب منع الخروج عن ساعات الدوام

### Visit validations

- `client_id` required
- `doctor_id` required
- `visit_date` required date
- `appointment_id` nullable exists
- `duration_minutes` nullable integer

### Payment validations

- `client_id` required
- `amount` required numeric min 0.01
- `payment_method` in `cash, card, bank_transfer`

### Treatment record validations

- `teeth.*.tooth_number` required
- `teeth.*.treatment_catalog_id` required exists
- `teeth.*.unit_price` required numeric

---

## 11. الـ Resources المطلوبة

أنشئ:

- `UserResource`
- `ClientResource`
- `ClientListResource`
- `TreatmentRecordResource`
- `VisitResource`
- `PaymentResource`
- `DoctorScheduleResource`
- `AppointmentResource`
- `AvailabilityResource`

يفضل أن يحتوي `AppointmentResource` على:

- `action_state`
- `is_past`
- `is_future`
- `is_within_one_hour`

---

## 12. Service Classes المقترحة

يفضل إنشاء Services بدل وضع كل شيء في Controller:

- `AppointmentConflictService`
- `DoctorAvailabilityService`
- `AppointmentActionStateService`
- `ClientFinancialSummaryService`
- `TreatmentRecordService`

---

## 13. Financial Summary المطلوب للعميل

يفضل أن endpoint `GET /api/clients/{client}` أو endpoint مستقل يعيد:

```json
{
  "financial_summary": {
    "total_services_amount": 4500000,
    "total_paid_amount": 1400000,
    "remaining_amount": 3100000
  }
}
```

طريقة الحساب:
- `total_services_amount`: مجموع أسعار الأسنان المختارة في `treatment_record_teeth`
- `total_paid_amount`: مجموع المدفوعات
- `remaining_amount`: الفرق بينهما

---

## 14. Response format المقترح

يفضل توحيد الشكل:

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
    "start_time": [
      "This appointment conflicts with an existing appointment."
    ]
  }
}
```

### Business Rule Conflict

يفضل `422`:

```json
{
  "message": "Appointment conflict detected.",
  "errors": {
    "appointment": [
      "The selected doctor already has a booked or unavailable slot that overlaps with this time range."
    ]
  }
}
```

---

## 15. Example Scenarios that backend must support

### Scenario 1
- doctor works Saturday 09:00 -> 17:00
- booked appointment exists at 11:00 for 30 min
- user tries to add unavailable slot starting 10:00 for 90 min
- result must be rejected بسبب التضارب

### Scenario 2
- start time 10:00 selected
- next slot 10:30 filled
- duration options should allow only 30

### Scenario 3
- appointment at 09:00
- current time becomes 08:00 or later
- `action_state` should become `checkin`

### Scenario 4
- check-in endpoint called
- visit created
- appointment status becomes `completed`
- appointment no longer appears as scheduled

---

## 16. المطلوب من فريق الـ Backend بعد الانتهاء

بعد بناء الـ backend كاملاً، الرجاء إعادة ملف أو رسالة تحتوي:

1. جميع أسماء الجداول النهائية
2. جميع أسماء الـ migrations
3. جميع أسماء الـ models
4. جميع أسماء الـ controllers
5. جميع الـ API routes
6. جميع request validation rules المهمة
7. أمثلة كاملة لكل endpoint

مهم جداً:
لكل endpoint أريد أمثلة:

- Request example
- Required fields
- Optional fields
- Example success response
- Example validation error response
- مثال على query parameters إن وجدت

أمثلة مطلوبة خصوصاً لهذه endpoints:

- `POST /api/auth/login`
- `GET /api/clients`
- `GET /api/clients/{client}`
- `PUT /api/clients/{client}`
- `GET /api/clients/{client}/treatment-record`
- `PUT /api/clients/{client}/treatment-record`
- `GET /api/clients/{client}/visits`
- `POST /api/clients/{client}/visits`
- `PUT /api/visits/{visit}`
- `DELETE /api/visits/{visit}`
- `GET /api/clients/{client}/payments`
- `POST /api/clients/{client}/payments`
- `PUT /api/payments/{payment}`
- `DELETE /api/payments/{payment}`
- `GET /api/doctors/{doctor}/schedule`
- `PUT /api/doctors/{doctor}/schedule`
- `GET /api/doctors/{doctor}/availability`
- `GET /api/doctors/{doctor}/available-start-times`
- `GET /api/doctors/{doctor}/available-durations`
- `GET /api/appointments`
- `POST /api/appointments`
- `PUT /api/appointments/{appointment}`
- `DELETE /api/appointments/{appointment}`
- `POST /api/appointments/{appointment}/check-in`
- `POST /api/appointments/{appointment}/no-show`

---

## 17. ملاحظات أخيرة مهمة جداً

- لا تعتمدوا على التحقق من الفرونت فقط
- منع التضارب يجب أن يكون في الـ backend أيضاً
- يفضل استخدام Transactions عند:
  - check-in
  - no-show
  - تحديث treatment record مع teeth
- يفضل استخدام soft deletes
- يفضل إرجاع `action_state` جاهزاً من الـ backend
- يفضل إرجاع `next_appointment` في client list مباشرة لتسهيل الواجهة

---

## 18. Prompt جاهز لإرساله إلى فريق الـ Backend

يمكنك إرسال النص التالي كما هو:

```text
أريد منك بناء Backend كامل بلغة Laravel 11 لهذا المشروع الطبي الخاص بعيادة الأسنان.

المطلوب:
1. إنشاء قاعدة بيانات MySQL كاملة.
2. إنشاء جميع migrations المطلوبة.
3. إنشاء models مع العلاقات الصحيحة.
4. إنشاء controllers و form requests و API resources.
5. استخدام Laravel Sanctum للمصادقة.
6. بناء REST API كامل.
7. تطبيق جميع قواعد منع تضارب المواعيد داخل الـ backend وليس في الفرونت فقط.
8. دعم نوعين من المواعيد داخل جدول واحد:
   - booked
   - unavailable
9. دعم حالات الموعد:
   - scheduled
   - completed
   - no_show
   - cancelled
10. عند وصول الموعد إلى ساعة قبل البداية، يجب أن يحسب النظام action_state = checkin.
11. عند check-in يجب إنشاء visit وربطها بالموعد وتحديث حالة الموعد إلى completed.
12. عند no-show يجب إنشاء visit وربطها بالموعد وتحديث حالة الموعد إلى no_show.
13. يجب توفير endpoints لحساب:
   - available start times
   - available durations
   - day availability slots
14. إذا كانت مدة 60 أو 90 دقيقة تسبب تضارباً مع slots تالية، يجب رفضها أو اعتبارها غير متاحة.
15. يجب إعادة next_appointment لكل client.
16. يجب حساب financial summary لكل client:
   - total_services_amount
   - total_paid_amount
   - remaining_amount
17. يجب حفظ الأسنان المختارة في جدول تفصيلي وليس JSON فقط.

قم باتباع المواصفات الموجودة في الملف BACKEND_API_SPEC.md المرفق.

بعد الانتهاء أريد منك أن ترسل لي:
- جميع أسماء الجداول
- جميع أسماء الميغريشن
- جميع أسماء الـ models
- جميع أسماء الـ controllers
- جميع الـ routes
- جميع الحقول المطلوبة والاختيارية لكل endpoint
- Examples كاملة للـ request والـ response
- أمثلة validation errors

أريد الرد النهائي بشكل منظم وواضح لكي أستخدمه مباشرة في ربط الـ frontend مع الـ backend.
```

