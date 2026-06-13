const toastEl = document.getElementById('siteToast');
const toastText = document.getElementById('toastText');
const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2200 });
const scrollTopBtn = document.getElementById('scrollTopBtn');
const reveals = document.querySelectorAll('.reveal');

const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) entry.target.classList.add('visible');
    });
}, { threshold: 0.12 });

reveals.forEach(el => observer.observe(el));

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            const navbarCollapseEl = document.getElementById('navbarNav');
            const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapseEl);
            if (window.innerWidth < 992 && bsCollapse) bsCollapse.hide();
        }
    });
});

window.addEventListener('scroll', () => {
    scrollTopBtn.classList.toggle('show', window.scrollY > 500);
});

scrollTopBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

document.getElementById('contactForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    try {
        const response = await fetch('send-mail.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        toastEl.classList.remove('text-bg-success', 'text-bg-danger', 'text-bg-primary');
        if (result.success) {
            this.reset();
            toastEl.classList.add('text-bg-success');
            toastText.textContent = 'Сообщение отправлено на почту.';
        } else {
            toastEl.classList.add('text-bg-danger');
            toastText.textContent = result.message || 'Ошибка отправки сообщения.';
        }
        toast.show();
    } catch (error) {
        toastEl.classList.remove('text-bg-success', 'text-bg-primary');
        toastEl.classList.add('text-bg-danger');
        toastText.textContent = 'Сервер недоступен или PHP-обработчик не найден.';
        toast.show();
    }
});

function donate() {
    toastEl.classList.remove('text-bg-success', 'text-bg-danger');
    toastEl.classList.add('text-bg-primary');
    toastText.textContent = 'Переход к форме пожертвований (YooMoney/Sber).';
    toast.show();
    setTimeout(() => {
        toastEl.classList.remove('text-bg-primary');
        toastEl.classList.add('text-bg-success');
    }, 2400);
}