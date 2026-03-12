<div align="center">

<img src="https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white" />
<img src="https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white" />
<img src="https://img.shields.io/badge/Bootstrap-3.3.7_RTL-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white" />
<img src="https://img.shields.io/badge/jQuery-0769AD?style=for-the-badge&logo=jquery&logoColor=white" />
<img src="https://img.shields.io/badge/Chart.js-FF6384?style=for-the-badge&logo=chartdotjs&logoColor=white" />
<img src="https://img.shields.io/badge/License-Private-red?style=for-the-badge" />

<br/><br/>

# 📇 فهرس | Fahras

### نظام فهرسة العملاء وكشف المخالفات لشركات التقسيط الأردنية

**Client Indexing & Violation Detection System for Jordanian Installment Companies**

<br/>

</div>

---

<div dir="rtl" align="right">

## 🇯🇴 العربية

### 📖 نبذة عن المشروع

**فهرس** هو نظام متكامل لإدارة وفهرسة عملاء شركات التقسيط في السوق الأردني. يقوم النظام بالبحث عن العملاء عبر مصادر متعددة (محلية وخارجية)، واكتشاف المخالفات التعاقدية تلقائياً بين الشركات، وإدارة الغرامات والمدفوعات، مع دعم كامل للغتين العربية والإنجليزية.

---

### ✨ المميزات الرئيسية

#### 🔍 البحث الموحّد
- بحث شامل بالاسم أو الرقم الوطني أو رقم الهاتف
- استعلام متزامن من قاعدة البيانات المحلية + 4 مصادر خارجية (زاجل، جدل، نماء، بسيل)
- توصيات فورية: **يمكن البيع** / **لا يمكن البيع** / **يجب التواصل أولاً**
- نسخ التقرير كصورة عبر html2canvas

#### ⚠️ محرك كشف المخالفات
- كشف تلقائي للعملاء المكررين بين الشركات
- قواعد غرامات ذكية (20 دينار لأول 5 مخالفات، 40 دينار بعدها)
- حد أدنى للمبلغ المتبقي: 150 دينار أردني
- فحص الكفلاء والأطراف المرتبطة بالعقود
- استثناء العقود المنتهية والملغاة تلقائياً

#### 🔄 المسح والمزامنة
- مزامنة كاملة مع جميع المصادر الخارجية
- مسح شامل: محلي↔محلي، محلي↔خارجي، خارجي↔خارجي
- شريط تقدم مباشر مع سجل عمليات تفصيلي

#### 📊 التقارير والإحصائيات
- **لوحة التحكم**: إحصائيات فورية (إجمالي العملاء، الشركات، المخالفات الشهرية)
- **تقرير المبيعات**: حسب الفترة الزمنية مع رسوم بيانية
- **التقرير الشهري**: مخالفات حسب الشهر مع مقارنة الشركات
- **سجل النشاطات**: تتبع جميع العمليات (تسجيل دخول، بحث، استيراد، مسح)

#### 👥 إدارة المستخدمين والصلاحيات (RBAC)
- أدوار: مدير عام، مدير شركة، مستخدم، مشاهد
- صلاحيات دقيقة على مستوى الوحدة والإجراء
- صلاحيات مباشرة للمستخدمين بالإضافة للأدوار
- مدير الشركة يرى بيانات شركته فقط

#### 📥 الاستيراد والتصدير
- استيراد العملاء من Excel (xlsx, xls, csv)
- فحص مخالفات صامت عند كل عملية إضافة
- تصدير البيانات بصيغة CSV

#### 🌐 ثنائي اللغة
- واجهة كاملة بالعربية والإنجليزية
- نظام ترجمة ديناميكي عبر قاعدة البيانات
- دعم RTL الكامل

#### 🎨 واجهة مستخدم احترافية
- وضع داكن / فاتح مع حفظ التفضيل
- تصميم متجاوب مع Bootstrap RTL
- خط Almarai العربي
- أيقونات Font Awesome

---

### 🏗️ هيكل المشروع

</div>

```
Fahras/
├── index.php                    # إعادة توجيه → /admin
├── FahrasBaselFullAPIs.php      # واجهة API لنظام بسيل (SQL Server)
│
├── admin/                       # لوحة التحكم
│   ├── index.php                # الرئيسية + البحث الموحّد
│   ├── header.php / footer.php  # القالب الرئيسي
│   ├── login.php / logout.php   # تسجيل الدخول
│   ├── clients.php              # إدارة العملاء
│   ├── accounts.php             # إدارة الشركات
│   ├── jobs.php                 # البحث عن جهات العمل
│   ├── violations.php           # إدارة المخالفات
│   ├── scan.php / scan_ajax.php # المسح والمزامنة
│   ├── import.php               # استيراد من Excel
│   ├── users.php / roles.php    # المستخدمين والأدوار
│   ├── sales-report.php         # تقرير المبيعات
│   ├── monthly-report.php       # التقرير الشهري
│   ├── activity-log.php         # سجل النشاطات
│   ├── translate.php            # إدارة الترجمة
│   ├── attachments.php          # مرفقات العملاء
│   ├── css/                     # ملفات التنسيق
│   └── xcrud/                   # مكتبة XCRUD
│
├── includes/
│   ├── bootstrap.php            # المصادقة، CSRF، RBAC، الدوال المساعدة
│   ├── smplPDO.php              # غلاف PDO مبسّط
│   ├── violation_engine.php     # محرك كشف المخالفات
│   ├── permissions.php          # تعريف الصلاحيات
│   └── migrations/              # ترحيلات قاعدة البيانات
│
└── vendor/                      # مكتبات Composer (PhpSpreadsheet)
```

<div dir="rtl" align="right">

### 🛠️ التقنيات المستخدمة

</div>

| الطبقة | التقنية |
|--------|---------|
| **الخلفية** | PHP 8.x |
| **قاعدة البيانات** | MySQL (utf8mb4) |
| **ORM** | smplPDO (غلاف PDO) + XCRUD |
| **الواجهة** | Bootstrap 3.3.7 RTL, jQuery |
| **الرسوم البيانية** | Chart.js 3.5.1 |
| **المكونات** | Select2 4.0.3, html2canvas 1.4.1, Animate.css |
| **الخطوط** | Almarai (Google Fonts) |
| **Excel** | PhpOffice/PhpSpreadsheet |
| **API خارجي** | SQL Server (sqlsrv) لنظام بسيل |

<div dir="rtl" align="right">

### 🗄️ قاعدة البيانات

</div>

| الجدول | الوصف |
|--------|-------|
| `accounts` | الشركات (الاسم، الهاتف، العنوان، الضريبة، النوع، الحالة) |
| `users` | المستخدمين (اسم المستخدم، كلمة المرور، الدور، الحساب، اللغة) |
| `clients` | العملاء (الحساب، الاسم، الرقم الوطني، العقود، المبلغ المتبقي) |
| `violations` | المخالفات (العميل، الطرف المستحق، المخالف، الغرامة، الحالة) |
| `violation_log` | سجل تغييرات المخالفات |
| `remote_clients` | ذاكرة مؤقتة لنتائج المصادر الخارجية |
| `roles` | الأدوار |
| `permissions` | الصلاحيات (الوحدة + الإجراء) |
| `role_has_permissions` | ربط الأدوار بالصلاحيات |
| `user_has_permissions` | صلاحيات مباشرة للمستخدمين |
| `activity_log` | سجل النشاطات |
| `translate` | الترجمات (النص، عربي، إنجليزي) |
| `attachments` | مرفقات العملاء |

<div dir="rtl" align="right">

### 🔌 واجهات API الخارجية

يتصل النظام بأربعة مصادر خارجية للبحث والمزامنة:

| المصدر | الوظيفة |
|--------|---------|
| **زاجل (Zajal)** | بحث عملاء ومزامنة |
| **جدل (Jadal)** | بحث عملاء وجهات عمل |
| **نماء (Namaa)** | بحث عملاء وجهات عمل |
| **بسيل (Bseel)** | بحث عملاء، جهات عمل، مرفقات، أطراف العقود |

### 🔐 الأمان

- تشفير كلمات المرور بـ bcrypt/argon2 (ترقية تلقائية من MD5)
- حماية CSRF على النماذج وطلبات AJAX
- جلسات آمنة مع توكن + كوكيز تذكرني
- نظام صلاحيات RBAC متعدد المستويات
- تسجيل جميع النشاطات مع عنوان IP

---

</div>

<br/>

## 🇬🇧 English

### 📖 About

**Fahras** is a comprehensive client indexing and violation detection system built for the Jordanian installment companies market. The system searches clients across multiple local and remote sources, automatically detects contractual violations between companies, and manages fines and payments — with full Arabic and English support.

---

### ✨ Key Features

#### 🔍 Unified Search
- Search by name, national ID, or phone number
- Simultaneous queries across local DB + 4 remote sources (Zajal, Jadal, Namaa, Bseel)
- Instant recommendations: **Can Sell** / **Cannot Sell** / **Contact First**
- Copy report as image via html2canvas

#### ⚠️ Violation Detection Engine
- Automatic detection of duplicate clients across companies
- Smart fine rules (20 JOD for first 5 violations, 40 JOD thereafter)
- Minimum remaining amount threshold: 150 JOD
- Guarantor and contract party checks
- Auto-exclusion of finished/canceled contracts

#### 🔄 Scan & Sync
- Full synchronization with all remote sources
- Comprehensive scan: local↔local, local↔remote, remote↔remote
- Real-time progress bar with detailed operation log

#### 📊 Reports & Analytics
- **Dashboard**: Live stats (total clients, companies, monthly violations)
- **Sales Report**: By date range with charts
- **Monthly Report**: Violations by month with company comparison
- **Activity Log**: Track all operations (login, search, import, scan)

#### 👥 User Management & RBAC
- Roles: Super Admin, Company Admin, User, Viewer
- Granular permissions at module + action level
- Direct user permissions in addition to roles
- Company Admin sees only their company's data

#### 📥 Import & Export
- Import clients from Excel (xlsx, xls, csv)
- Silent violation check on every import
- CSV data export

#### 🌐 Bilingual
- Full Arabic & English UI
- Dynamic translation system via database
- Complete RTL support

#### 🎨 Professional UI
- Dark / Light mode with preference persistence
- Responsive design with Bootstrap RTL
- Almarai Arabic font
- Font Awesome icons

---

### 🛠️ Tech Stack

| Layer | Technology |
|-------|------------|
| **Backend** | PHP 8.x |
| **Database** | MySQL (utf8mb4) |
| **ORM** | smplPDO (PDO wrapper) + XCRUD |
| **Frontend** | Bootstrap 3.3.7 RTL, jQuery |
| **Charts** | Chart.js 3.5.1 |
| **Components** | Select2 4.0.3, html2canvas 1.4.1, Animate.css |
| **Fonts** | Almarai (Google Fonts) |
| **Excel** | PhpOffice/PhpSpreadsheet |
| **External API** | SQL Server (sqlsrv) for Bseel system |

---

### 🗄️ Database Schema

| Table | Description |
|-------|-------------|
| `accounts` | Companies (name, phone, address, tax number, type, status) |
| `users` | Users (username, password, role, account, language) |
| `clients` | Clients (account, name, national ID, contracts, remaining amount) |
| `violations` | Violations (client, entitled party, violator, fine, status) |
| `violation_log` | Violation change history |
| `remote_clients` | Cached remote API results |
| `roles` | RBAC roles |
| `permissions` | Permissions (module + action) |
| `role_has_permissions` | Role-permission mapping |
| `user_has_permissions` | Direct user permissions |
| `activity_log` | Activity tracking |
| `translate` | UI translations (text, Arabic, English) |
| `attachments` | Client attachments |

---

### 🔌 External APIs

The system connects to four remote sources for search and synchronization:

| Source | Purpose |
|--------|---------|
| **Zajal** | Client search & sync |
| **Jadal** | Client & workplace search |
| **Namaa** | Client & workplace search |
| **Bseel** | Client search, workplaces, attachments, contract parties |

---

### 🔐 Security

- Password hashing with bcrypt/argon2 (auto-upgrade from MD5)
- CSRF protection on forms and AJAX requests
- Secure sessions with token + remember-me cookies
- Multi-level RBAC permission system
- Full activity logging with IP tracking

---

### 📂 Project Structure

```
Fahras/
├── index.php                    # Redirect → /admin
├── FahrasBaselFullAPIs.php      # Bseel API endpoint (SQL Server)
│
├── admin/                       # Admin Panel
│   ├── index.php                # Dashboard + unified search
│   ├── header.php / footer.php  # Main template
│   ├── login.php / logout.php   # Authentication
│   ├── clients.php              # Client management
│   ├── accounts.php             # Company management
│   ├── jobs.php                 # Workplace search
│   ├── violations.php           # Violation management
│   ├── scan.php / scan_ajax.php # Scan & sync engine
│   ├── import.php               # Excel import
│   ├── users.php / roles.php    # User & role management
│   ├── sales-report.php         # Sales report
│   ├── monthly-report.php       # Monthly report
│   ├── activity-log.php         # Activity log
│   ├── translate.php            # Translation management
│   ├── attachments.php          # Client attachments
│   ├── css/                     # Stylesheets
│   └── xcrud/                   # XCRUD library
│
├── includes/
│   ├── bootstrap.php            # Auth, CSRF, RBAC, helpers
│   ├── smplPDO.php              # Simplified PDO wrapper
│   ├── violation_engine.php     # Violation detection engine
│   ├── permissions.php          # Permission definitions
│   └── migrations/              # Database migrations
│
└── vendor/                      # Composer (PhpSpreadsheet)
```

---

<div align="center">

**Built for the Jordanian Installment Market** 🇯🇴

</div>
