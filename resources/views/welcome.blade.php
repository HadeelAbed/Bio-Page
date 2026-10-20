<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>منصتك | اجمع كل روابطك في صفحة واحدة أنيقة</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Tajawal', sans-serif; }
        .glass-nav {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.5);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.6);
        }
        .header-mask {
            mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 50%, rgba(0,0,0,0) 100%);
            -webkit-mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 50%, rgba(0,0,0,0) 100%);
        }
        .preview-glass-link {
            background: rgba(255, 255, 255, 0.28);
            border: 1px solid rgba(255, 255, 255, 0.45);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
        }
    </style>
</head>
<body class="bg-[#faf7f5] text-gray-900 selection:bg-rose-200 overflow-x-hidden">

    <!-- خلفيات ضوئية ديكورية ناعمة -->
    <div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden">
        <div class="absolute -top-40 -right-40 w-[500px] h-[500px] bg-rose-200/50 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -left-40 w-[500px] h-[500px] bg-amber-100/60 rounded-full blur-3xl"></div>
    </div>

    <!-- 1. شريط التنقل العلوي (Navbar) -->
    <nav class="fixed top-0 left-0 w-full z-50 glass-nav transition">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-20 flex items-center justify-between gap-2">
            <a href="{{ route('home') }}" class="flex items-center gap-2" aria-label="الصفحة الرئيسية">
                <span class="w-9 h-9 rounded-xl bg-gray-900 text-white flex items-center justify-center font-bold text-lg shadow-sm">B</span>
                <span class="font-bold text-base sm:text-xl tracking-tight text-gray-900">بايوـلينك</span>
            </a>
            <div class="flex items-center gap-1.5 sm:gap-3">
                <a href="{{ route('login') }}" class="text-[11px] sm:text-sm font-semibold text-gray-700 hover:text-black px-3 sm:px-4 py-2 transition whitespace-nowrap">تسجيل الدخول</a>
                <a href="{{ route('register') }}" class="bg-gray-900 hover:bg-black text-white text-[11px] sm:text-sm font-semibold px-4 sm:px-5 py-2.5 rounded-xl shadow-sm transition whitespace-nowrap">
                    ابدأ مجاناً
                </a>
            </div>
        </div>
    </nav>

    <!-- 2. القسم الرئيسي (Hero Section) -->
    <header class="pt-36 pb-20 md:pt-44 md:pb-28 max-w-6xl mx-auto px-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">

            <!-- النصوص والدعوة للإجراء (CTA) -->
            <div class="lg:col-span-7 -translate-y-6 lg:-translate-y-12 space-y-6 text-center lg:text-right">
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-gray-900 leading-[1.2] tracking-tight">
                    رابط واحد يجمع <span class="text-transparent bg-clip-text bg-gradient-to-l from-[#a87f83] to-[#c99a9e]">كل هويتك</span> وإبداعك في مكان واحد.
                </h1>

                <p class="text-base sm:text-lg text-gray-600 max-w-xl mx-auto lg:mx-0 font-light leading-relaxed">
                    صممي صفحة روابط شخصية فخمة بتأثير الزجاج وتدرجات مخصصة مجاناً بالكامل. شاركي أعمالك، حساباتك، ومتاجرك برابط خاص يعبر عنك.
                </p>

                <!-- نموذج حجز الرابط السريع -->
                <div class="pt-2 max-w-md mx-auto lg:mx-0">
                    <form action="{{ route('register') }}" method="GET" class="glass-card p-2 rounded-2xl shadow-lg border border-white flex flex-col sm:flex-row gap-2">
                        <div class="flex-1 flex items-center px-3 bg-white/70 rounded-xl border border-gray-200" dir="ltr">
                            <span class="text-xs text-gray-400 font-mono">link.me/</span>
                            <input
                                type="text"
                                name="username"
                                placeholder="yourname"
                                aria-label="اسم الرابط الذي تريد حجزه"
                                minlength="3"
                                maxlength="30"
                                pattern="[A-Za-z0-9_\-]+"
                                title="استخدمي 3 أحرف على الأقل من الحروف الإنجليزية أو الأرقام أو _ أو -"
                                class="w-full p-2.5 text-xs text-gray-800 bg-transparent focus:outline-none font-semibold"
                            >
                        </div>
                        <button type="submit" class="bg-gray-900 hover:bg-black text-white text-xs sm:text-sm font-bold px-6 py-3 rounded-xl transition shadow-md whitespace-nowrap">
                            احجز رابطك
                        </button>
                    </form>
                    <p class="text-[11px] text-gray-500 mt-2 text-center lg:text-right">سهل وسريع، ولا يتطلب أي خبرة برمجية.</p>
                </div>
            </div>

            <!-- معاينة حية لصفحة الروابط داخل جوال -->
            <div class="lg:col-span-5 flex justify-center">
                <div class="relative w-full max-w-[340px]">
                    <div class="pointer-events-none absolute -inset-6 rounded-[3rem] bg-gradient-to-tr from-rose-300/40 via-amber-100/40 to-transparent blur-2xl"></div>
                    <div class="relative aspect-[34/67] w-full rounded-[48px] bg-black p-3 shadow-2xl ring-1 ring-gray-900/15">
                        <div class="absolute left-1/2 top-4 z-30 h-4 w-24 -translate-x-1/2 rounded-full bg-black"></div>
                        <div class="flex h-full w-full flex-col overflow-y-auto overflow-x-hidden rounded-[40px] no-scrollbar bg-gradient-to-b from-[#b88c8f] to-[#ba8b8f]">
                            <div class="relative h-64 w-full flex-shrink-0 overflow-hidden">
                                <img
                                    src="{{ asset('images/hijab-portrait.svg') }}"
                                    alt="رسمة توضيحية لامرأة محجبة"
                                    class="header-mask h-full w-full object-cover object-top"
                                />
                            </div>
                            <div class="z-10 mt-[-15px] flex flex-shrink-0 flex-col items-center px-5 text-center text-gray-900">
                                <h3 class="font-serif text-xl font-normal">اسمك هنا</h3>
                                <p class="mt-1.5 line-clamp-2 text-[11px] leading-relaxed opacity-85">مصممة ومطوّرة، كل روابطي في مكان واحد</p>
                            </div>
                            <div class="mt-5 flex-1 space-y-2.5 px-4 pb-8">
                                <div class="preview-glass-link flex items-center justify-between rounded-2xl p-3.5 text-gray-900">
                                    <span class="w-3"></span>
                                    <span class="max-w-[200px] truncate text-center text-xs font-medium">معرض أعمالي</span>
                                    <i class="fa-solid fa-arrow-up-right-from-square px-1 text-[10px] opacity-60" aria-hidden="true"></i>
                                </div>
                                <div class="preview-glass-link flex items-center justify-between rounded-2xl p-3.5 text-gray-900">
                                    <span class="w-3"></span>
                                    <span class="max-w-[200px] truncate text-center text-xs font-medium">حسابي على إنستغرام</span>
                                    <i class="fa-solid fa-arrow-up-right-from-square px-1 text-[10px] opacity-60" aria-hidden="true"></i>
                                </div>
                                <div class="preview-glass-link flex items-center justify-between rounded-2xl p-3.5 text-gray-900">
                                    <span class="w-3"></span>
                                    <span class="max-w-[200px] truncate text-center text-xs font-medium">رابط مخصص آخر</span>
                                    <i class="fa-solid fa-arrow-up-right-from-square px-1 text-[10px] opacity-60" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </header>

    <!-- 3. قسم المميزات (Features) -->
    <section class="py-20 bg-white/60 border-t border-gray-100">
        <div class="max-w-6xl mx-auto px-6">
            <div class="text-center max-w-xl mx-auto mb-16">
                <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900">لماذا يفضل المبدعون منصتنا؟</h2>
                <p class="text-xs sm:text-sm text-gray-600 mt-2">كل المزايا التي تدفع عليها اشتراكات في المواقع الأخرى، نوفرها لك مجاناً وبأعلى جودة.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="p-8 rounded-3xl bg-white border border-gray-200/80 shadow-sm hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl mb-6">
                        <i class="fa-solid fa-palette"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">تخصيص كامل للألوان</h3>
                    <p class="text-xs text-gray-600 leading-relaxed">تحكم كامل في تدرجات الخلفية، ألوان الخطوط، ومظهر الزجاج الشفاف ليطابق هوية عملك.</p>
                </div>

                <div class="p-8 rounded-3xl bg-white border border-gray-200/80 shadow-sm hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl mb-6">
                        <i class="fa-solid fa-image"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">هيدر سينمائي بتلاشٍ ناعم</h3>
                    <p class="text-xs text-gray-600 leading-relaxed">ارفع صورتك بدقة عالية وادمجها مع الخلفية بسلاسة بدون الحاجة لأي برامج تعديل خارجية.</p>
                </div>

                <div class="p-8 rounded-3xl bg-white border border-gray-200/80 shadow-sm hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl mb-6">
                        <i class="fa-solid fa-link"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">تنظيم الروابط بسهولة</h3>
                    <p class="text-xs text-gray-600 leading-relaxed">أضيفي حتى 25 رابطاً، سمّيها كما تحبين، وأعيدي ترتيبها بأزرار واضحة مع معاينة مباشرة.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. فوتر الصفحة (Footer) -->
    <footer class="py-10 border-t border-gray-200/80 text-center text-xs text-gray-500">
        <p>© 2026 جميع الحقوق محفوظة — منصة الروابط المخصصة.</p>
    </footer>

</body>
</html>
