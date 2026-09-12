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

async function loadAbout() {
    try {
        const items = await loadJson('data/about.json');
        if (!Array.isArray(items) || items.length === 0) return;
        renderInto('aboutContainer', items.map(item => `
            <div class="col-lg-4 reveal"><div class="glass-card h-100 p-4"><h4>${escapeHtml(item.title)}</h4><p class="mb-0">${escapeHtml(item.text)}${item.link ? (item.link.modal
                ? ` <a href="#" data-bs-toggle="modal" data-bs-target="${escapeHtml(item.link.modal)}">${escapeHtml(item.link.text)}</a>`
                : ` <a href="${encodeURI(item.link.href || '#')}">${escapeHtml(item.link.text)}</a>`) : ''}</p></div></div>
        `).join(''));
    } catch (e) { /* keep empty section */ }
}

async function loadProjects() {
    try {
        const items = await loadJson('data/projects.json');
        if (!Array.isArray(items) || items.length === 0) return;
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
    } catch (e) { /* keep empty section */ }
}

async function loadNews() {
    const container = document.getElementById('newsContainer');
    const loader = document.getElementById('newsLoader');
    if (!container) return;

    try {
        const items = await loadJson('data/news.json');
        if (!Array.isArray(items) || items.length === 0) throw new Error('empty');

        loader?.remove();
        renderInto('newsContainer', items.map(item => `
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
    } catch (error) {
        loader?.remove();
        container.innerHTML = '<div class="col-12"><div class="alert alert-warning text-center mb-0">Не удалось загрузить новости. Обновите страницу позже.</div></div>';
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
    toastText.textContent = 'Приём пожертвований откроется после регистрации сбора в реестре. Пока помогайте волонтёрством!';
    toast.show();
    setTimeout(() => {
        toastEl.classList.remove('text-bg-primary');
        toastEl.classList.add('text-bg-success');
    }, 2400);
}