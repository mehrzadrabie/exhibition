<?php
defined('ROOT') || exit;

/** Initial content for «همایش دکمه قرمز» (from the event brochure). Editable later in the admin panel. */
function seed_all()
{
    require_once APP . '/lib/hall.php';
    $cats = hall_seed();

    $settings = [
        'site_title' => 'همایش دکمه قرمز',
        'site_tagline' => 'راهبردهای بقا، بازیابی و بازگشت به رشد – ویژه کسب‌وکارهای کرمان',
        'event_dates' => '۲۲ و ۲۳ مهر ۱۴۰۵',
        'venue' => 'تالار فرهنگ و هنر کرمان',
        'support_phone' => '09196443843',
        'footer_text' => 'برگزارکننده: Vcandoo – Innovative Total Solutions for Business Growth',
        'about_title' => 'دکمه قرمز چیست؟',
        'about_text' => "بحران، پیش از آنکه یک رویداد باشد، یک وضعیت تصمیم‌گیری است؛ وضعیتی که در آن سرعت تغییرات افزایش می‌یابد، قابلیت پیش‌بینی کاهش پیدا می‌کند، منابع محدودتر می‌شوند و تصمیم‌هایی که در شرایط عادی می‌توانستند به تعویق بیفتند، ناگهان به تصمیم‌هایی فوری و گاه سرنوشت‌ساز تبدیل می‌شوند.\n\n«دکمه قرمز» رویدادی تخصصی برای مدیران و صاحبان کسب‌وکارهای کوچک و متوسط است که با هدف ارتقای توان تشخیص، تصمیم‌گیری و اقدام مدیریتی در شرایط بحران و پسابحران طراحی شده است. معماری محتوایی رویداد حول یک مسیر سه‌مرحله‌ای شکل گرفته است: بقا ← بازیابی ← بازگشت به رشد.\n\nقرار نیست مدیران در پایان «دکمه قرمز» فقط بیشتر بدانند؛ قرار است دقیق‌تر ببینند، بهتر تصمیم بگیرند و آگاهانه‌تر عمل کنند.",
        'audience_text' => "«دکمه قرمز» با حضور حدود ۷۰۰ نفر از مدیران، کارآفرینان و صاحبان کسب‌وکارهای استان کرمان برگزار می‌شود:\n• مالکان و بنیان‌گذاران کسب‌وکارها\n• مدیران عامل، مدیران ارشد و کارشناسان سازمان‌ها و شرکت‌های بزرگ استان\n• مدیران کسب‌وکارهای کوچک و متوسط (SME)\n• کارآفرینان و فعالان اقتصادی\n• مدیران و فعالان صنایع، اصناف و مجموعه‌های خدماتی\n• مهمانان VIP شامل مدیران و چهره‌های منتخب اکوسیستم کسب‌وکار",
        'hold_minutes' => '12',
        'gateway' => 'fake',
        'zarinpal_sandbox' => '0',
        'zibal_merchant' => 'zibal',
        'sms_driver' => 'log',
        'otp_length' => '5',
        'otp_resend_seconds' => '90',
        'layout_version' => (string)time(),
    ];
    foreach ($settings as $k => $v) db_exec('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $v]);

    $sessions = [
        [
            'title' => 'روز نخست: سخنرانی تعاملی و میز تجربه',
            'subtitle' => 'جعبه‌ابزار مدیریتی عبور از بحران',
            'description' => "سخنرانی تعاملی ۵ تن از اساتید برجسته کشوری با موضوع «جعبه‌ابزار مدیریتی عبور از بحران»\nمیز تجربه با حضور ۴ نفر از کارآفرینان برتر کشور با موضوع «تجربیات عبور از بحران»",
            'starts_at' => jalali_to_datetime('1405/07/22 16:00'),
        ],
        [
            'title' => 'روز دوم: یک فنجان چای تجربه',
            'subtitle' => 'تجربیات عبور از بحران',
            'description' => "یک فنجان چای تجربه با حضور ۴ نفر از کارآفرینان برتر کشور با موضوع «تجربیات عبور از بحران»\nاجرای ۲ سانس بازی مدیریتی «Battle Of Kings» ویژه میهمانان VIP",
            'starts_at' => jalali_to_datetime('1405/07/23 16:00'),
        ],
    ];
    $i = 0;
    foreach ($sessions as $s) {
        $sid = db_insert('sessions', $s + ['sale_open' => 1, 'is_public' => 1, 'max_per_order' => 10, 'sort' => ++$i, 'created_at' => now()]);
        session_seats_init($sid);
        // VIP rows are invitation-only by default (no public price) – issue them from the admin panel
        db_insert('session_prices', ['session_id' => $sid, 'category_id' => $cats['a'], 'price' => 2500000]);
        db_insert('session_prices', ['session_id' => $sid, 'category_id' => $cats['b'], 'price' => 1500000]);
    }

    $speakers = [
        ['دکتر محمد وکیلی', 'دکترای مدیریت بازرگانی، مدیرعامل شرکت مشاوره و توسعه مدیریت تمهید', 'vakili'],
        ['دکتر مهدی کنعانی', 'دکترای کارآفرینی سازمانی، مشاور سرمایه‌گذاری و ارزیابی شرکت‌های دانش‌بنیان', 'kanani'],
        ['ساحل کلانتری', 'کوچ و منتور مدیران، مدیرعامل شرکت مشاوره مدیریت راهکار تجارت کندو', 'kalantari'],
        ['مجید برقی', 'موسس DrCRM.ir، رئیس هیئت‌مدیره بهپویان راه توسعه فردا', 'barghi'],
        ['پدرام صادقی', 'مدیر ارشد مالی اسنپ، مدرس دوره‌های تخصصی مالی و حسابداری', 'sadeghi'],
        ['صمد سلیمان‌زاده', 'مدیرعامل شرکت ساختمانی ویو، سازنده برج پارس و برج ویو', 'soleimanzadeh'],
        ['بابک خوشجان', 'رئیس هیئت‌مدیره راهکار تجارت کندو، استراتژیست برندینگ و تبلیغات', 'khoshjan'],
    ];
    foreach ($speakers as $k => $sp) {
        db_insert('speakers', ['name' => $sp[0], 'title' => $sp[1], 'photo' => 'assets/img/speakers/' . $sp[2] . '.jpg', 'sort' => $k + 1, 'is_active' => 1]);
    }
}
