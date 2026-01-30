PAYMOB EGYPT - Paymob Payment Gateway for Bagisto
By CartCan (Free & Open Integration)
www.cartcan.com

Paymob payment gateway for Bagisto, developed and maintained by CartCan.
Supports Accept – Unified Checkout with full order and refund lifecycle integration.

🚀 Features
Accept Unified Checkout

Payments via:

Cards

Wallets

Installments

Automatic order creation after successful payment

Transaction ID stored per order

Full Refund support from Admin panel

Refund status handling:

Refunded (Gateway)

Refunded (Settled)

Auto-loaded payment gateway logo

No Bagisto Core overrides

Production-ready & marketplace-ready

📄 Licensing
Library Licenses:

Bagisto: MIT License

Laravel: MIT License

This Package License:

MIT License - Free to use, modify, and distribute

👥 Development & Credits
Package Development:

Developed by: CartCan

Contact: hello@cartcan.com

Version: 2.3.x

Release Date: December 2025

Core Contributors:

Bagisto Development Team - Base framework

Library Maintainers - Core functionality providers

CartCan Developers - Bagisto integration and customization

Community Support:

Bagisto Arabic Community

https://www.facebook.com/BagistoMENA

Open-source package maintainers

✅ Requirements
Bagisto v1.5+

PHP 8.1+

Paymob (Accept) Account

Public Key

Secret Key

Integration IDs

📁 Package Structure
packages/
└── Webkul/
    └── Paymob/
        ├── src/
        │   ├── Config/
        │   │   ├── payment-methods.php
        │   │   └── system.php
        │   ├── Http/
        │   │   └── Controllers/
        │   │       └── PaymobController.php
        │   ├── Payment/
        │   │   └── Paymob.php
        │   ├── Providers/
        │   │   └── PaymobServiceProvider.php
        │   ├── Listeners/
        │   │   └── RefundPaymob.php
        │   ├── Routes/
        │   │   └── web.php
        │   └── Resources/
        │       ├── assets/
        │       │   └── images/
        │       │       └── paymob.png
        │       ├── views/
        │       │   └── admin/
        │       │       └── sales/
        │       │           └── orders/
        │       │               └── view.blade.php
        │       └── lang/
        │           └── en/
        │               └── app.php
        └── README.md
⚙️ Installation
1️⃣ Place the Package
Copy the package to:


packages/Webkul/Paymob
Make sure composer.json contains:

json
"autoload": {
  "psr-4": {
    "Webkul\\Paymob\\": "packages/Webkul/Paymob/src/"
  }
}
Register the service provider in bootstrap/providers.php:

php
Webkul\Paymob\Providers\PaymobServiceProvider::class,
Run:

bash
composer dump-autoload
2️⃣ Publish Assets & Views
bash
php artisan vendor:publish --tag=paymob-assets
php artisan vendor:publish --tag=paymob-admin-views
php artisan optimize:clear
3️⃣ Configure Paymob
Navigate to:
Admin → Configuration → Sales → Payment Methods → Paymob

Fill in:

✅ Public Key

✅ Secret Key

✅ Integration IDs (comma separated)

✅ HMAC Secret

❌ Not required:

API Key

Iframe ID

🧾 Stored Payment Data
Payment-related data stored in:
order_payment.additional

Example:

json
{
  "paymob_amount_cents": 18600,
  "paymob_transaction_id": "386028054",
  "paymob_refund_last_response": {}
}
🔁 Refunds
Automatically triggered when creating a Refund from Admin

Fully integrated with Bagisto refund events

No additional configuration required

🖼️ Payment Logo
Auto-loaded from:
/public/vendor/paymob/images/paymob.png

Displayed in:

Checkout page

Admin panel

Order details

🔗 Integration Callbacks
Callback Type	URL
Transaction processed callback	https://www.yourdomain.com/paymob/callback
Transaction response callback	https://www.yourdomain.com/paymob/callback
❌ Common Issues
Issue	Solution
Logo not visible	Ensure assets are published and path starts with /
404 vendor/paymob	Run vendor:publish --tag=paymob-assets
Refund not working	Verify Secret Key and HMAC
🏷️ Status
✅ Production Ready

✅ Marketplace Ready

✅ Zero Core Overrides

✅ Tested on Live Paymob Environment

💼 Commercial Note
Free Package
Developed by CartCan

Free to use

No Core modifications

Commercial & marketplace friendly

Redistribution allowed with attribution

© Copyright
© 2026 CartCan
www.cartcan.com

All rights reserved.

This package is provided free of charge by CartCan.
Paymob® is a registered trademark of its respective owner.
--------

PAYMOB مصر - بوابة دفع Paymob لـ Bagisto
من CartCan (تكامل مجاني ومفتوح المصدر)
www.cartcan.com

بوابة الدفع Paymob لمنصة Bagisto، مطورة ومدارة بواسطة CartCan.
تدعم Accept – Unified Checkout مع تكامل كامل لدورة حياة الطلبات والاسترداد.

🚀 المميزات
دعم Unified Checkout

المدفوعات عبر:

البطاقات

المحافظ الإلكترونية

الدفع بالتقسيط

إنشاء الطلبات تلقائياً بعد الدفع الناجح

تخزين معرف المعاملة لكل طلب

دعم كامل لعملية الاسترداد من لوحة التحكم

معالجة حالات الاسترداد:

تم الاسترداد (من البوابة)

تم الاسترداد (تم التسوية)

تحميل شعار بوابة الدفع تلقائياً

لا يوجد تعديل على نواة Bagisto

جاهز للإنتاج وللتسويق

📄 الترخيص
تراخيص المكتبات:

Bagisto: ترخيص MIT

Laravel: ترخيص MIT

ترخيص هذه الحزمة:

ترخيص MIT - مجاني للاستخدام والتعديل والتوزيع

👥 التطوير والاعتمادات
تطوير الحزمة:

المطور: CartCan

التواصل: hello@cartcan.com

الإصدار: 2.3.x

تاريخ الإصدار: ديسمبر 2025

المساهمون الرئيسيون:

فريق تطوير Bagisto - الإطار الأساسي


مطورو CartCan - تكامل وتخصيص Bagisto

الدعم المجتمعي:

مجتمع Bagisto العربي

https://www.facebook.com/BagistoMENA

مشرفو الحزم مفتوحة المصدر

✅ المتطلبات
Bagisto الإصدار 1.5 فأعلى

PHP الإصدار 8.1 فأعلى

حساب Paymob (Accept)

المفتاح العام

المفتاح السري

معرفات التكامل

📁 هيكل الحزمة
packages/
└── Webkul/
    └── Paymob/
        ├── src/
        │   ├── Config/
        │   │   ├── payment-methods.php
        │   │   └── system.php
        │   ├── Http/
        │   │   └── Controllers/
        │   │       └── PaymobController.php
        │   ├── Payment/
        │   │   └── Paymob.php
        │   ├── Providers/
        │   │   └── PaymobServiceProvider.php
        │   ├── Listeners/
        │   │   └── RefundPaymob.php
        │   ├── Routes/
        │   │   └── web.php
        │   └── Resources/
        │       ├── assets/
        │       │   └── images/
        │       │       └── paymob.png
        │       ├── views/
        │       │   └── admin/
        │       │       └── sales/
        │       │           └── orders/
        │       │               └── view.blade.php
        │       └── lang/
        │           └── en/
        │               └── app.php
        └── README.md
⚙️ التثبيت
1️⃣ وضع الحزمة
انسخ الحزمة إلى:


packages/Webkul/Paymob
تأكد من أن ملف composer.json يحتوي على:

json
"autoload": {
  "psr-4": {
    "Webkul\\Paymob\\": "packages/Webkul/Paymob/src/"
  }
}
سجل مقدم الخدمة في ملف bootstrap/providers.php:

php
Webkul\Paymob\Providers\PaymobServiceProvider::class,
شغل الأمر:

bash
composer dump-autoload
2️⃣ نشر الأصول والعروض
bash
php artisan vendor:publish --tag=paymob-assets
php artisan vendor:publish --tag=paymob-admin-views
php artisan optimize:clear
3️⃣ تكوين Paymob
انتقل إلى:
المسؤول → الإعدادات → المبيعات → طرق الدفع → Paymob

املأ البيانات التالية:

✅ المفتاح العام

✅ المفتاح السري

✅ معرفات التكامل (مفصولة بفواصل)

✅ سر HMAC

❌ غير مطلوب:

مفتاح API

معرف Iframe

🧾 بيانات الدفع المخزنة
بيانات الدفع المخزنة في:
order_payment.additional

مثال:

json
{
  "paymob_amount_cents": 18600,
  "paymob_transaction_id": "386028054",
  "paymob_refund_last_response": {}
}
🔁 عمليات الاسترداد
يتم تشغيلها تلقائياً عند إنشاء استرداد من لوحة التحكم

متكاملة بالكامل مع أحداث الاسترداد في Bagisto

لا تحتاج إلى أي إعدادات إضافية

🖼️ شعار بوابة الدفع
يتم تحميله تلقائياً من:
/public/vendor/paymob/images/paymob.png

يظهر في:

صفحة الدفع

لوحة التحكم

تفاصيل الطلب

🔗 ردود الاتصال للتكامل
نوع رد الاتصال	الرابط
رد معاملة معالجة	https://www.yourdomain.com/paymob/callback
رد استجابة المعاملة	https://www.yourdomain.com/paymob/callback
❌ المشاكل الشائعة
المشكلة	الحل
الشعار غير مرئي	تأكد من نشر الأصول وأن المسار يبدأ بـ /
خطأ 404 vendor/paymob	شغل الأمر vendor:publish --tag=paymob-assets
الاسترداد لا يعمل	تحقق من المفتاح السري و HMAC
🏷️ الحالة
✅ جاهز للإنتاج

✅ جاهز للتسويق

✅ بدون تعديلات على النواة

✅ تم الاختبار على بيئة Paymob الحية

💼 ملاحظة تجارية
حزمة مجانية
مطورة بواسطة CartCan

مجانية الاستخدام

بدون تعديلات على النواة

مناسبة للاستخدام التجاري والتسويق

مسموح بإعادة التوزيع مع نسب العمل للأصل

© حقوق النشر
© 2026 CartCan
www.cartcan.com

جميع الحقوق محفوظة.

هذه الحزمة مقدمة مجاناً من CartCan.
Paymob® علامة تجارية مسجلة لمالكها المعني.