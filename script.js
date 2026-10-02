const toastEl = document.getElementById('siteToast');
const toastText = document.getElementById('toastText');
const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2200 });
const scrollTopBtn = document.getElementById('scrollTopBtn');
let reveals = document.querySelectorAll('.reveal');

const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) entry.target.classList.add('visible');
    });
}, { threshold: 0.12 });

reveals.forEach(el => observer.observe(el));

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const hash = this.getAttribute('href');
        if (hash === '#' || hash === '') return;
        const target = document.querySelector(hash);
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

const MONTHS_RU = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];

function formatDate(isoDate) {
    const d = new Date(isoDate + 'T00:00:00');
    if (isNaN(d)) return isoDate;
    return `${d.getDate()} ${MONTHS_RU[d.getMonth()]} ${d.getFullYear()}`;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

async function loadJson(file) {
    const response = await fetch(file);
    if (!response.ok) throw new Error('HTTP ' + response.status);
    return response.json();
}

function renderInto(containerId, html) {
    const container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = html;
    container.querySelectorAll('.reveal').forEach(el => observer.observe(el));
    // Re-apply "new tab" behaviour and image fallbacks for injected content
    container.querySelectorAll('a[href^="http"]').forEach(a => {
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
    });
    container.querySelectorAll('img').forEach(img => {
        if (img.complete && img.naturalWidth === 0) img.src = FALLBACK_IMG;
        img.addEventListener('error', () => { img.src = FALLBACK_IMG; }, { once: true });
    });
}

function placeholderBlock(icon, title, text) {
    // Developer note: shown when a section has no content (empty data file or load failure)
    return `<div class="col-12 reveal">
        <div class="glass-card text-center p-5">
            <div class="icon-bubble"><i class="fas ${icon} fa-2x"></i></div>
            <h4 class="mb-2">${title}</h4>
            <p class="text-muted mb-0">${text}</p>
        </div>
    </div>`;
}

async function loadAbout() {
    try {
        const items = await loadJson('data/about.json');
        if (!Array.isArray(items) || items.length === 0) throw new Error('empty');
        renderInto('aboutContainer', items.map(item => `
            <div class="col-lg-4 reveal"><div class="glass-card h-100 p-4"><h4>${escapeHtml(item.title)}</h4><p class="mb-0">${escapeHtml(item.text)}${item.link ? (item.link.modal
                ? ` <a href="#" data-bs-toggle="modal" data-bs-target="${escapeHtml(item.link.modal)}">${escapeHtml(item.link.text)}</a>`
                : ` <a href="${encodeURI(item.link.href || '#')}">${escapeHtml(item.link.text)}</a>`) : ''}</p></div></div>
        `).join(''));
    } catch (e) {
        renderInto('aboutContainer', placeholderBlock('fa-users', 'Информация скоро появится', 'Мы готовим рассказ о сообществе и его участниках — загляните чуть позже.'));
    }
}

async function loadProjects() {
    try {
        const items = await loadJson('data/projects.json');
        if (!Array.isArray(items) || items.length === 0) throw new Error('empty');
        renderInto('projectsContainer', items.map(item => `
            <div class="col-md-6 reveal">
                <div class="card project-card h-100">
                    ${item.image ? `<img src="${encodeURI(item.image)}" class="card-img-top project-image" alt="${escapeHtml(item.title)}" loading="lazy">` : ''}
                    <div class="card-body p-4">
                        <h5 class="card-title">${escapeHtml(item.title)}</h5>
                        <p class="card-text">${escapeHtml(item.text)}</p>
                        ${item.badge && item.badge.text ? `<span class="badge text-bg-${escapeHtml(item.badge.type || 'primary')}">${escapeHtml(item.badge.text)}</span>` : ''}
                        ${item.link ? `<a href="${encodeURI(item.link)}" class="btn btn-outline-primary btn-sm ms-1" target="_blank" rel="noopener noreferrer">Подробнее</a>` : ''}
                    </div>
                </div>
            </div>
        `).join(''));
    } catch (e) {
        renderInto('projectsContainer', placeholderBlock('fa-diagram-project', 'Проекты скоро появятся', 'Мы готовим анонсы мероприятий и добрых дел — загляните чуть позже.'));
    }
}

// Developer note: news render 6 per page; extra items go to pagination (#newsPagination)
const NEWS_PER_PAGE = 6;
let newsItems = [];
let newsPage = 1;

function renderNewsPage() {
    const container = document.getElementById('newsContainer');
    const pag = document.getElementById('newsPagination');
    if (!container || !pag) return;
    const pages = Math.ceil(newsItems.length / NEWS_PER_PAGE);
    const start = (newsPage - 1) * NEWS_PER_PAGE;

    renderInto('newsContainer', newsItems.slice(start, start + NEWS_PER_PAGE).map(item => `
        <div class="col-md-6 col-lg-4 reveal">
            <div class="card news-card h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <h5 class="card-title">${escapeHtml(item.title)}</h5>
                    ${item.description ? `<p class="card-text">${escapeHtml(item.description)}</p>` : ''}
                    ${item.source && item.source.name ? `<small class="text-muted d-block mb-2">Источник: ${item.source.link
                        ? `<a href="${encodeURI(item.source.link)}" target="_blank" rel="noopener noreferrer">${escapeHtml(item.source.name)}</a>`
                        : escapeHtml(item.source.name)}</small>` : ''}
                    <div class="mt-auto d-flex justify-content-between align-items-center">
                        <small class="text-muted">${formatDate(item.date)}</small>
                        ${item.link ? `<a href="${encodeURI(item.link)}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">Читать</a>` : ''}
                    </div>
                </div>
            </div>
        </div>
    `).join(''));

    if (pages <= 1) {
        pag.classList.add('d-none');
        pag.innerHTML = '';
        return;
    }
    pag.classList.remove('d-none');
    pag.innerHTML = `
        <ul class="pagination justify-content-center mb-0">
            <li class="page-item ${newsPage === 1 ? 'disabled' : ''}"><a class="page-link" href="#news" data-page="${newsPage - 1}" aria-label="Предыдущая страница">&laquo;</a></li>
            ${Array.from({ length: pages }, (_, i) => `
                <li class="page-item ${i + 1 === newsPage ? 'active' : ''}"><a class="page-link" href="#news" data-page="${i + 1}">${i + 1}</a></li>`).join('')}
            <li class="page-item ${newsPage === pages ? 'disabled' : ''}"><a class="page-link" href="#news" data-page="${newsPage + 1}" aria-label="Следующая страница">&raquo;</a></li>
        </ul>`;
}

async function loadNews() {
    const container = document.getElementById('newsContainer');
    const loader = document.getElementById('newsLoader');
    if (!container) return;

    try {
        newsItems = await loadJson('data/news.json');
        if (!Array.isArray(newsItems) || newsItems.length === 0) throw new Error('empty');

        loader?.remove();
        renderNewsPage();
        document.getElementById('newsPagination')?.addEventListener('click', e => {
            const link = e.target.closest('a[data-page]');
            if (!link) return;
            e.preventDefault();
            const page = +link.dataset.page;
            if (page < 1 || page > Math.ceil(newsItems.length / NEWS_PER_PAGE) || page === newsPage) return;
            newsPage = page;
            renderNewsPage();
            document.getElementById('news')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    } catch (error) {
        loader?.remove();
        renderInto('newsContainer', placeholderBlock('fa-newspaper', 'Новостей пока нет', 'Здесь появятся новости сообщества и отраслевые события — загляните позже.'));
    }
}

loadAbout();
loadProjects();
loadNews();

// Open all external links in a new tab (internal #anchor navigation stays in-tab)
document.querySelectorAll('a[href^="http"]').forEach(a => {
    a.target = '_blank';
    a.rel = 'noopener noreferrer';
});

// Inline placeholder if an image fails to load
const FALLBACK_IMG = 'data:image/svg+xml,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500"><rect width="800" height="500" fill="#dbeafe"/><text x="400" y="255" text-anchor="middle" font-family="Arial" font-size="28" fill="#0d6efd">Изображение недоступно</text></svg>'
);
document.querySelectorAll('img').forEach(img => {
    if (img.complete && img.naturalWidth === 0) img.src = FALLBACK_IMG;
    img.addEventListener('error', () => { img.src = FALLBACK_IMG; }, { once: true });
});

const shareBtn = document.getElementById('shareBtn');shareBtn?.addEventListener('click', async () => {
    const shareData = {
        title: document.title,
        text: 'Сообщество Сантехников — объединяем профессионов для обучения и взаимопомощи.',
        url: location.href
    };
    try {
        if (navigator.share) {
            await navigator.share(shareData);
        } else {
            await navigator.clipboard.writeText(shareData.url);
            toastEl.classList.remove('text-bg-danger', 'text-bg-primary');
            toastEl.classList.add('text-bg-success');
            toastText.textContent = 'Ссылка скопирована в буфер обмена.';
            toast.show();
        }
    } catch (e) {
        if (e.name !== 'AbortError') {
            toastEl.classList.remove('text-bg-success', 'text-bg-primary');
            toastEl.classList.add('text-bg-danger');
            toastText.textContent = 'Не удалось поделиться ссылкой.';
            toast.show();
        }
    }
});

// Cookie consent banner (choice is stored in localStorage, no tracking technologies used)
const cookieBanner = document.getElementById('cookieBanner');
if (cookieBanner && !localStorage.getItem('cookieConsent')) {
    cookieBanner.hidden = false;
}
const saveCookieChoice = decision => {
    localStorage.setItem('cookieConsent', JSON.stringify({ decision, at: new Date().toISOString() }));
    cookieBanner.hidden = true;
};
document.getElementById('cookieAccept')?.addEventListener('click', () => saveCookieChoice('accepted'));
document.getElementById('cookieDecline')?.addEventListener('click', () => saveCookieChoice('declined'));
document.getElementById('cookieClose')?.addEventListener('click', () => { cookieBanner.hidden = true; });

// Телефон: маска +7 (XXX) XXX-XX-XX — форматирование на лету, цифры до 11 штук
function formatPhone(raw) {
    let d = raw.replace(/\D/g, '');
    if (!d) return '';
    if (d[0] === '8') d = '7' + d.slice(1); // 8 -> +7
    if (d[0] === '9') d = '7' + d;          // ввели без кода страны
    d = d.slice(0, 11);
    const rest = d.slice(1);
    let out = '+' + d[0];
    if (rest.length > 0) out += ' (' + rest.slice(0, 3);
    if (rest.length >= 3) out += ')';
    if (rest.length > 3) out += ' ' + rest.slice(3, 6);
    if (rest.length > 6) out += '-' + rest.slice(6, 8);
    if (rest.length > 8) out += '-' + rest.slice(8, 10);
    return out;
}

const phoneInput = document.querySelector('#contactForm input[name="phone"]');
phoneInput?.addEventListener('input', function () {
    this.value = formatPhone(this.value);
    this.setCustomValidity('');
    const digits = this.value.replace(/\D/g, '');
    if (this.value !== '' && (digits.length < 10 || digits.length > 15)) {
        this.setCustomValidity('Телефон должен содержать от 10 до 15 цифр');
    }
});

// Сообщение: счётчик символов (лимит задан maxlength в разметке и проверкой на сервере)
const MESSAGE_MAX = 300;
const messageInput = document.querySelector('#contactForm textarea[name="message"]');
const messageCounter = document.getElementById('messageCounter');
function updateMessageCounter() {
    if (!messageInput || !messageCounter) return;
    const len = messageInput.value.length;
    messageCounter.textContent = `${len}/${MESSAGE_MAX}`;
    // text-muted в Bootstrap объявлен позже text-danger — держим только один из классов
    messageCounter.classList.toggle('text-danger', len >= MESSAGE_MAX);
    messageCounter.classList.toggle('text-muted', len < MESSAGE_MAX);
}
messageInput?.addEventListener('input', updateMessageCounter);

document.getElementById('contactForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    // novalidate: вместо браузерных бабблов поля подсвечивает Bootstrap (was-validated)
    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
    }

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
            this.classList.remove('was-validated');
            updateMessageCounter();
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
    toastText.textContent = 'Приём пожертвований откроется после регистрации сбора в реестре. Пока помогайте волонтёрством!';
    toast.show();
    setTimeout(() => {
        toastEl.classList.remove('text-bg-primary');
        toastEl.classList.add('text-bg-success');
    }, 2400);
}