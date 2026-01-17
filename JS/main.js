// تحديث التاريخ الهجري والميلادي
function updateDate() {
    const now = new Date();
    
    // التاريخ الميلادي (بدون اسم اليوم)
    const gregorianDate = now.toLocaleDateString('ar-DZ', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        calendar: 'gregory'
    });
    
    // التاريخ الهجري
    const hijriDate = now.toLocaleDateString('ar-DZ-u-ca-islamic-umalqura', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }).replace(' هـ', '').replace('،', '');
    
    if (document.getElementById('hijri-date')) {
        document.getElementById('hijri-date').textContent = hijriDate;
    }
    if (document.getElementById('gregorian-date')) {
        document.getElementById('gregorian-date').textContent = gregorianDate;
    }
}

// تفعيل القائمة المنسدلة للموبايل
function toggleMobileMenu() {
    const mobileMenu = document.getElementById('mobile-menu');
    const menuIcon = document.querySelector('.menu-icon');
    
    if (mobileMenu && menuIcon) {
        mobileMenu.classList.toggle('active');
        menuIcon.classList.toggle('active');
    }
}

// تأثيرات التمرير السلس
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// تهيئة عند التحميل
window.addEventListener('DOMContentLoaded', function() {
    // تحديث التاريخ إذا كانت العناصر موجودة
    if (document.getElementById('hijri-date')) {
        updateDate();
        setInterval(updateDate, 60000);
    }
});

// مراقبة التمرير لإخفاء شريط التاريخ
window.addEventListener('scroll', function() {
    const currentScroll = window.pageYOffset;
    const navbar = document.getElementById('main-navbar');
    const dateBar = document.querySelector('.date-bar');
    
    // شريط التاريخ
    if (dateBar && navbar) {
        if (currentScroll > 50) {
            dateBar.classList.add('hidden');
            navbar.style.top = '0';
        } else {
            dateBar.classList.remove('hidden');
            navbar.style.top = '43px';
        }
    }
});