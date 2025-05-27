document.addEventListener('DOMContentLoaded', function() {

    const bookingForm = document.getElementById('bookingForm');
    const statusForm = document.getElementById('statusForm');
    const bookingMessage = document.getElementById('bookingMessage');
    const statusResult = document.getElementById('statusResult');

    // فقط اگر در صفحه مشتری هستیم، این‌ها را اجرا کن
    if (bookingForm && statusForm) {

        // مدیریت فرم رزرو نوبت
        bookingForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(bookingForm);
            formData.append('action', 'book');

            bookingMessage.textContent = 'در حال ارسال...';
            bookingMessage.className = 'info';

            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                bookingMessage.textContent = data.message;
                bookingMessage.className = data.success ? 'success' : 'error';
                if (data.success) {
                    bookingForm.reset();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                bookingMessage.textContent = 'خطایی رخ داد. لطفاً دوباره تلاش کنید.';
                bookingMessage.className = 'error';
            });
        });

        // مدیریت فرم استعلام وضعیت
        statusForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const phone = document.getElementById('checkPhone').value;
            statusResult.innerHTML = 'در حال بررسی...';
            statusResult.className = 'info';

            fetch(`api.php?action=check_status&phone=${phone}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.appointments.length > 0) {
                    statusResult.innerHTML = 'وضعیت نوبت(های) شما:<br>';
                    data.appointments.forEach(app => {
                        let statusText = '';
                        let statusClass = '';
                        switch (app.status) {
                            case 'pending': statusText = 'در انتظار تایید'; statusClass = 'pending'; break;
                            case 'confirmed': statusText = 'تایید شده ✅'; statusClass = 'confirmed'; break;
                            case 'rejected': statusText = 'رد شده ❌'; statusClass = 'rejected'; break;
                        }
                        statusResult.innerHTML += `
                            <div class="${statusClass}" style="margin-bottom: 10px; padding: 10px; border-radius: 4px;">
                                تاریخ: ${app.app_date} - زمان: ${app.app_time} - خدمات: ${app.service} - وضعیت: <strong>${statusText}</strong>
                                ${app.barber_message ? `<br><small>پیام آرایشگر: ${app.barber_message}</small>` : ''}
                            </div>
                        `;
                    });
                     statusResult.className = '';
                } else if (data.success) {
                    statusResult.textContent = 'هیچ نوبتی با این شماره تلفن یافت نشد.';
                    statusResult.className = 'info';
                } else {
                    statusResult.textContent = data.message || 'خطا در بررسی وضعیت.';
                    statusResult.className = 'error';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                statusResult.textContent = 'خطای شبکه در بررسی وضعیت.';
                statusResult.className = 'error';
            });
        });
    }
});