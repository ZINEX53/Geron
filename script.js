let currentUser = null;
let isLoggedIn = false;
let pendingRegistration = null;
const API_BASE = 'api/';

const API = {
    async request(url, method = 'GET', data = null) {
        const opts = {
            method,
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin'
        };
        if (data && method !== 'GET') {
            opts.body = JSON.stringify(data);
        }

        const res = await fetch(API_BASE + url, opts);
        const text = await res.text();

        try {
            const json = JSON.parse(text);
            if (!res.ok) {
                throw new Error(json.message || 'Ошибка сервера');
            }
            return json;
        } catch (e) {
            if (e.message !== 'Ошибка сервера') {
                throw new Error('Сервер вернул HTML вместо JSON');
            }
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
    checkAdmin: () => API.request('check-admin.php'),
};

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

// ==================== TOAST ====================
function showToast(msg, type = 'info') {
    const old = $('.toast');
    if (old) old.remove();
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// ==================== МОДАЛКИ ====================
function openModal(id) {
    const m = document.getElementById(id);
    if (m) {
        m.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const m = document.getElementById(id);
    if (m) {
        m.classList.remove('active');
        document.body.style.overflow = '';
    }
}

$$('.modal-close').forEach(b => {
    b.addEventListener('click', () => closeModal(b.dataset.modal));
});

$$('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => {
        if (e.target === o) closeModal(o.id);
    });
});

// ==================== ПРОВЕРКА АДМИНА ====================
async function checkAdminAccess() {
    try {
        const res = await API.checkAdmin();
        const link = $('#adminPanelLink');
        if (link) {
            link.style.display = res.isAdmin ? '' : 'none';
        }
    } catch (e) {
        console.error('Ошибка проверки админа:', e);
    }
}

// ==================== UI АВТОРИЗАЦИИ ====================
function updateAuthUI() {
    const authNav = $('#authNav');
    const authBtns = $('#authButtons');
    const authMob = $('#authButtonsMobile');
    const authNavMob = $('#authNavMobile');
    const nameEl = $('#userName');
    const nameMob = $('#userNameMobile');
    const adminLink = $('#adminPanelLink');

    if (isLoggedIn && currentUser) {
        if (authNav) authNav.classList.add('active');
        if (authBtns) authBtns.style.display = 'none';
        if (authMob) authMob.classList.remove('show-mobile');
        if (authNavMob) authNavMob.classList.add('show-mobile');
        if (nameEl) nameEl.textContent = currentUser.name;
        if (nameMob) nameMob.textContent = currentUser.name;
        checkAdminAccess();
    } else {
        if (authNav) authNav.classList.remove('active');
        if (authBtns) authBtns.style.display = 'flex';
        if (authMob) authMob.classList.add('show-mobile');
        if (authNavMob) authNavMob.classList.remove('show-mobile');
        if (adminLink) adminLink.style.display = 'none';
    }
}

// ==================== НАВИГАЦИЯ ПО СЕКЦИЯМ ====================
function showSection(id) {
    const heroEl = $('.hero');
    const servicesEl = $('.services');
    const sliderEl = $('.employees-slider');
    const reviewsEl = $('.reviews-section');
    const profileEl = $('.profile-section');
    const vacanciesEl = $('#vacancies');
    const contactsEl = $('#contacts');
    const newsSection = $('#news-section');

    if (heroEl) heroEl.style.display = 'none';
    if (sliderEl) sliderEl.style.display = 'none';
    if (reviewsEl) reviewsEl.style.display = 'none';
    if (profileEl) profileEl.style.display = 'none';
    if (vacanciesEl) vacanciesEl.style.display = 'none';
    if (contactsEl) contactsEl.style.display = 'none';
    if (newsSection) newsSection.style.display = 'none';

    const allServices = document.querySelectorAll('.services');
    allServices.forEach(el => el.style.display = 'none');

    if (id === 'home') {
        if (heroEl) heroEl.style.display = '';
        if (sliderEl) sliderEl.style.display = '';
        if (reviewsEl) reviewsEl.style.display = '';
        if (newsSection) newsSection.style.display = '';
        allServices.forEach(el => el.style.display = '');
    } else if (id === 'services') {
        const svc = document.querySelector('#services');
        if (svc) svc.style.display = '';
        const ctc = document.querySelector('#contacts');
        if (ctc) ctc.style.display = '';
    } else if (id === 'reviews') {
        if (reviewsEl) reviewsEl.style.display = '';
    } else if (id === 'profile') {
        if (profileEl) profileEl.style.display = '';
        if (isLoggedIn) loadProfileData();
    } else if (id === 'vacancies') {
        if (vacanciesEl) vacanciesEl.style.display = '';
    } else if (id === 'contacts') {
        const ctc = document.querySelector('#contacts');
        if (ctc) ctc.style.display = '';
    }

    $$('.nav-links a').forEach(l => {
        l.classList.remove('active');
        if (l.getAttribute('href') === `#${id}`) l.classList.add('active');
        if (id === 'home' && l.getAttribute('href') === '#') l.classList.add('active');
    });

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ==================== МОБИЛЬНОЕ МЕНЮ ====================
const mobileBtn = $('#mobileMenuBtn');
const navLinksEl = $('#navLinks');

if (mobileBtn) {
    mobileBtn.addEventListener('click', () => {
        navLinksEl.classList.toggle('active');
        const icon = mobileBtn.querySelector('i');
        if (icon) {
            icon.className = navLinksEl.classList.contains('active') ? 'fas fa-times' : 'fas fa-bars';
        }
    });
}

$$('.nav-links a').forEach(l => {
    l.addEventListener('click', () => {
        navLinksEl.classList.remove('active');
        if (mobileBtn) {
            const icon = mobileBtn.querySelector('i');
            if (icon) icon.className = 'fas fa-bars';
        }
    });
});

// ==================== СЛАЙДЕР ====================
let currentSlide = 0;
let totalSlides = 0;
let slideTimer = null;

function goToSlide(i) {
    const slider = $('#slider');
    if (!slider) return;
    if (i < 0) i = totalSlides - 1;
    if (i >= totalSlides) i = 0;
    currentSlide = i;
    slider.style.transform = `translateX(-${currentSlide * 100}%)`;
    $$('.dot').forEach((d, idx) => {
        d.classList.toggle('active', idx === currentSlide);
    });
}

function startAutoSlide() {
    stopAutoSlide();
    if (totalSlides > 0) {
        slideTimer = setInterval(() => goToSlide(currentSlide + 1), 5000);
    }
}

function stopAutoSlide() {
    clearInterval(slideTimer);
}

$('#prevBtn')?.addEventListener('click', () => goToSlide(currentSlide - 1));
$('#nextBtn')?.addEventListener('click', () => goToSlide(currentSlide + 1));

// ==================== ЗАГРУЗКА СОТРУДНИКОВ ====================
async function loadEmployees() {
    try {
        const res = await API.request('employees.php');
        if (res.success && res.employees && res.employees.length > 0) {
            const slider = $('#slider');
            if (!slider) return;
            const gradients = ['#2d3f63', '#3d5a80', '#4a6fa5', '#5c80bc', '#6b8fc7', '#7a9ed0'];
            slider.innerHTML = res.employees.map((emp, i) => {
                const initials = emp.name.split(' ').map(n => n.charAt(0)).join('');
                const grad = gradients[i % gradients.length];
                return `
                <div class="slide">
                    <div class="slide-img" style="background:linear-gradient(135deg,${grad},#1a2b4c);display:flex;align-items:center;justify-content:center;font-size:80px;color:rgba(255,255,255,0.1);font-family:'Font Awesome 6 Free';font-weight:900">
                        ${initials}
                    </div>
                    <div class="slide-info">
                        <h3>${esc(emp.name)}</h3>
                        <p>${esc(emp.position)}${emp.experience ? ', ' + esc(emp.experience) + ' опыта' : ''}${emp.description ? '. ' + esc(emp.description) : ''}</p>
                    </div>
                </div>`;
            }).join('');
            totalSlides = res.employees.length;
            currentSlide = 0;
            goToSlide(0);
            initSliderDots();
            startAutoSlide();
            slider.addEventListener('mouseenter', stopAutoSlide);
            slider.addEventListener('mouseleave', startAutoSlide);
        }
    } catch (e) {
        console.error('Ошибка загрузки сотрудников:', e);
    }
}

function initSliderDots() {
    const dots = $('#sliderDots');
    if (!dots) return;
    dots.innerHTML = '';
    for (let i = 0; i < totalSlides; i++) {
        const d = document.createElement('div');
        d.className = `dot ${i === 0 ? 'active' : ''}`;
        d.addEventListener('click', () => goToSlide(i));
        dots.appendChild(d);
    }
}

// ==================== ЗАГРУЗКА ВАКАНСИЙ ====================
async function loadVacancies() {
    try {
        const res = await API.request('vacancies.php');
        if (res.success && res.vacancies && res.vacancies.length > 0) {
            const grid = $('#vacanciesGrid');
            if (!grid) return;
            grid.innerHTML = res.vacancies.map(v => `
                <div class="vacancy-card" data-position="${esc(v.title)}">
                    <div class="vacancy-icon"><i class="fas ${esc(v.icon || 'fa-wrench')}"></i></div>
                    <h3>${esc(v.title)}</h3>
                    <p>${esc(v.description || '')}</p>
                    <div class="vacancy-salary">${esc(v.salary || '')}</div>
                    <button class="btn btn-outline btn-select-vacancy">Выбрать</button>
                </div>
            `).join('');
            bindVacancyEvents();
        }
    } catch (e) {
        console.error('Ошибка загрузки вакансий:', e);
    }
}

function bindVacancyEvents() {
    $$('.btn-select-vacancy').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const card = this.closest('.vacancy-card');
            if (!card) return;
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
}

function fillVacancyFromProfile() {
    if (isLoggedIn && currentUser) {
        if (!$('#vacancyFullName').value) $('#vacancyFullName').value = currentUser.name || '';
        if (!$('#vacancyPhone').value) $('#vacancyPhone').value = currentUser.phone || '';
        if (!$('#vacancyEmail').value) $('#vacancyEmail').value = currentUser.email || '';
    }
}

// ==================== ФОРМА ВХОДА ====================
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
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Войти';
    }
});

// ==================== ФОРМА РЕГИСТРАЦИИ ====================
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
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Зарегистрироваться';
    }
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

// ==================== ПОДТВЕРЖДЕНИЕ EMAIL ====================
$('#verifyEmailForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    btn.disabled = true;
    btn.textContent = 'Проверка...';
    try {
        const vRes = await API.verifyCode(pendingRegistration.email, $('#verifyCode').value);
        if (vRes.success) {
            btn.textContent = 'Регистрация...';
            const regRes = await API.register(
                pendingRegistration.name,
                pendingRegistration.email,
                pendingRegistration.phone,
                pendingRegistration.password,
                true
            );
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
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Подтвердить';
    }
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
    } catch (err) {
        showToast(err.message, 'error');
    }
});

// ==================== ФОРМА ЗАЯВКИ НА РЕМОНТ ====================
$('#requestForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!isLoggedIn) {
        openModal('authModal');
        showToast('Необходимо авторизоваться', 'error');
        return;
    }
    const btn = e.target.querySelector('button');
    btn.disabled = true;
    btn.textContent = 'Отправка...';
    try {
        let photo = null;
        const fileInput = $('#requestPhoto');
        if (fileInput && fileInput.files[0]) {
            const fd = new FormData();
            fd.append('file', fileInput.files[0]);
            fd.append('type', 'request');
            const upRes = await fetch(API_BASE + 'upload.php', {
                method: 'POST',
                body: fd
            }).then(r => r.json());
            if (upRes.success) photo = upRes.path;
        }
        const data = {
            service_type: $('#requestServiceType').value,
            vehicle_model: $('#requestVehicleModel').value,
            problem_description: $('#requestProblemDescription').value,
            preferred_date: $('#requestPreferredDate').value,
            phone: $('#requestPhone').value,
            photo: photo,
        };
        const res = await API.createRequest(data);
        if (res.success) {
            closeModal('requestModal');
            showToast('Заявка создана', 'success');
            e.target.reset();
            const preview = $('#requestPhotoPreview');
            if (preview) preview.innerHTML = '';
            const profileSection = $('.profile-section');
            if (profileSection && profileSection.style.display !== 'none') {
                loadRequests();
            }
        } else {
            showToast(res.message || 'Ошибка', 'error');
        }
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Отправить';
    }
});

// ==================== ФОРМА ОТЗЫВА ====================
$('#reviewForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!isLoggedIn) {
        openModal('authModal');
        showToast('Необходимо авторизоваться', 'error');
        return;
    }
    const btn = e.target.querySelector('button');
    btn.disabled = true;
    try {
        const ratingEl = document.querySelector('input[name="reviewRating"]:checked');
        if (!ratingEl) {
            showToast('Выберите оценку', 'error');
            btn.disabled = false;
            return;
        }
        const res = await API.createReview({
            rating: parseInt(ratingEl.value),
            comment: $('#reviewCommentNew').value
        });
        if (res.success) {
            closeModal('reviewModal');
            showToast('Отзыв добавлен', 'success');
            e.target.reset();
            loadReviews();
        } else {
            showToast(res.message || 'Ошибка', 'error');
        }
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
    }
});

// ==================== ФОРМА ПРОФИЛЯ ====================
$('#profileForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    btn.disabled = true;
    btn.textContent = 'Сохранение...';
    try {
        const data = {
            name: $('#profileName').value,
            phone: $('#profilePhone').value
        };
        const currentPass = $('#currentPassword').value;
        const newPass = $('#newPassword').value;
        if (currentPass || newPass) {
            data.current_password = currentPass;
            data.new_password = newPass;
        }
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
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Сохранить';
    }
});

// ==================== ФОРМА ОТЗЫВА В ПРОФИЛЕ ====================
$('#myReviewForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
        const ratingEl = document.querySelector('#myReviewForm input[name="rating"]:checked');
        if (!ratingEl) {
            showToast('Выберите оценку', 'error');
            return;
        }
        const myReviewRes = await API.getMyReview();
        const hasReview = myReviewRes.has_review;
        const res = hasReview
            ? await API.updateReview({ rating: parseInt(ratingEl.value), comment: $('#reviewComment').value })
            : await API.createReview({ rating: parseInt(ratingEl.value), comment: $('#reviewComment').value });
        if (res.success) {
            showToast('Отзыв сохранен', 'success');
            loadMyReview();
        } else {
            showToast(res.message || 'Ошибка', 'error');
        }
    } catch (err) {
        showToast(err.message, 'error');
    }
});

// ==================== ФОРМА ВАКАНСИИ ====================
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
        console.error('Ошибка вакансии:', err);
        showToast(err.message || 'Ошибка соединения с сервером', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    }
});

// ==================== ЗАГРУЗКА ДАННЫХ ПРОФИЛЯ ====================
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
    } catch (e) {
        showToast('Ошибка загрузки профиля', 'error');
    }
}

async function loadRequests() {
    const list = $('#requestsList');
    if (!list) return;
    try {
        const res = await API.getMyRequests();
        if (res.success && res.requests && res.requests.length > 0) {
            list.innerHTML = res.requests.map(r => {
                let extraInfo = '';
                if (r.repair_date) {
                    extraInfo += `<div class="request-date"><i class="fas fa-tools"></i> Ремонт: ${formatDate(r.repair_date)} ${r.repair_time || ''}</div>`;
                }
                if (r.admin_comment) {
                    extraInfo += `<div class="request-date"><i class="fas fa-comment"></i> ${esc(r.admin_comment)}</div>`;
                }
                return `
                <div class="request-card">
                    <div class="request-info">
                        <div class="request-id">Заявка #${r.id}</div>
                        <div class="request-service">${esc(r.service_type)}</div>
                        <div class="request-vehicle">${esc(r.vehicle_model)}</div>
                        <div class="request-date"><i class="far fa-calendar"></i> ${formatDate(r.preferred_date)}</div>
                        ${extraInfo}
                    </div>
                    <div class="request-status status-${r.status}">${statusText(r.status)}</div>
                    ${r.status === 'new' ? `<button class="btn btn-secondary btn-small" onclick="cancelRequest(${r.id})">Отменить</button>` : ''}
                </div>`;
            }).join('');
        } else {
            list.innerHTML = '<div class="no-reviews">У вас пока нет заявок</div>';
        }
    } catch (e) {
        list.innerHTML = '<div class="no-reviews">Ошибка загрузки</div>';
    }
}

async function loadReviews() {
    const grid = $('#reviewsGrid');
    if (!grid) return;
    try {
        const res = await API.getReviews();
        if (res.success) {
            $('#averageRatingText').textContent = res.average_rating || '0.0';
            $('#totalReviews').textContent = res.total_reviews || 0;
            const fullStars = Math.floor(res.average_rating || 0);
            const starsHtml = '★'.repeat(fullStars) + '☆'.repeat(5 - fullStars);
            $('#averageStars').innerHTML = `<span style="color:#ffc107">${starsHtml}</span>`;
            if (res.reviews && res.reviews.length > 0) {
                grid.innerHTML = res.reviews.map(r => `
                    <div class="review-card">
                        <div class="review-header">
                            <span class="review-author">${esc(r.user_name)}</span>
                            <span class="review-date">${r.date_formatted}</span>
                        </div>
                        <div class="review-rating">${'★'.repeat(r.rating)}${'☆'.repeat(5 - r.rating)}</div>
                        <div class="review-text">${esc(r.comment)}</div>
                    </div>
                `).join('');
            } else {
                grid.innerHTML = '<div class="no-reviews">Пока нет отзывов. Будьте первым!</div>';
            }
        }
    } catch (e) {
        grid.innerHTML = '<div class="no-reviews">Ошибка загрузки отзывов</div>';
    }
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
            $('#saveReviewBtn').innerHTML = '<i class="fas fa-save"></i> Сохранить отзыв';
        }
    } catch (e) {
        console.error('Ошибка загрузки отзыва:', e);
    }
}

async function cancelRequest(id) {
    if (!confirm('Вы уверены, что хотите отменить заявку?')) return;
    try {
        const res = await API.cancelRequest(id);
        if (res.success) {
            showToast('Заявка отменена', 'success');
            loadRequests();
        }
    } catch (e) {
        showToast(e.message, 'error');
    }
}

// ==================== ВСПОМОГАТЕЛЬНЫЕ ====================
function esc(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
}

function statusText(s) {
    const statuses = {
        'new': 'Новая',
        'in_progress': 'В работе',
        'completed': 'Выполнена',
        'cancelled': 'Отменена'
    };
    return statuses[s] || s;
}

// ==================== ПРЕВЬЮ ФОТО ====================
$('#requestPhoto')?.addEventListener('change', function() {
    const file = this.files[0];
    const preview = $('#requestPhotoPreview');
    if (!file || !file.type.startsWith('image/')) return;
    if (file.size > 5 * 1024 * 1024) {
        showToast('Размер файла не должен превышать 5 МБ', 'error');
        return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
        preview.innerHTML = `
            <div class="file-info">
                <i class="fas fa-image"></i>
                <span>${file.name}</span>
                <small>(${(file.size / 1024).toFixed(1)} КБ)</small>
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

// ==================== АУТЕНТИФИКАЦИЯ ====================
function openAuthModal(mode = 'login') {
    openModal('authModal');
    setAuthMode(mode);
}

function setAuthMode(mode) {
    const toggle = $('#modalAuthToggle');
    if (toggle) toggle.dataset.active = mode;
    $$('.auth-toggle-option').forEach(o => {
        o.classList.toggle('active', o.dataset.action === mode);
    });
    const title = $('#authModalTitle');
    if (title) title.textContent = mode === 'login' ? 'Вход' : 'Регистрация';
    const loginForm = $('#loginForm');
    const registerForm = $('#registerForm');
    if (loginForm) loginForm.classList.toggle('active', mode === 'login');
    if (registerForm) registerForm.classList.toggle('active', mode === 'register');
}

$('#modalAuthToggle')?.addEventListener('click', (e) => {
    const opt = e.target.closest('.auth-toggle-option');
    if (opt) setAuthMode(opt.dataset.action);
});

$('#loginBtnNav')?.addEventListener('click', () => openAuthModal('login'));
$('#registerBtnNav')?.addEventListener('click', () => openAuthModal('register'));
$('#authActionBtnMobile')?.addEventListener('click', () => openAuthModal('login'));

$('#userName')?.addEventListener('click', () => {
    if (isLoggedIn) {
        showSection('profile');
    } else {
        openAuthModal('login');
    }
});

// ==================== ВЫХОД ====================
async function handleLogout() {
    try {
        await API.logout();
        isLoggedIn = false;
        currentUser = null;
        updateAuthUI();
        showSection('home');
        showToast('Выход выполнен', 'success');
    } catch (e) {
        showToast(e.message, 'error');
    }
}

$$('.btn-logout-mobile').forEach(b => b?.addEventListener('click', handleLogout));
$('#logoutBtnProfile')?.addEventListener('click', handleLogout);

// ==================== КНОПКИ ====================
$('#openRequestModal')?.addEventListener('click', () => {
    if (isLoggedIn) {
        openModal('requestModal');
    } else {
        openAuthModal('login');
    }
});

$('#openRequestModalProfile')?.addEventListener('click', () => openModal('requestModal'));

$('#openReviewModal')?.addEventListener('click', () => {
    if (isLoggedIn) {
        openModal('reviewModal');
    } else {
        openAuthModal('login');
    }
});

$$('.btn-request').forEach(b => {
    b.addEventListener('click', () => {
        if (isLoggedIn) {
            const sel = $('#requestServiceType');
            if (sel && b.dataset.service) {
                sel.value = b.dataset.service;
            }
            openModal('requestModal');
        } else {
            openAuthModal('login');
            showToast('Необходимо авторизоваться', 'error');
        }
    });
});

// ==================== НАВИГАЦИЯ ПО ССЫЛКАМ ====================
$$('.nav-links a').forEach(a => {
    a.addEventListener('click', function(e) {
        const href = this.getAttribute('href');

        // Пропускаем внешние ссылки
        if (href && (href.startsWith('admin/') || href.startsWith('http') || href === 'news.html')) return;

        e.preventDefault();

        if (href === '#' || !href) showSection('home');
        else if (href === '#services') showSection('services');
        else if (href === '#profile') isLoggedIn ? showSection('profile') : openAuthModal('login');
        else if (href === '#reviews') showSection('reviews');
        else if (href === '#contacts') showSection('contacts');
        else if (href === '#vacancies') showSection('vacancies');
    });
});

// ==================== ТАБЫ ПРОФИЛЯ ====================
$$('.profile-tab').forEach(t => {
    t.addEventListener('click', () => {
        $$('.profile-tab').forEach(t2 => t2.classList.remove('active'));
        t.classList.add('active');
        $$('.profile-content').forEach(c => c.classList.remove('active'));
        const tabId = t.dataset.tab;
        const content = $(`#${tabId}Tab`);
        if (content) content.classList.add('active');
    });
});

// ==================== МИНИМАЛЬНАЯ ДАТА ====================
function setMinDates() {
    const today = new Date().toISOString().split('T')[0];
    const d = $('#requestPreferredDate');
    if (d) d.min = today;
}

// ==================== НОВОСТИ ====================
async function loadNews() {
    try {
        const res = await API.request('news.php?action=all&limit=3');
        if (res.success && res.news && res.news.length > 0) {
            const grid = $('#newsGrid');
            if (!grid) return;
            grid.innerHTML = res.news.map(n => {
                let imgHtml = '';
                if (n.image) {
                    imgHtml = `<div class="news-card-img-link" onclick="openImageModal('${n.image}')" title="Нажмите для просмотра">
                        <img src="${n.image}" alt="${esc(n.title)}">
                    </div>`;
                } else {
                    imgHtml = '<div class="news-card-img" style="background:linear-gradient(135deg,#2d3f63,#1a2b4c);display:flex;align-items:center;justify-content:center;font-size:40px;color:rgba(255,255,255,0.3)"><i class="fas fa-newspaper"></i></div>';
                }
                return `
                <div class="news-card">
                    ${imgHtml}
                    <div class="news-card-body">
                        <h3>${esc(n.title)}</h3>
                        <div class="news-text">${esc(n.content)}</div>
                        <span class="read-more" data-id="${n.id}" style="color:#ff6b35;font-weight:600;font-size:14px;cursor:pointer;text-decoration:underline">Читать далее</span>
                        <div class="news-card-date"><i class="far fa-clock"></i> ${formatDate(n.created_at)}</div>
                    </div>
                </div>`;
            }).join('');

            // Вешаем обработчики на "Читать далее"
            $$('.read-more').forEach(span => {
                span.addEventListener('click', function() {
                    const id = this.dataset.id;
                    loadSingleNews(id);
                });
            });
        } else {
            const grid = $('#newsGrid');
            if (grid) grid.innerHTML = '<div class="no-reviews">Пока нет новостей</div>';
        }
    } catch (e) {
        console.error('Ошибка загрузки новостей:', e);
    }
}

async function loadSingleNews(id) {
    try {
        const res = await API.request(`news.php?action=get&id=${id}`);
        if (res.success && res.news) {
            const n = res.news;
            openNewsModal(n.title, n.content, n.image, formatDate(n.created_at));
        }
    } catch (e) {
        showToast('Ошибка загрузки новости', 'error');
    }
}

function openNewsModal(title, content, image, date) {
    const modal = document.getElementById('newsModal');
    const bodyEl = document.getElementById('newsModalBody');
    if (!modal || !bodyEl) return;

    let bodyHtml = '';
    if (image) {
        bodyHtml += `<div style="cursor:pointer;text-align:center;margin-bottom:20px" onclick="openImageModal('${image}')">
            <img src="${image}" alt="${title}" style="max-width:100%;max-height:300px;border-radius:8px">
        </div>`;
    }
    bodyHtml += `<div style="color:#999;font-size:13px;margin-bottom:15px"><i class="far fa-clock" style="color:#ff6b35;margin-right:5px"></i> ${date}</div>`;
    bodyHtml += `<div style="color:#333;font-size:15px;line-height:1.7;white-space:pre-wrap;word-wrap:break-word">${content}</div>`;

    bodyEl.innerHTML = bodyHtml;
    modal.style.display = 'flex';
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeNewsModal() {
    var modal = document.getElementById('newsModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function openImageModal(src) {
    var modal = document.getElementById('imageModal');
    var img = document.getElementById('imageModalImg');
    if (modal && img) {
        img.src = src;
        modal.style.display = 'flex';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeImageModal() {
    var modal = document.getElementById('imageModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}
// ==================== ИНИЦИАЛИЗАЦИЯ ====================
document.addEventListener('DOMContentLoaded', async () => {
    setMinDates();
    loadNews();
    await loadEmployees();
    await loadVacancies();

        // Закрытие модалки новости по клику на фон
    const newsModalBg = document.getElementById('newsModal');
    if (newsModalBg) {
        newsModalBg.addEventListener('click', function(e) {
            if (e.target === this) closeNewsModal();
        });
    }

    try {
        const auth = await API.checkAuth();
        if (auth.loggedIn && auth.user) {
            isLoggedIn = true;
            currentUser = auth.user;
        }
    } catch (e) {
        console.error('Ошибка проверки авторизации:', e);
    }

    updateAuthUI();
    loadReviews();

    const vac = $('#vacancies');
    if (vac) vac.style.display = 'none';
});

window.cancelRequest = cancelRequest;
window.removePhoto = removePhoto;
window.openImageModal = openImageModal;
window.closeImageModal = closeImageModal;
window.openNewsModal = openNewsModal;
window.closeNewsModal = closeNewsModal;