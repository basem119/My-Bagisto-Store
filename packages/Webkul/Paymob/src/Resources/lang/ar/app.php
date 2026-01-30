<?php

return [
    'admin' => [
        'system' => [
            'paymob'                  => 'باي موب',
            'paymob_info'            => 'نظام الدفع الموحد من باي موب (Accept API)',
            
            'status'                 => 'الحالة',
            'title'                  => 'العنوان',
            'description'            => 'الوصف',
            
            'public_key'             => 'المفتاح العام',
            'public_key_info'        => 'مطلوب. يستخدم لفتح صفحة الدفع الموحدة من باي موب.',
            
            'secret_key'             => 'المفتاح السري',
            'secret_key_info'        => 'مطلوب. يستخدم لإنشاء طلبات الدفع والاسترجاع.',
            
            'integration_ids'        => 'معرفات التكامل (مفصولة بفاصلة)',
            'integration_ids_info'   => 'مثال: 2172510,4553310',
            
            'hmac'                   => 'HMAC السري',
            'hmac_info'              => 'يستخدم للتحقق من إشعارات باي موب.',
            
            'debug_mode'             => 'وضع التصحيح',
            'debug_mode_info'        => 'تفعيل تسجيل جميع طلبات واستجابات API في ملف storage/logs/paymob.log',
            
            'order_min_total'        => 'الحد الأدنى للطلب (جنيه مصري)',
            'order_min_total_info'   => 'الطلبات أقل من هذا المبلغ لن تظهر باي موب كخيار دفع.',
            
            'sort'                   => 'ترتيب العرض',
        ],
    ],

    'shop' => [
        'checkout' => [
            'paymob_title'       => 'باي موب',
            'paymob_description' => 'ادفع بأمان باستخدام البطاقات والمحافظ الإلكترونية والتقسيط عبر باي موب.',
        ],
    ],
];