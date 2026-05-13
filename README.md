<h1 align="center">IFGF Taiwan — ChurchCRM</h1>

<p align="center">
  Church Management System for <strong>IFGF Taipei & Zhongli</strong><br>
  Member registry · iCare attendance · QR check-in · Birthday calendar · Member portal
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-brightgreen.svg" alt="MIT License"></a>
  <a href="https://github.com/ifgf-tpe/CRM/releases"><img src="https://img.shields.io/github/v/release/churchcrm/crm?label=ChurchCRM%20Base" alt="Base version"></a>
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white" alt="MySQL 8">
  <img src="https://img.shields.io/badge/framework-Slim%204-3F4F75" alt="Slim 4">
</p>

---

## Table of Contents

<!-- toc -->

- [Introduction](#introduction)
- [Execution Status](#execution-status)
- [Minimum Requirements](#minimum-requirements)
- [System Architecture](#system-architecture)
  - [Component Overview](#component-overview)
  - [Software Stack](#software-stack)
- [Use Case Diagram](#use-case-diagram)
- [Workflows (Sequence Diagrams)](#workflows-sequence-diagrams)
  - [UC1: QR Code Attendance Check-In](#uc1-qr-code-attendance-check-in)
  - [UC2: New Member Registration](#uc2-new-member-registration)
  - [UC3: iCare Attendance with Photo](#uc3-icare-attendance-with-photo)
- [Class Diagram](#class-diagram)
  - [Domain Model](#domain-model)
  - [Service Layer](#service-layer)
- [Entity Relationship Diagram](#entity-relationship-diagram)
  - [Entity Overview](#entity-overview)
  - [Member (person\_per)](#member-person_per)
  - [iCare Group (group\_grp)](#icare-group-group_grp)
  - [iCare Meeting (icare\_meeting)](#icare-meeting-icare_meeting)
  - [iCare Member Attendance (icare\_attendance)](#icare-member-attendance-icare_attendance)
  - [iCare Visitor (icare\_visitor)](#icare-visitor-icare_visitor)
  - [Event (events\_event)](#event-events_event)
  - [Event Attendance (event\_attend)](#event-attendance-event_attend)
  - [Event Count (eventcounts\_evtcnt)](#event-count-eventcounts_evtcnt)
  - [Business Rules](#business-rules)
- [API Reference](#api-reference)
- [Installation](#installation)
- [Usage Guides](#usage-guides)
- [Contributing](#contributing)
- [References](#references)

<!-- tocstop -->

---

## Introduction

ChurchCRM is an open-source church management platform, adapted here for **IFGF Taipei & Zhongli** — an Indonesian Fellowship of God's Family congregation in Taiwan.

1. **Background**
   - Members are currently tracked across three separate systems: a Google Apps Script spreadsheet project (`church-member-management`), a FastAPI web service (`IFGF-Web-Server`), and a React admin frontend (`IFGF-Web-Admin`).
   - Data is fragmented; birthday reminders, QR attendance, iCare tracking, and member communication each live in different tools.

2. **Importance**
   - Consolidating into ChurchCRM gives the church a single source of truth: full member profiles, weekly Sunday attendance via personal QR codes, iCare cell-group attendance with photo evidence, birthday calendar, and a public member portal — all in one deployed PHP application.

3. **Contribution**
   - IFGF-specific extensions built on top of ChurchCRM 7.x:
     - **Static QR code per member** (HMAC-signed, linked to person ID only) for frictionless Sunday attendance scanning.
     - **iCare attendance module** (`icare_meeting`, `icare_attendance`, `icare_visitor` tables) with compressed photo upload (≤ 300 KB enforced server-side).
     - **Welcome email** with inline QR code sent automatically on member creation.
     - **Public member portal** (`/external/member-portal`) for QR code resend and self-registration.

4. **Challenges**
   - **Schema extension without breaking upstream**: New iCare tables use isolated FK constraints; no upstream ChurchCRM tables were modified.
   - **Mobile photo upload**: Photos from Android/iPhone are compressed server-side (GD library, quality reduction loop) to always produce ≤ 300 KB output regardless of the original camera resolution.
   - **Birthday calendar**: ChurchCRM's built-in `BirthdaysCalendar` surfaces birthdays inside the app; Google Calendar push requires a service-account OAuth integration (planned).

---

## Execution Status

| Feature | Status | Date | Notes |
| --- | --- | --- | --- |
| Base ChurchCRM 7.x install | ✅ | 2026-05-04 | Upstream release `b56406a` |
| Member QR code (static, HMAC-signed) | ✅ | 2026-05-13 | `QrCodeService`, `/external/checkin` |
| Welcome email with inline QR | ✅ | 2026-05-13 | `WelcomeMemberEmail`, `bSendWelcomeEmail` setting |
| Member self-service portal | ✅ | 2026-05-13 | `/external/member-portal`, QR resend by email |
| iCare meeting + attendance tables | ✅ | 2026-05-13 | `7.4.0-icare.sql` migration |
| iCare attendance + photo upload (≤ 300 KB) | ✅ | 2026-05-13 | `ICareService::saveMeetingPhoto()` |
| iCare visitor registration | ✅ | 2026-05-13 | `icare_visitor` table |
| iCare attendance UI (leader view) | ⏳ | — | Routes scaffolded; views in progress |
| Birthday → Google Calendar push | ⏳ | — | Needs Google Calendar API + service account |
| Google OAuth login for members | ⏳ | — | Requires `league/oauth2-google` + `per_google_sub` column |
| Member self-service profile edit | ⏳ | — | Blocked by Google OAuth |
| iCare co-leader role | ⏳ | — | Schema and UI not yet built |
| CGSL group tracking | ⏳ | — | Planned; no timeline |
| Fingerprint device TSV import | ⏳ | — | Planned; no timeline |

---

## Minimum Requirements

| Component | Requirement |
| --- | --- |
| CPU | 2-core 2 GHz (ARM or x86) |
| RAM | 1 GB minimum, 2 GB recommended |
| Storage | 10 GB for application + media |
| PHP | 8.4+ with GD, mbstring, pdo_mysql, json, intl |
| MySQL / MariaDB | MySQL 8.0+ or MariaDB 10.11+ |
| Web server | Apache 2.4+ (mod_rewrite) or nginx 1.24+ |
| Node.js | 24 LTS (build only — not needed at runtime) |
| Composer | 2.x |
| Docker | 24+ (optional — recommended for local dev via DDEV) |

**Recommended local development:** [DDEV](https://ddev.readthedocs.io/) — installs PHP, MariaDB, Apache, and Node automatically via Docker.

---

## System Architecture

### Component Overview

```mermaid
graph TD
    Browser["Admin Browser\n(Tabler / Bootstrap 5 UI)"]
    Phone["Church Member\n(Android / iPhone)"]
    Portal["Public Member Portal\n/external/member-portal"]
    Checkin["QR Check-in\n/external/checkin"]
    CRM["ChurchCRM PHP App\n(Slim 4 MVC)"]
    DB[(MySQL 8\nchurchcrm DB)]
    SMTP["SMTP Server\n(outgoing email)"]
    GCalendar["Google Calendar API\n(planned)"]
    QRApi["api.qrserver.com\n(QR PNG generation)"]

    Browser -->|HTTPS| CRM
    Phone -->|Scan QR → HTTPS| Checkin
    Phone -->|Upload photo| CRM
    Portal -->|email lookup + resend| CRM
    Checkin --> CRM
    CRM --> DB
    CRM -->|Welcome email + QR| SMTP
    CRM -->|Fetch QR PNG| QRApi
    CRM -.->|Birthday events (planned)| GCalendar
```

### Software Stack

| Layer | Technology | Version |
| --- | --- | --- |
| Language | PHP | 8.4 |
| HTTP framework | Slim 4 | 4.x |
| ORM | Propel 2 | 2.x |
| Templating | Twig (email) / PHP views | 3.x |
| Email | PHPMailer | 6.x |
| Frontend CSS | Tabler + Bootstrap 5 | 1.x / 5.x |
| JS bundler | Webpack 5 + TypeScript | 5.x |
| Linter | Biome | 2.x |
| Database | MySQL / MariaDB | 8.0 / 10.11 |
| Dev environment | DDEV | 1.24+ |
| CI testing | Cypress | 15.x |

---

## Use Case Diagram

```mermaid
graph LR
    Admin(["👤 Admin"])
    Leader(["👥 iCare Leader"])
    Member(["📱 Church Member"])

    subgraph "Admin Functions"
        UC_AddMember["Add / Edit Member"]
        UC_ViewAttend["View Attendance Reports"]
        UC_ManageGroups["Manage iCare Groups"]
        UC_Settings["System Settings"]
    end

    subgraph "iCare Leader Functions"
        UC_RecordIcare["Record iCare Attendance"]
        UC_UploadPhoto["Upload Meeting Photo"]
        UC_AddVisitor["Register Visitor"]
    end

    subgraph "Member Functions (Public Portal)"
        UC_ScanQR["Scan QR → Check In"]
        UC_ResendQR["Request QR via Email"]
        UC_Register["Self-Register"]
    end

    Admin --> UC_AddMember
    Admin --> UC_ViewAttend
    Admin --> UC_ManageGroups
    Admin --> UC_Settings
    Leader --> UC_RecordIcare
    Leader --> UC_UploadPhoto
    Leader --> UC_AddVisitor
    Member --> UC_ScanQR
    Member --> UC_ResendQR
    Member --> UC_Register

    UC_AddMember -.->|triggers| UC_ResendQR
```

---

## Workflows (Sequence Diagrams)

### UC1: QR Code Attendance Check-In

```mermaid
sequenceDiagram
    actor M as Church Member (Phone)
    participant QR as QR Code
    participant EXT as /external/checkin
    participant SVC as QrCodeService
    participant DB as Database

    M->>QR: Scan personal QR code with camera
    QR-->>M: Opens browser → /external/checkin?pid=X&token=Y
    M->>EXT: GET /external/checkin?pid=X&token=Y
    EXT->>SVC: verifyToken(pid, token)
    SVC-->>EXT: valid / invalid
    alt Invalid token
        EXT-->>M: Error page — invalid QR code
    else Valid token
        EXT->>DB: Find today's church event
        alt No event today
            EXT-->>M: "No event scheduled today"
        else Event found
            EXT->>DB: Event::checkInPerson(pid)
            DB-->>EXT: Attendance recorded
            EXT-->>M: ✅ "You're checked in, [Name]!"
        end
    end
```

### UC2: New Member Registration

```mermaid
sequenceDiagram
    actor A as Admin
    participant UI as ChurchCRM UI
    participant P as Person (Model)
    participant WE as WelcomeMemberEmail
    participant QS as QrCodeService
    participant SMTP as SMTP Server

    A->>UI: Fill member form → Save
    UI->>P: Person::save()
    P->>P: postInsert() hook fires
    P->>WE: WelcomeMemberEmail::sendIfEnabled(person)
    alt bSendWelcomeEmail = ON && email present
        WE->>QS: getPersonCheckInUrl(person)
        QS-->>WE: https://church.example.com/external/checkin?pid=X&token=Y
        WE->>QS: fetchQrCodePng(url, 300px)
        QS-->>WE: PNG bytes (from api.qrserver.com)
        WE->>SMTP: Send email with inline QR image
        SMTP-->>A: Email delivered to member
    end
    UI-->>A: Member saved ✅
```

### UC3: iCare Attendance with Photo

```mermaid
sequenceDiagram
    actor L as iCare Leader (Phone)
    participant UI as iCare Attendance Page
    participant SVC as ICareService
    participant GD as GD Library (PHP)
    participant FS as File System
    participant DB as Database

    L->>UI: Select group + date, check members present
    L->>UI: Take / upload group photo (JPEG/PNG/WebP)
    UI->>UI: Browser: compress to canvas (optional client-side)
    UI->>SVC: POST base64 image + attendance list
    SVC->>GD: imagecreatefromstring(data)
    GD->>GD: Resize to max 1200×900 px
    loop Reduce quality until ≤ 300 KB
        GD->>GD: imagejpeg(dest, null, quality)
        Note over GD: Start at quality=82, step -5, floor=25
    end
    GD-->>SVC: JPEG bytes ≤ 300 KB
    SVC->>FS: file_put_contents(meeting-ID.jpg)
    SVC->>DB: INSERT icare_meeting (group, date, photo_filename)
    SVC->>DB: INSERT icare_attendance × N (meeting_id, person_id)
    DB-->>SVC: OK
    SVC-->>L: ✅ Attendance recorded + photo saved
```

---

## Class Diagram

### Domain Model

```mermaid
classDiagram
    direction LR

    class Person {
        +int per_ID
        +string per_FirstName
        +string per_LastName
        +string per_Email
        +string per_CellPhone
        +int per_BirthDay
        +int per_BirthMonth
        +int per_BirthYear
        +int per_cls_ID
        +int per_fam_ID
        +DateTime per_DateEntered
        +getBirthDate() DateTimeImmutable
        +getFullName() string
        +postInsert() void
        +postUpdate() void
    }

    class Family {
        +int fam_ID
        +string fam_Name
        +string fam_Address1
        +string fam_City
        +string fam_Email
        +postInsert() void
    }

    class Group {
        +int grp_ID
        +string grp_Name
        +int grp_Type
        +bool grp_active
        +int grp_RoleListID
    }

    class Person2group2role {
        +int p2g2r_per_ID
        +int p2g2r_grp_ID
        +int p2g2r_rle_ID
    }

    class IcareMeeting {
        +int id
        +int group_id
        +Date meeting_date
        +string location
        +string notes
        +string photo_filename
        +int created_by
        +DateTime created_at
    }

    class ICareAttendance {
        +int id
        +int meeting_id
        +int person_id
        +DateTime recorded_at
    }

    class ICareVisitor {
        +int id
        +int meeting_id
        +string full_name
        +string phone
        +string instagram
        +string address
        +DateTime created_at
    }

    class Event {
        +int event_id
        +int event_type
        +string event_title
        +DateTime event_start
        +DateTime event_end
        +checkInPerson(personId, checkedInById) array
        +checkOutPerson(personId, checkedOutById) array
    }

    class EventAttend {
        +int attend_id
        +int event_id
        +int person_id
        +DateTime checkin_date
        +DateTime checkout_date
    }

    class EventCount {
        +int evtcnt_eventid
        +string evtcnt_countname
        +int evtcnt_countcount
        +string evtcnt_notes
    }

    class User {
        +int usr_ID
        +string usr_Login
        +string usr_Password
        +int usr_per_ID
        +bool isAdmin() bool
    }

    class KioskDevice {
        +int kdev_ID
        +string kdev_GUIDHash
        +bool kdev_Accepted
        +string kdev_Name
    }

    %% Relationships
    Person "N" --> "1" Family : belongs to
    Person "N" --> "N" Group : via Person2group2role
    Person2group2role --> Person
    Person2group2role --> Group

    IcareMeeting "N" --> "1" Group : held by
    ICareAttendance "N" --> "1" IcareMeeting : records
    ICareAttendance "N" --> "1" Person : attends
    ICareVisitor "N" --> "1" IcareMeeting : visits

    EventAttend "N" --> "1" Event : checks into
    EventAttend "N" --> "1" Person : who
    EventCount "N" --> "1" Event : aggregates

    User --> Person : linked to
```

### Service Layer

```mermaid
classDiagram
    direction TB

    class QrCodeService {
        -string QR_API
        +getPersonCheckInUrl(person) string$
        +fetchQrCodePng(url, sizePx) string$
        +generateToken(personId) string$
        +verifyToken(personId, token) bool$
        -getSecret() string$
    }

    class ICareService {
        -int PHOTO_MAX_WIDTH
        -int PHOTO_MAX_HEIGHT
        -int PHOTO_QUALITY
        -int PHOTO_MAX_BYTES
        +getGroupsForUser(userId) array
        +getGroupMembers(groupId) array
        +getMeetingsForGroup(groupId, limit) array
        +getMeetingDetail(meetingId) array
        +createMeeting(groupId, userId, data) IcareMeeting
        +saveMeetingPhoto(meetingId, base64DataUri) string
        +getMeetingPhotoPath(meetingId) string
        +deleteMeeting(meetingId) void
    }

    class WelcomeMemberEmail {
        -Person person
        -string checkInUrl
        -string qrCid
        +sendIfEnabled(person) void$
        +getTokens() array
        +getSubSubject() string
        +getPreheader() string
    }

    class PersonService {
        +addVolunteerOpportunity(personId, opportunityId) bool
    }

    class EventService {
        +createRepeatEvents(data) int[]
    }

    class GroupService {
        +getGroupRoles() array
    }

    QrCodeService ..> Person : generates URL for
    WelcomeMemberEmail ..> QrCodeService : uses
    WelcomeMemberEmail ..> Person : sends to
    ICareService ..> IcareMeeting : manages
    ICareService ..> ICareAttendance : records
    ICareService ..> ICareVisitor : registers
```

---

## Entity Relationship Diagram

This section documents the database entity-relationship model used to manage church membership, event attendance, and iCare (cell group) activity for IFGF Taiwan.
Source of truth: `Jemaat & Absensi.xlsx` (sheets: *Daftar Jemaat*, *Daftar Absensi*, *Absensi iCare*, *Absen-TPE/ZL*).

### Entity Overview

| Entity | DB Table | Purpose |
| --- | --- | --- |
| **Member** | `person_per` | Church member profile |
| **iCare Group** | `group_grp` (type = iCare) | Cell group definition |
| **iCare Meeting** | `icare_meeting` | One weekly iCare session |
| **iCare Member Attendance** | `icare_attendance` | Existing members at an iCare session |
| **iCare Visitor** | `icare_visitor` | Walk-in non-members at iCare |
| **Event** | `events_event` | All church events (Super Sunday, Worship Night, etc.) |
| **Event Attendance** | `event_attend` | Individual member check-in via QR code |
| **Event Count** | `eventcounts_evtcnt` | Aggregate headcount per event session |

```mermaid
erDiagram
    person_per {
        int per_ID PK
        varchar per_FirstName
        varchar per_LastName
        varchar per_Email
        varchar per_CellPhone
        tinyint per_BirthDay
        tinyint per_BirthMonth
        smallint per_BirthYear
        tinyint per_cls_ID
        smallint per_fam_ID
    }

    family_fam {
        int fam_ID PK
        varchar fam_Name
        varchar fam_Address1
        varchar fam_City
    }

    group_grp {
        smallint grp_ID PK
        varchar grp_Name
        tinyint grp_Type
        bool grp_active
    }

    person2group2role_p2g2r {
        int p2g2r_per_ID FK
        smallint p2g2r_grp_ID FK
        smallint p2g2r_rle_ID
    }

    icare_meeting {
        int id PK
        smallint group_id FK
        date meeting_date
        varchar location
        text notes
        varchar photo_filename
        int created_by
        timestamp created_at
    }

    icare_attendance {
        int id PK
        int meeting_id FK
        int person_id FK
        timestamp recorded_at
    }

    icare_visitor {
        int id PK
        int meeting_id FK
        varchar full_name
        varchar phone
        varchar instagram
        varchar address
        timestamp created_at
    }

    events_event {
        int event_id PK
        int event_type FK
        varchar event_title
        timestamp event_start
        timestamp event_end
    }

    event_attend {
        int attend_id PK
        int event_id FK
        int person_id FK
        timestamp checkin_date
        timestamp checkout_date
    }

    eventcounts_evtcnt {
        int evtcnt_eventid FK
        varchar evtcnt_countname
        int evtcnt_countcount
    }

    person_per }|--|| family_fam : "belongs to"
    person2group2role_p2g2r }|--|| person_per : "member"
    person2group2role_p2g2r }|--|| group_grp : "group"
    icare_meeting }|--|| group_grp : "held by"
    icare_attendance }|--|| icare_meeting : "session"
    icare_attendance }|--|| person_per : "who"
    icare_visitor }|--|| icare_meeting : "visits"
    event_attend }|--|| events_event : "event"
    event_attend }|--|| person_per : "who"
    eventcounts_evtcnt }|--|| events_event : "aggregates"
```

### Member (`person_per`)

Represents every registered church member (jemaat). Sourced from the **Daftar Jemaat** registration form.

| Column | Type | Notes |
| --- | --- | --- |
| `per_ID` | INT PK | Auto-increment |
| `per_FirstName` | VARCHAR(50) | Full name (Latin script) |
| `per_LastName` | VARCHAR(50) | — |
| `per_Email` | VARCHAR(50) | Primary contact / login |
| `per_CellPhone` | VARCHAR(30) | WhatsApp number |
| `per_Facebook` | VARCHAR(50) | LINE ID (re-used field) |
| `per_Twitter` | VARCHAR(50) | Instagram handle |
| `per_BirthDay/Month/Year` | TINYINT/SMALLINT | Date of birth |
| `per_Address1` | VARCHAR(50) | Taiwan domicile area |
| `per_Address2` | VARCHAR(50) | Indonesian domicile |
| `per_cls_ID` | TINYINT | **Kategori**: Adult / College / Teens & Youth / Kids |
| `per_fam_ID` | SMALLINT FK → `family_fam` | Church location family grouping |
| `per_DateEntered` | TIMESTAMP | Registration timestamp |
| *(custom)* | — | Chinese name (中文名) |
| *(custom)* | — | Profession (Siswa / Mahasiswa / Pekerja) |
| *(custom)* | — | Education level |
| *(custom)* | — | IFGF church location (Taipei / Zhongli) |
| *(custom)* | — | Baptized (Y/N) |
| *(custom)* | — | Indonesian home church |

**Member Categories (`per_cls_ID`):**

| Kategori | Condition |
| --- | --- |
| Kids | SD (elementary school) |
| Teens and Youth | Siswa/SMP or SMA/SMK |
| College | Siswa/Mahasiswa + S1 or Diploma |
| Adult | Pekerja (worker) |

### iCare Group (`group_grp`)

| Column | Type | Notes |
| --- | --- | --- |
| `grp_ID` | SMALLINT PK | Auto-increment |
| `grp_Name` | VARCHAR(50) | e.g., "iCare TMS", "iCare Keelung" |
| `grp_Type` | TINYINT | Group type ID for iCare |
| `grp_active` | BOOLEAN | Active / disbanded |
| `grp_RoleListID` | SMALLINT | Roles: Leader, Member |

**Known groups:** `iCare TMS` · `iCare U` · `iCare Keelung` · `iCare Linkou` · `iCare Immanuel` · `iCare Freshcare` · `iCare Tamkang` · `iCare Hsinchu` · `iCare Liming` · `iCare Home`

**Member → iCare via `person2group2role_p2g2r`:**

| Column | Type | Notes |
| --- | --- | --- |
| `p2g2r_per_ID` | FK → `person_per` | Member |
| `p2g2r_grp_ID` | FK → `group_grp` | iCare group |
| `p2g2r_rle_ID` | FK → `list_lst` | Role: Leader or Member |

### iCare Meeting (`icare_meeting`)

One row per weekly iCare session.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | INT PK | Auto-increment |
| `group_id` | SMALLINT FK → `group_grp` | Which iCare group |
| `meeting_date` | DATE | Date of the session |
| `location` | VARCHAR(255) | Physical venue (optional) |
| `notes` | TEXT | Leader notes |
| `photo_filename` | VARCHAR(255) | Group photo — JPEG, ≤ 300 KB |
| `created_by` | INT FK → user | CRM user who recorded attendance |
| `created_at` | TIMESTAMP | |

### iCare Member Attendance (`icare_attendance`)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | INT PK | Auto-increment |
| `meeting_id` | INT FK → `icare_meeting` | Which session |
| `person_id` | INT FK → `person_per` | Attending member |
| `recorded_at` | TIMESTAMP | |
| **UNIQUE** | (`meeting_id`, `person_id`) | No duplicate check-ins |

### iCare Visitor (`icare_visitor`)

Tracks **new walk-in visitors** (not yet members) who come to an iCare session.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | INT PK | Auto-increment |
| `meeting_id` | INT FK → `icare_meeting` | Session visited |
| `full_name` | VARCHAR(100) | Full name |
| `phone` | VARCHAR(30) | WhatsApp / phone |
| `instagram` | VARCHAR(100) | Instagram or social media handle |
| `address` | VARCHAR(255) | Residential area (Taiwan) |
| `created_at` | TIMESTAMP | |

### Event (`events_event`)

| Event Type | Examples | Location |
| --- | --- | --- |
| **Super Sunday** | Weekly Sunday Gathering | Taipei (TPE) / Zhongli (ZL) |
| **iCare** | Weekly cell group meeting | Per-group venue |
| **Worship Night** | School-based, volunteer, open | Taipei / Zhongli |
| **Special Event** | Passover, Christmas, Retreat | Taipei / Zhongli / Offsite |

| Column | Type | Notes |
| --- | --- | --- |
| `event_id` | INT PK | Auto-increment |
| `event_type` | INT FK → `event_types` | Event category |
| `event_title` | VARCHAR(255) | e.g., "Super Sunday — Taipei" |
| `event_start` | TIMESTAMP | |
| `event_end` | TIMESTAMP | |
| `inactive` | INT | 0 = active |

### Event Attendance (`event_attend`)

Records individual member check-ins via personal QR code.

| Column | Type | Notes |
| --- | --- | --- |
| `attend_id` | INT PK | Auto-increment |
| `event_id` | INT FK → `events_event` | Which event |
| `person_id` | INT FK → `person_per` | Who checked in |
| `checkin_date` | TIMESTAMP | Scan time |
| `checkout_date` | TIMESTAMP | Optional checkout |
| **UNIQUE** | (`event_id`, `person_id`) | One check-in per person per event |

### Event Count (`eventcounts_evtcnt`)

Aggregate headcount per event, broken down by Kategori.

| Column | Type | Notes |
| --- | --- | --- |
| `evtcnt_eventid` | INT FK → `events_event` | Which event |
| `evtcnt_countname` | VARCHAR(20) | Adult / College / Teens & Youth / Kids / Online |
| `evtcnt_countcount` | INT | Number of attendees |
| `evtcnt_notes` | VARCHAR(20) | Reporter name |

### Business Rules

1. **Primary iCare**: Every member is assigned to exactly one primary iCare group via `person2group2role_p2g2r`. `"Belum mengikuti"` means not yet assigned.
2. **Cross-iCare visits**: A member may attend any iCare meeting — `icare_attendance` is not restricted to their primary group.
3. **New visitors at iCare**: Stored in `icare_visitor` (lightweight record). After follow-up, they can be promoted to a full member and assigned an iCare group.
4. **Sunday attendance**: Captured by QR code scan → `event_attend`. Aggregate counts (Adult/College/Youth/Kids/Online) also recorded in `eventcounts_evtcnt`.
5. **Location split**: Super Sunday runs in **Taipei (TPE)** and **Zhongli (ZL)** independently — each is a separate `events_event` row.
6. **QR code stability**: The QR code encodes `HMAC-SHA256(personId, secret)` and never changes when member details change. Only re-generated on explicit member request.
7. **Photo size**: iCare meeting photos are compressed server-side to ≤ 300 KB (JPEG, max 1200×900 px) regardless of the original uploaded resolution.

---

## API Reference

### Public Endpoints (no login required)

| Method | Path | Description |
| --- | --- | --- |
| GET | `/external/member-portal` | Member self-service portal page |
| POST | `/external/member-portal/resend-qr` | Email QR code to member by address |
| GET | `/external/checkin?pid=X&token=Y` | QR scan → record attendance + confirmation |

### Admin Endpoints (login required)

| Method | Path | Description |
| --- | --- | --- |
| GET | `/people/view/{id}` | Member profile (includes QR code card) |
| GET/POST | `/v2/icare/{groupId}/attendance` | Record iCare attendance + photo |
| GET | `/api/people` | Member list (JSON) |
| POST | `/api/public/register/family` | Public family registration (if enabled) |

### iCare API (JSON, login required)

| Method | Path | Description |
| --- | --- | --- |
| GET | `/api/icare/groups` | Groups the current user leads |
| GET | `/api/icare/groups/{id}/members` | Members of a group |
| GET | `/api/icare/groups/{id}/meetings` | Meeting history for a group |
| POST | `/api/icare/groups/{id}/meetings` | Create meeting + record attendance |
| POST | `/api/icare/meetings/{id}/photo` | Upload/replace meeting photo |
| GET | `/api/icare/meetings/{id}` | Meeting detail + attendance list |
| DELETE | `/api/icare/meetings/{id}` | Delete meeting and photo |

---

## Installation

### Recommended: DDEV (local development)

```bash
# 1. Clone the repo
git clone https://github.com/ifgf-tpe/CRM.git churchcrm
cd churchcrm

# 2. Install dependencies
npm install        # Node packages + locale files
cd src && composer install && cd ..

# 3. Build frontend assets
npm run build

# 4. Start DDEV (handles MariaDB + Apache + PHP automatically)
ddev start

# 5. Apply iCare schema (one-time)
ddev exec mysql churchcrm < src/mysql/upgrade/7.4.0-icare.sql

# 6. Open in browser
ddev launch
# Default credentials: admin / changeme
```

### Docker Compose (production-like)

```bash
cd docker
cp .env.example .env   # edit passwords
docker compose -f docker-compose.nginx.yaml up -d
```

### Configuration

After first login: **Admin → System Settings → New Members & Greeting**

| Setting | Purpose |
| --- | --- |
| `bSendWelcomeEmail` | Send welcome email + QR code on new member creation |
| `sWelcomeEmailSubject` | Email subject line (leave blank for default) |
| `sQrCodeSecret` | HMAC signing secret for QR tokens — set to a long random string |

---

## Usage Guides

Step-by-step usage guides are in [`future-work.md`](future-work.md) → **Feature Usage Guides**:

- **Guide 1**: [Weekly Attendance via QR Code](future-work.md#guide-1-weekly-attendance-via-qr-code)
- **Guide 2**: [Member Registration Flow](future-work.md#guide-2-member-registration-flow) (birthday, QR code, welcome email)
- **Guide 3**: [Member Self-Service Portal](future-work.md#guide-3-member-self-service-portal)
- **Guide 4**: [iCare Attendance with Photo Upload](future-work.md#guide-4-icare-attendance-with-photo-upload-planned) *(planned)*
- **Guide 5**: [Google Account Login / OAuth](future-work.md#guide-5-google-account-login--oauth-planned) *(planned)*

---

## Contributing

We welcome contributions! See [CONTRIBUTING.md](CONTRIBUTING.md) for how to file issues and submit pull requests.

For IFGF Taiwan-specific features (iCare, QR attendance, member portal), open an issue describing the use case and tag it `ifgf-taiwan`.

[![GitHub contributors](https://img.shields.io/github/contributors/churchcrm/crm.svg)](https://github.com/ChurchCRM/CRM/graphs/contributors)
[![Discord](https://img.shields.io/badge/Discord-ChurchCRM-5865F2?logo=discord&logoColor=white)](https://discord.gg/tuWyFzj3Nj)

---

## References

1. ChurchCRM upstream project — [churchcrm.io](https://churchcrm.io) / [github.com/ChurchCRM/CRM](https://github.com/ChurchCRM/CRM)
2. IFGF-Web-Server (FastAPI backend) — `D:/Users/Ian Joseph/Documents/GitHub/IFGF-Web-Server`
3. IFGF-Web-Admin (React frontend) — `D:/Users/Ian Joseph/Documents/GitHub/IFGF-Web-Admin`
4. church-member-management (GAS origin) — `D:/Users/Ian Joseph/Documents/GitHub/church-member-management`
5. Slim 4 Framework — [slimframework.com](https://www.slimframework.com/)
6. Propel ORM — [propelorm.org](http://propelorm.org/)
7. Tabler UI Kit — [tabler.io](https://tabler.io/)
8. api.qrserver.com — QR code generation API used for member attendance codes
9. Project Documentation SOP Template — [bmw-ece-ntust/SOP](https://github.com/bmw-ece-ntust/SOP/blob/master/project-documentation.md)
