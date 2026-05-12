let currentUser = null;
let isLoggedIn = false;
let pendingRegistration = null;
const API_BASE = 'api/';

const API = {
    async request(url, method = 'GET', data = null) {
        const opts = { method, headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin' };
        if (data && method !== 'GET') opts.body = JSON.stringify(data);
        const res = await fetch(API_BASE + url, opts);
        const text = await res.text();
        try {
            const json = JSON.parse(text);
            if (!res.ok) throw new Error(json.message || 'Ошибка сервера');
            return json;
        } catch (e) {
            if (e.message !== 'Ошибка сервера') throw new Error('Сервер вернул HTML вместо JSON');
            throw e;
        }
    },
    checkAuth: () => API.request('auth.php'),
    login: (email, password) => API.request('login.php', 'POST', { email, password }),
    register: (name, email, phone, password, verified = false) => API.request('register.php', 'POST', { name, email, phone, password, verified }),
    logout: () => API.request('logout.php', 'POST'),
    sendCode: (email) => API.request('send-code.php', 'POST', { email }),
    verifyCode: (email, code) => API.request('verify-code.php', 'POST', { email, code }),
    getProfile: () => API.request('profile.php'),
    updateProfile: (data) => API.request('profile.php', 'POST', data),
    createRequest: (data) => API.request('requests.php?action=create', 'POST', data),
    getMyRequests: () => API.request('requests.php?action=my'),
    cancelRequest: (id) => API.request('requests.php?action=cancel', 'POST', { id }),
    getReviews: () => API.request('reviews.php?action=all'),
    createReview: (data) => API.request('reviews.php?action=create', 'POST', data),
    getMyReview: () => API.request('reviews.php?action=my'),
    updateReview: (data) => API.request('reviews.php?action=update', 'POST', data),
    submitVacancy: (data) => API.request('resume.php', 'POST', data),
};

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

function showToast(msg, type = 'info') {
    const old = $('.toast');
    if (old) old.remove();
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

function openModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('active'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('active'); document.body.style.overflow = ''; }
}

$$('.modal-close').forEach(b => b.addEventListener('click', () => closeModal(b.dataset.modal)));
$$('.modal-overlay').forEach(o => o.addEventListener('click', e => { if (e.target === o) closeModal(o.id); }));

function updateAuthUI() {
    const authNav = $('#authNav');
    const authBtns = $('#authButtons');
    const authMob = $('#authButtonsMobile');
    const authNavMob = $('#authNavMobile');
    
    if (isLoggedIn && currentUser) {
        if (authNav) authNav.classList.add('active');
        if (authBtns) authBtns.style.display = 'none';
        if (authMob) authMob.classList.remove('show-mobile');
        if (authNavMob) authNavMob.classList.add('show-mobile');
        const nameEl = $('#userName');
        const nameMob = $('#userNameMobile');
        if (nameEl) nameEl.textContent = currentUser.name;
        if (nameMob) nameMob.textContent = currentUser.name;
    } else {
        if (authNav) authNav.classList.remove('active');
        if (authBtns) authBtns.style.display = 'flex';
        if (authMob) authMob.classList.add('show-mobile');
        if (authNavMob) authNavMob.classList.remove('show-mobile');
    }
}

function showSection(id) {
    const sections = {
        '.hero': ['home'],
        '.services': ['home', 'contacts'],
        '.employees-slider': ['home'],
        '.reviews-section': ['home', 'reviews'],
        '.profile-section': ['profile'],
        '#vacancies': ['vacancies'],
        '#contacts': ['contacts'],
    };
    
    for (const [sel, visible] of Object.entries(sections)) {
        const el = $(sel);
        if (el) el.style.display = visible.includes(id) ? '' : 'none';
    }
    
    if (id === 'vacancies') {
        const svc = $('.services');
        if (svc && svc.id !== 'contacts') svc.style.display = 'none';
    }
    
    $$('.nav-links a').forEach(l => {
        l.classList.remove('active');
        if (l.getAttribute('href') === `#${id}`) l.classList.add('active');
    });
    
    if (id === 'profile' && isLoggedIn) loadProfileData();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

const mobileBtn = $('#mobileMenuBtn');
const navLinks = $('#navLinks');
if (mobileBtn) {
    mobileBtn.addEventListener('click', () => {
        navLinks.classList.toggle('active');
        const icon = mobileBtn.querySelector('i');
        icon.className = navLinks.classList.contains('active') ? 'fas fa-times' : 'fas fa-bars';
    });
}
$$('.nav-links a').forEach(l => l.addEventListener('click', () => {
    navLinks.classList.remove('active');
    if (mobileBtn) mobileBtn.querySelector('i').className = 'fas fa-bars';
}));

let currentSlide = 0, totalSlides = 0, slideTimer = null;
function initSlider() {
    const slider = $('#slider');
    if (!slider) return;
    totalSlides = $$('.slide').length;
    const dots = $('#sliderDots');
    if (dots) {
        dots.innerHTML = '';
        for (let i = 0; i < totalSlides; i++) {
            const d = document.createElement('div');
            d.className = `dot ${i === 0 ? 'active' : ''}`;
            d.addEventListener('click', () => goToSlide(i));
            dots.appendChild(d);
        }
    }
    startAutoSlide();
    slider.addEventListener('mouseenter', stopAutoSlide);
    slider.addEventListener('mouseleave', startAutoSlide);
}
function goToSlide(i) {
    const slider = $('#slider');
    if (!slider) return;
    if (i < 0) i = totalSlides - 1;
    if (i >= totalSlides) i = 0;
    currentSlide = i;
    slider.style.transform = `translateX(-${currentSlide * 100}%)`;
    $$('.dot').forEach((d, idx) => d.classList.toggle('active', idx === currentSlide));
}
function startAutoSlide() { stopAutoSlide(); slideTimer = setInterval(() => goToSlide(currentSlide + 1), 5000); }
function stopAutoSlide() { clearInterval(slideTimer); }
$('#prevBtn')?.addEventListener('click', () => goToSlide(currentSlide - 1));
$('#nextBtn')?.addEventListener('click', () => goToSlide(currentSlide + 1));

// АВТОРИЗАЦИЯ
$('#loginForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    btn.disabled = true;
    btn.textContent = 'Вход...';
    try {
        const res = await API.login($('#loginEmail').value, $('#loginPassword').value);
        if (res.success) {
            isLoggedIn = true;
            currentUser = res.user;
            updateAuthUI();
            closeModal('authModal');
            showToast('Вход выполнен', 'success');
            e.target.reset();
        } else {
            showToast(res.message || 'Ошибка входа', 'error');
        }
    } catch (err) { showToast(err.message, 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Войти'; }
});

$('#registerForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    btn.disabled = true;
    btn.textContent = 'Отправка кода...';
    try {
        const res = await API.sendCode($('#registerEmail').value);
        if (res.success) {
            pendingRegistration = {
                name: $('#registerName').value,
                email: $('#registerEmail').value,
                phone: $('#registerPhone').value,
                password: $('#registerPassword').value,
            };
            $('#verifyEmailText').textContent = pendingRegistration.email;
            closeModal('authModal');
            openModal('verifyEmailModal');
            showToast('Код отправлен на email', 'info');
            if (res.test_code) showTestCode(res.test_code);
        } else {
            showToast(res.message || 'Ошибка', 'error');
        }
    } catch (err) { showToast(err.message, 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Зарегистрироваться'; }
});

function showTestCode(code) {
    const old = document.getElementById('testCodeDisplay');
    if (old) old.remove();
    const div = document.createElement('div');
    div.id = 'testCodeDisplay';
    div.style.cssText = 'background:#ff6b35;color:#fff;padding:10px;border-radius:6px;text-align:center;font-size:24px;font-weight:bold;margin:15px 0;letter-spacing:5px';
    div.textContent = code;
    $('#verifyEmailForm').prepend(div);
}

$('#verifyEmailForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    btn.disabled = true;
    btn.textContent = 'Проверка...';
    try {
        const vRes = await API.verifyCode(pendingRegistration.email, $('#verifyCode').value);
        if (vRes.success) {
            btn.textContent = 'Регистрация...';
            const regRes = await API.register(pendingRegistration.name, pendingRegistration.email, pendingRegistration.phone, pendingRegistration.password, true);
            if (regRes.success) {
                isLoggedIn = true;
                currentUser = regRes.user;
                updateAuthUI();
                closeModal('verifyEmailModal');
                showToast('Регистрация успешна!', 'success');
                pendingRegistration = null;
                e.target.reset();
            } else {
                showToast(regRes.message || 'Ошибка регистрации', 'error');
            }
        } else {
            showToast(vRes.message || 'Неверный код', 'error');
        }
    } catch (err) { showToast(err.message, 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Подтвердить'; }
});

$('#resendCode')?.addEventListener('click', async (e) => {
    e.preventDefault();
    if (!pendingRegistration?.email) return;
    try {
        const res = await API.sendCode(pendingRegistration.email);
        if (res.success) {
            showToast('Код отправлен повторно', 'success');
            if (res.test_code) showTestCode(res.test_code);
        }
    } catch (err) { showToast(err.message, 'error'); }
});

// ЗАЯВКА НА РЕМОНТ
$('#requestForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!isLoggedIn) { openModal('authModal'); showToast('Необходимо авторизоваться', 'error'); return; }
    const btn = e.target.querySelector('button');
    btn.disabled = true;
    btn.textContent = 'Отправка...';
    try {
        let photo = null;
        const file = $('#requestPhoto')?.files[0];
        if (file) {
            const fd = new FormData();
            fd.append('file', file);
            fd.append('type', 'request');
            const upRes = await fetch(API_BASE + 'upload.php', { method: 'POST', body: fd }).then(r => r.json());
            if (upRes.success) photo = upRes.path;
        }
        const data = {
            service_type: $('#requestServiceType').value,
            vehicle_model: $('#requestVehicleModel').value,
            problem_description: $('#requestProblemDescription').value,
            preferred_date: $('#requestPreferredDate').value,
            phone: $('#requestPhone').value,
            photo,
        };
        const res = await API.createRequest(data);
        if (res.success) {
            closeModal('requestModal');
            showToast('Заявка создана', 'success');
            e.target.reset();
            const preview = $('#requestPhotoPreview');
            if (preview) preview.innerHTML = '';
            if ($('.profile-section')?.style.display !== 'none') loadRequests();
        } else {
            showToast(res.message || 'Ошибка', 'error');
        }
    } catch (err) { showToast(err.message, 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Отправить'; }
});

// ОТЗЫВ
$('#reviewForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!isLoggedIn) { openModal('authModal'); showToast('Необходимо авторизоваться', 'error'); return; }
    const btn = e.target.querySelector('button');
    btn.disabled = true;
    try {
        const ratingEl = document.querySelector('input[name="reviewRating"]:checked');
        if (!ratingEl) { showToast('Выберите оценку', 'error'); btn.disabled = false; return; }
        const res = await API.createReview({ rating: parseInt(ratingEl.value), comment: $('#reviewCommentNew').value });
        if (res.success) {
            closeModal('reviewModal');
            showToast('Отзыв добавлен', 'success');
            e.target.reset();
            loadReviews();
        } else {
            showToast(res.message || 'Ошибка', 'error');
        }
    } catch (err) { showToast(err.message, 'error'); }
    finally { btn.disabled = false; }
});

// ПРОФИЛЬ
$('#profileForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    btn.disabled = true;
    btn.textContent = 'Сохранение...';
    try {
        const data = { name: $('#profileName').value, phone: $('#profilePhone').value };
        const cp = $('#currentPassword').value;
        const np = $('#newPassword').value;
        if (cp || np) { data.current_password = cp; data.new_password = np; }
        const res = await API.updateProfile(data);
        if (res.success) {
            showToast('Профиль обновлен', 'success');
            if (currentUser) currentUser.name = data.name;
            updateAuthUI();
            $('#currentPassword').value = '';
            $('#newPassword').value = '';
        } else {
            showToast(res.message || 'Ошибка', 'error');
        }
    } catch (err) { showToast(err.message, 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Сохранить'; }
});

$('#myReviewForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
        const ratingEl = document.querySelector('#myReviewForm input[name="rating"]:checked');
        if (!ratingEl) { showToast('Выберите оценку', 'error'); return; }
        const hasReview = (await API.getMyReview()).has_review;
        const res = hasReview
            ? await API.updateReview({ rating: parseInt(ratingEl.value), comment: $('#reviewComment').value })
            : await API.createReview({ rating: parseInt(ratingEl.value), comment: $('#reviewComment').value });
        if (res.success) {
            showToast('Отзыв сохранен', 'success');
            loadMyReview();
        } else {
            showToast(res.message || 'Ошибка', 'error');
        }
    } catch (err) { showToast(err.message, 'error'); }
});

// ВАКАНСИЯ
$('#vacancyForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Отправка...';
    
    try {
        const data = {
            position: $('#vacancyPosition').value,
            full_name: $('#vacancyFullName').value,
            phone: $('#vacancyPhone').value,
            email: $('#vacancyEmail').value,
            call_time: $('#vacancyCallTime').value,
            age: $('#vacancyAge').value,
            experience: $('#vacancyExperience').value,
            comment: $('#vacancyComment').value,
        };
        
        if (!data.position || !data.full_name || !data.phone || !data.email) {
            showToast('Заполните все обязательные поля', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHTML;
            return;
        }
        
        const res = await API.submitVacancy(data);
        
        if (res.success) {
            showToast(res.message || 'Заявка отправлена!', 'success');
            e.target.reset();
            $('#vacancyPosition').value = '';
            $$('.vacancy-card').forEach(c => c.classList.remove('selected'));
            closeModal('vacancyModal');
        } else {
            showToast(res.message || 'Ошибка отправки', 'error');
        }
    } catch (err) {
        console.error('ОШИБКА ВАКАНСИИ:', err);
        showToast(err.message || 'Ошибка соединения с сервером', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    }
});

// ВАКАНСИЯ — автозаполнение из профиля
function fillVacancyFromProfile() {
    if (isLoggedIn && currentUser) {
        if (!$('#vacancyFullName').value) {
            $('#vacancyFullName').value = currentUser.name || '';
        }
        if (!$('#vacancyPhone').value) {
            $('#vacancyPhone').value = currentUser.phone || '';
        }
        if (!$('#vacancyEmail').value) {
            $('#vacancyEmail').value = currentUser.email || '';
        }
    }
}

$$('.btn-select-vacancy').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const card = this.closest('.vacancy-card');
        const pos = card.getAttribute('data-position');
        $$('.vacancy-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        $('#vacancyPosition').value = pos;
        fillVacancyFromProfile();
        openModal('vacancyModal');
    });
});

$$('.vacancy-card').forEach(card => {
    card.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-select-vacancy')) return;
        const pos = this.getAttribute('data-position');
        $$('.vacancy-card').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
        $('#vacancyPosition').value = pos;
        fillVacancyFromProfile();
        openModal('vacancyModal');
    });
});

// ЗАГРУЗКА ДАННЫХ
async function loadProfileData() {
    if (!isLoggedIn) return;
    try {
        const res = await API.getProfile();
        if (res.success) {
            $('#profileName').value = res.user.name || '';
            $('#profileEmail').value = res.user.email || '';
            $('#profilePhone').value = res.user.phone || '';
            if (res.stats) {
                $('#totalRequestsStat').textContent = res.stats.total_requests || 0;
                $('#completedRequestsStat').textContent = res.stats.completed_requests || 0;
            }
            loadRequests();
            loadMyReview();
        }
    } catch (e) { showToast('Ошибка загрузки профиля', 'error'); }
}

async function loadRequests() {
    const list = $('#requestsList');
    if (!list) return;
    try {
        const res = await API.getMyRequests();
        if (res.success && res.requests?.length) {
            list.innerHTML = res.requests.map(r => `
                <div class="request-card">
                    <div class="request-info">
                        <div class="request-id">Заявка #${r.id}</div>
                        <div class="request-service">${esc(r.service_type)}</div>
                        <div class="request-vehicle">${esc(r.vehicle_model)}</div>
                        <div class="request-date"><i class="far fa-calendar"></i> ${formatDate(r.preferred_date)}</div>
                    </div>
                    <div class="request-status status-${r.status}">${statusText(r.status)}</div>
                    ${r.status === 'new' ? `<button class="btn btn-secondary btn-small" onclick="cancelRequest(${r.id})">Отменить</button>` : ''}
                </div>
            `).join('');
        } else {
            list.innerHTML = '<div class="no-reviews">У вас пока нет заявок</div>';
        }
    } catch (e) { list.innerHTML = '<div class="no-reviews">Ошибка загрузки</div>'; }
}

async function loadReviews() {
    const grid = $('#reviewsGrid');
    if (!grid) return;
    try {
        const res = await API.getReviews();
        if (res.success) {
            $('#averageRatingText').textContent = res.average_rating || '0.0';
            $('#totalReviews').textContent = res.total_reviews || 0;
            const stars = '★'.repeat(Math.floor(res.average_rating || 0)) + '☆'.repeat(5 - Math.floor(res.average_rating || 0));
            $('#averageStars').innerHTML = `<span style="color:#ffc107">${stars}</span>`;
            if (res.reviews?.length) {
                grid.innerHTML = res.reviews.map(r => `
                    <div class="review-card">
                        <div class="review-header"><span class="review-author">${esc(r.user_name)}</span><span class="review-date">${r.date_formatted}</span></div>
                        <div class="review-rating">${'★'.repeat(r.rating)}${'☆'.repeat(5 - r.rating)}</div>
                        <div class="review-text">${esc(r.comment)}</div>
                    </div>
                `).join('');
            } else {
                grid.innerHTML = '<div class="no-reviews">Пока нет отзывов</div>';
            }
        }
    } catch (e) { grid.innerHTML = '<div class="no-reviews">Ошибка загрузки</div>'; }
}

async function loadMyReview() {
    try {
        const res = await API.getMyReview();
        if (res.success && res.has_review && res.review) {
            const rb = document.querySelector(`#myReviewForm input[name="rating"][value="${res.review.rating}"]`);
            if (rb) rb.checked = true;
            $('#reviewComment').value = res.review.comment || '';
            $('#saveReviewBtn').innerHTML = '<i class="fas fa-save"></i> Обновить отзыв';
        } else {
            $$('#myReviewForm input[name="rating"]').forEach(r => r.checked = false);
            $('#reviewComment').value = '';
            $('#saveReviewBtn').innerHTML = '<i class="fas fa-save"></i> Сохранить';
        }
    } catch (e) {}
}

async function cancelRequest(id) {
    if (!confirm('Отменить заявку?')) return;
    try {
        await API.cancelRequest(id);
        showToast('Заявка отменена', 'success');
        loadRequests();
    } catch (e) { showToast(e.message, 'error'); }
}

function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' }) : ''; }
function statusText(s) { return { new: 'Новая', in_progress: 'В работе', completed: 'Выполнена', cancelled: 'Отменена' }[s] || s; }

$('#requestPhoto')?.addEventListener('change', function() {
    const file = this.files[0];
    const preview = $('#requestPhotoPreview');
    if (!file?.type.startsWith('image/')) return;
    if (file.size > 5 * 1024 * 1024) { showToast('Максимум 5 МБ', 'error'); return; }
    const reader = new FileReader();
    reader.onload = e => {
        preview.innerHTML = `
            <div class="file-info">
                <i class="fas fa-image"></i> <span>${file.name}</span> <small>(${(file.size/1024).toFixed(1)} КБ)</small>
                <button class="remove-file" onclick="removePhoto()"><i class="fas fa-times"></i> Удалить</button>
            </div>
            <img src="${e.target.result}" alt="Preview">
        `;
    };
    reader.readAsDataURL(file);
});

function removePhoto() {
    const inp = $('#requestPhoto');
    const prev = $('#requestPhotoPreview');
    if (inp) inp.value = '';
    if (prev) prev.innerHTML = '';
}

function openAuthModal(mode = 'login') {
    openModal('authModal');
    setAuthMode(mode);
}

function setAuthMode(mode) {
    const toggle = $('#modalAuthToggle');
    if (toggle) toggle.dataset.active = mode;
    $$('.auth-toggle-option').forEach(o => o.classList.toggle('active', o.dataset.action === mode));
    $('#authModalTitle').textContent = mode === 'login' ? 'Вход' : 'Регистрация';
    $('#loginForm').classList.toggle('active', mode === 'login');
    $('#registerForm').classList.toggle('active', mode === 'register');
}

$('#modalAuthToggle')?.addEventListener('click', e => {
    const opt = e.target.closest('.auth-toggle-option');
    if (opt) setAuthMode(opt.dataset.action);
});

$('#loginBtnNav')?.addEventListener('click', () => openAuthModal('login'));
$('#registerBtnNav')?.addEventListener('click', () => openAuthModal('register'));
$('#authActionBtnMobile')?.addEventListener('click', () => openAuthModal('login'));
$('#userName')?.addEventListener('click', () => isLoggedIn ? showSection('profile') : openAuthModal('login'));

async function handleLogout() {
    try {
        await API.logout();
        isLoggedIn = false;
        currentUser = null;
        updateAuthUI();
        showSection('home');
        showToast('Выход выполнен', 'success');
    } catch (e) { showToast(e.message, 'error'); }
}

$$('.btn-logout-mobile').forEach(b => b?.addEventListener('click', handleLogout));
$('#logoutBtnProfile')?.addEventListener('click', handleLogout);

$('#openRequestModal')?.addEventListener('click', () => isLoggedIn ? openModal('requestModal') : openAuthModal('login'));
$('#openRequestModalProfile')?.addEventListener('click', () => openModal('requestModal'));
$('#openReviewModal')?.addEventListener('click', () => isLoggedIn ? openModal('reviewModal') : openAuthModal('login'));

$$('.btn-request').forEach(b => b.addEventListener('click', () => {
    if (isLoggedIn) {
        const sel = $('#requestServiceType');
        if (sel) sel.value = b.dataset.service;
        openModal('requestModal');
    } else { openAuthModal('login'); showToast('Авторизуйтесь', 'error'); }
}));

$$('.nav-links a').forEach(a => a.addEventListener('click', function(e) {
    const href = this.getAttribute('href');
    e.preventDefault();
    if (href === '#') showSection('home');
    else if (href === '#profile') isLoggedIn ? showSection('profile') : openAuthModal('login');
    else if (href === '#reviews') showSection('reviews');
    else if (href === '#contacts') showSection('contacts');
    else if (href === '#vacancies') showSection('vacancies');
}));

$$('.profile-tab').forEach(t => t.addEventListener('click', () => {
    $$('.profile-tab').forEach(t2 => t2.classList.remove('active'));
    t.classList.add('active');
    $$('.profile-content').forEach(c => c.classList.remove('active'));
    $(`#${t.dataset.tab}Tab`)?.classList.add('active');
}));

function setMinDates() {
    const today = new Date().toISOString().split('T')[0];
    const d = $('#requestPreferredDate');
    if (d) d.min = today;
}

document.addEventListener('DOMContentLoaded', async () => {
    initSlider();
    setMinDates();
    try {
        const auth = await API.checkAuth();
        if (auth.loggedIn && auth.user) {
            isLoggedIn = true;
            currentUser = auth.user;
        }
    } catch (e) {}
    updateAuthUI();
    loadReviews();
    const vac = $('#vacancies');
    if (vac) vac.style.display = 'none';
});

window.cancelRequest = cancelRequest;
window.removePhoto = removePhoto;