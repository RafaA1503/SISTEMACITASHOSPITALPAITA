import './bootstrap';
import { Passkeys } from '@laravel/passkeys';

const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
const normalizeSearch = value => String(value ?? '').toLocaleLowerCase('es').normalize('NFD').replace(/[̀-ͯ]/g, '');

// Deslizar hacia abajo cierra una hoja/panel móvil (bottom sheet), como el
// gesto nativo de Android — solo si el toque empieza cerca del borde superior,
// para no interferir con el scroll normal del contenido de la hoja.
function enableSwipeToDismiss(sheet, dismiss) {
    if (!sheet) return;
    let startY = 0, tracking = false;
    sheet.addEventListener('touchstart', event => {
        if (window.innerWidth > 760 || event.touches.length !== 1) return;
        const rect = sheet.getBoundingClientRect();
        if (event.touches[0].clientY - rect.top > 60) return;
        startY = event.touches[0].clientY;
        tracking = true;
    }, { passive: true });
    sheet.addEventListener('touchend', event => {
        if (!tracking) return;
        tracking = false;
        const dy = (event.changedTouches[0]?.clientY ?? startY) - startY;
        if (dy > 70) dismiss();
    }, { passive: true });
}

function enhanceSelect(select) {
    if (select.dataset.comboEnhanced || select.multiple || select.options.length < 8) return;
    select.dataset.comboEnhanced = '1';

    const wrapper = document.createElement('div');
    wrapper.className = 'combo-select';
    select.insertAdjacentElement('beforebegin', wrapper);
    wrapper.appendChild(select);
    select.classList.add('combo-native-select');
    select.tabIndex = -1;

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'combo-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.innerHTML = '<span></span><i>⌄</i>';
    wrapper.appendChild(trigger);

    const panel = document.createElement('div');
    panel.className = 'combo-panel';
    panel.hidden = true;
    panel.innerHTML = '<input type="text" class="combo-search" placeholder="Buscar..." autocomplete="off"><div class="combo-options" role="listbox"></div><p class="combo-empty" hidden>Sin resultados</p>';
    wrapper.appendChild(panel);

    const searchInput = panel.querySelector('.combo-search');
    const optionsContainer = panel.querySelector('.combo-options');
    const emptyMsg = panel.querySelector('.combo-empty');
    const label = trigger.querySelector('span');

    let renderedItems = [];
    let highlightedIndex = -1;

    const updateLabel = () => {
        const current = select.options[select.selectedIndex];
        const hasValue = current && current.value !== '';
        label.textContent = current ? current.textContent.trim() : 'Seleccionar';
        label.classList.toggle('placeholder', !hasValue);
    };

    const buildList = (term = '') => {
        optionsContainer.innerHTML = '';
        renderedItems = [];
        highlightedIndex = -1;
        const normalizedTerm = normalizeSearch(term.trim());
        let lastGroup = null;
        [...select.options].forEach((option, index) => {
            if (option.hidden) return;
            const text = option.textContent.trim();
            if (normalizedTerm && !normalizeSearch(text).includes(normalizedTerm)) return;
            const groupLabel = option.closest('optgroup')?.label || null;
            if (groupLabel !== lastGroup) {
                lastGroup = groupLabel;
                if (groupLabel) {
                    const groupEl = document.createElement('div');
                    groupEl.className = 'combo-group-label';
                    groupEl.textContent = groupLabel;
                    optionsContainer.appendChild(groupEl);
                }
            }
            const item = document.createElement('div');
            item.className = 'combo-option' + (index === select.selectedIndex ? ' selected' : '');
            item.setAttribute('role', 'option');
            item.textContent = text || ' ';
            item.addEventListener('click', () => selectIndex(index));
            optionsContainer.appendChild(item);
            renderedItems.push({ el: item, index });
        });
        emptyMsg.hidden = renderedItems.length > 0;
    };

    const selectIndex = index => {
        select.selectedIndex = index;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        closePanel();
        trigger.focus();
    };

    const onDocumentClick = event => { if (!wrapper.contains(event.target)) closePanel(); };
    const openPanel = () => {
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        searchInput.value = '';
        buildList();
        searchInput.focus();
        document.addEventListener('click', onDocumentClick, true);
    };
    const closePanel = () => {
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        document.removeEventListener('click', onDocumentClick, true);
    };
    const moveHighlight = delta => {
        if (!renderedItems.length) return;
        renderedItems[highlightedIndex]?.el.classList.remove('active');
        highlightedIndex = (highlightedIndex + delta + renderedItems.length) % renderedItems.length;
        const item = renderedItems[highlightedIndex];
        item.el.classList.add('active');
        item.el.scrollIntoView({ block: 'nearest' });
    };

    trigger.addEventListener('click', () => (panel.hidden ? openPanel() : closePanel()));
    searchInput.addEventListener('input', () => buildList(searchInput.value));
    searchInput.addEventListener('keydown', event => {
        if (event.key === 'Escape') { closePanel(); trigger.focus(); }
        else if (event.key === 'ArrowDown') { event.preventDefault(); moveHighlight(1); }
        else if (event.key === 'ArrowUp') { event.preventDefault(); moveHighlight(-1); }
        else if (event.key === 'Enter') {
            event.preventDefault();
            const target = highlightedIndex >= 0 ? renderedItems[highlightedIndex] : (renderedItems.length === 1 ? renderedItems[0] : null);
            if (target) selectIndex(target.index);
        }
    });

    select.addEventListener('change', updateLabel);
    updateLabel();
}
document.querySelectorAll('select').forEach(enhanceSelect);

const serviceCatalogSearch = document.getElementById('serviceCatalogSearch');
if (serviceCatalogSearch) {
    const serviceCards = [...document.querySelectorAll('#serviceCatalog article')];
    const serviceCatalogEmpty = document.getElementById('serviceCatalogEmpty');
    serviceCatalogSearch.addEventListener('input', () => {
        const term = serviceCatalogSearch.value.trim().toLocaleLowerCase('es');
        let visible = 0;
        serviceCards.forEach(card => {
            const show = !term || card.dataset.serviceSearch.includes(term);
            card.hidden = !show;
            if (show) visible++;
        });
        if (serviceCatalogEmpty) serviceCatalogEmpty.hidden = visible !== 0;
    });
}

// --- Modal de confirmación propio, en vez del confirm() nativo del navegador ---
const confirmModal = document.createElement('div');
confirmModal.className = 'modal-backdrop confirm-modal-backdrop';
confirmModal.hidden = true;
confirmModal.innerHTML = `<div class="modal confirm-modal" role="alertdialog" aria-modal="true">
    <p class="confirm-modal-message"></p>
    <div class="confirm-modal-actions">
        <button type="button" class="confirm-modal-cancel">Cancelar</button>
        <button type="button" class="confirm-modal-accept">Confirmar</button>
    </div>
</div>`;
document.body.appendChild(confirmModal);
const confirmModalMessage = confirmModal.querySelector('.confirm-modal-message');
const confirmModalAccept = confirmModal.querySelector('.confirm-modal-accept');
const confirmModalCancel = confirmModal.querySelector('.confirm-modal-cancel');
function showConfirmModal(message) {
    return new Promise(resolve => {
        confirmModalMessage.textContent = message;
        confirmModal.hidden = false;
        const finish = accepted => {
            confirmModal.hidden = true;
            confirmModalAccept.removeEventListener('click', onAccept);
            confirmModalCancel.removeEventListener('click', onCancel);
            confirmModal.removeEventListener('click', onBackdrop);
            document.removeEventListener('keydown', onKeydown);
            resolve(accepted);
        };
        const onAccept = () => finish(true);
        const onCancel = () => finish(false);
        const onBackdrop = event => { if (event.target === confirmModal) finish(false); };
        const onKeydown = event => { if (event.key === 'Escape') finish(false); };
        confirmModalAccept.addEventListener('click', onAccept);
        confirmModalCancel.addEventListener('click', onCancel);
        confirmModal.addEventListener('click', onBackdrop);
        document.addEventListener('keydown', onKeydown);
        confirmModalAccept.focus();
    });
}

document.querySelectorAll('.module-permissions label').forEach(label => {
    if (label.textContent.includes('Agenda por servicio')) label.lastChild.textContent = ' Atención profesional';
});

const pageLoader = document.createElement('div');
pageLoader.id = 'pageLoader';
pageLoader.className = 'page-loader visible';
pageLoader.setAttribute('role', 'status');
pageLoader.setAttribute('aria-live', 'polite');
pageLoader.innerHTML = `<div class="loader-card"><div class="loader-logo"><img src="/logo-hospital-la-merced.png" alt=""></div><strong>Hospital Nuestra Señora de las Mercedes</strong><span>Cargando sistema...</span><i></i></div>`;
document.body.appendChild(pageLoader);
// Antes usaba doble requestAnimationFrame para que la transición se viera
// suave, pero rAF no se garantiza si la pestaña no está pintando activamente
// (recién navegada, en segundo plano) — y si nunca se dispara, el loader se
// queda visible para siempre bloqueando todos los clics de la página.
let loaderSafetyTimer = null;
const hideLoader = () => {
    if (loaderSafetyTimer) window.clearTimeout(loaderSafetyTimer);
    loaderSafetyTimer = null;
    pageLoader.classList.remove('visible');
};
const showLoader = (message = 'Cargando sistema...') => {
    pageLoader.querySelector('span').textContent = message;
    pageLoader.classList.add('visible');
    // Si el servidor rechaza una navegación o una descarga no cambia de página,
    // el usuario nunca debe quedar bloqueado indefinidamente por el preloader.
    if (loaderSafetyTimer) window.clearTimeout(loaderSafetyTimer);
    loaderSafetyTimer = window.setTimeout(hideLoader, 12_000);
};
window.addEventListener('load', hideLoader);
window.addEventListener('pageshow', event => {
    hideLoader();
    // Algunos navegadores restauran visualmente una página privada desde su
    // back-forward cache. Recargar obliga a Laravel a validar la sesión actual.
    if (event.persisted) window.location.reload();
});
setTimeout(hideLoader, 900);
document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || link.target === '_blank' || link.hasAttribute('download')) return;
    const target = new URL(link.href, window.location.href);
    // Mismo texto que el loader inicial de la página destino: al ser una
    // navegación normal (no SPA), cada módulo carga como documento nuevo con
    // su propio loader "desde cero". Si el mensaje cambiara aquí, se vería
    // como dos preloaders distintos en vez de una sola transición continua.
    if (target.origin === window.location.origin && target.href !== window.location.href && !target.hash) showLoader();
});
document.addEventListener('submit', event => {
    if (event.defaultPrevented || !event.target.checkValidity()) return;
    if (event.target.matches('.ajax-form')) return;
    showLoader(event.target.closest('.login-card') ? 'Iniciando sesión segura...' : 'Guardando cambios...');
});

const savedTheme = localStorage.getItem('hospital-theme');
const preferredTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
const applyTheme = theme => {
    document.documentElement.dataset.theme = theme;
    document.documentElement.style.colorScheme = theme;
    localStorage.setItem('hospital-theme', theme);
    document.cookie = `hospital_theme=${theme};path=/;max-age=31536000;samesite=lax`;
    const button = document.getElementById('themeToggle');
    if (button) {
        button.innerHTML = theme === 'dark' ? '<span>☀</span><b>Modo claro</b>' : '<span>☾</span><b>Modo oscuro</b>';
        button.setAttribute('aria-label', theme === 'dark' ? 'Activar modo claro' : 'Activar modo oscuro');
    }
};
applyTheme(savedTheme || preferredTheme);
const themeToggle = document.getElementById('themeToggle') || document.createElement('button');
if (!themeToggle.isConnected) {
    themeToggle.type = 'button';
    themeToggle.id = 'themeToggle';
    themeToggle.className = 'theme-toggle';
    document.body.appendChild(themeToggle);
}
applyTheme(document.documentElement.dataset.theme);
themeToggle.addEventListener('click', () => applyTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'));

// Respaldo del interruptor: aplica el tema directamente sobre <html> y evita
// que un manejador antiguo o una actualización del DOM anule el cambio.
const forceTheme = requestedTheme => {
    const theme = requestedTheme === 'dark' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', theme);
    document.documentElement.style.colorScheme = theme;
    try { localStorage.setItem('hospital-theme', theme); } catch { /* almacenamiento no disponible */ }
    document.cookie = `hospital_theme=${theme};path=/;max-age=31536000;samesite=lax`;
    const button = document.getElementById('themeToggle');
    if (button) {
        const dark = theme === 'dark';
        button.innerHTML = dark ? '<span>☀</span><b>Modo claro</b>' : '<span>☾</span><b>Modo oscuro</b>';
        button.setAttribute('aria-pressed', String(dark));
        button.setAttribute('aria-label', dark ? 'Activar modo claro' : 'Activar modo oscuro');
    }
};
document.addEventListener('click', event => {
    if (!event.target.closest('#themeToggle')) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    forceTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
}, true);

document.getElementById('togglePassword')?.addEventListener('click', event => {
    const input = document.getElementById('loginPassword');
    const visible = input.type === 'text';
    input.type = visible ? 'password' : 'text';
    event.currentTarget.textContent = visible ? 'Mostrar' : 'Ocultar';
});

// Carrusel institucional de la pantalla de acceso. Las fotos se cargan desde
// archivos locales para conservar calidad y no depender de servicios externos.
document.querySelectorAll('[data-login-carousel]').forEach(carousel => {
    const slides = [...carousel.querySelectorAll('.login-carousel-slide')];
    const dots = [...carousel.querySelectorAll('[data-carousel-dot]')];
    if (slides.length < 2) return;
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    let activeIndex = 0;
    let timer = null;
    const showSlide = index => {
        activeIndex = (index + slides.length) % slides.length;
        slides.forEach((slide, position) => slide.classList.toggle('is-active', position === activeIndex));
        dots.forEach((dot, position) => {
            const active = position === activeIndex;
            dot.classList.toggle('is-active', active);
            dot.setAttribute('aria-selected', String(active));
        });
    };
    const stop = () => { if (timer) window.clearInterval(timer); timer = null; };
    const start = () => {
        stop();
        if (!reducedMotion) timer = window.setInterval(() => showSlide(activeIndex + 1), 6000);
    };
    carousel.querySelector('[data-carousel-prev]')?.addEventListener('click', () => { showSlide(activeIndex - 1); start(); });
    carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => { showSlide(activeIndex + 1); start(); });
    dots.forEach((dot, index) => dot.addEventListener('click', () => { showSlide(index); start(); }));
    carousel.addEventListener('mouseenter', stop);
    carousel.addEventListener('mouseleave', start);
    document.addEventListener('visibilitychange', () => document.hidden ? stop() : start());
    start();
});

const photoInput = document.querySelector('input[name="photo"]');
if (photoInput) {
    photoInput.accept = 'image/jpeg,image/png,image/webp';
    const currentPhoto = document.querySelector('.profile-photo img');
    const uploader = document.createElement('div');
    uploader.className = 'photo-uploader';
    uploader.innerHTML = `<div class="photo-preview">${currentPhoto ? `<img src="${currentPhoto.src}" alt="Vista previa">` : '<span>＋</span>'}</div><div><strong>${currentPhoto ? 'Cambiar fotografía' : 'Subir fotografía'}</strong><small>Haz clic o arrastra una imagen JPG, PNG o WebP · Máximo 10 MB</small><em id="photoFileName">Ningún archivo seleccionado</em></div>`;
    photoInput.parentNode.insertBefore(uploader, photoInput);
    uploader.appendChild(photoInput);
    const preview = uploader.querySelector('.photo-preview');
    const fileName = uploader.querySelector('#photoFileName');
    const showPhoto = file => {
        if (!file) return;
        if (!file.type.startsWith('image/')) { fileName.textContent = 'Selecciona un archivo de imagen válido.'; uploader.classList.add('invalid'); return; }
        if (file.size > 10 * 1024 * 1024) { fileName.textContent = 'La imagen supera el máximo de 10 MB.'; uploader.classList.add('invalid'); return; }
        uploader.classList.remove('invalid');
        preview.innerHTML = `<img src="${URL.createObjectURL(file)}" alt="Vista previa de la nueva fotografía">`;
        fileName.textContent = file.name;
        const headerPreview = document.querySelector('.profile-photo');
        if (headerPreview) headerPreview.innerHTML = `<img src="${preview.querySelector('img').src}" alt="Nueva fotografía">`;
    };
    photoInput.addEventListener('change', () => showPhoto(photoInput.files[0]));
    ['dragenter','dragover'].forEach(type => uploader.addEventListener(type, event => { event.preventDefault(); uploader.classList.add('dragging'); }));
    ['dragleave','drop'].forEach(type => uploader.addEventListener(type, event => { event.preventDefault(); uploader.classList.remove('dragging'); }));
    uploader.addEventListener('drop', event => { if (event.dataTransfer.files[0]) { photoInput.files = event.dataTransfer.files; showPhoto(photoInput.files[0]); } });
}

const rows = window.appointments || [];
const body = document.getElementById('appointmentsBody');
const search = document.getElementById('searchInput');
const examFilter = document.getElementById('examFilter');
const statusFilter = document.getElementById('statusFilter');
const modal = document.getElementById('detailModal');
let selected = null;

const statusClass = status => status.toLowerCase().replaceAll(' ', '-').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
function render(){
    const term = search.value.toLowerCase().trim();
    const filtered = rows.filter(row => {
        const haystack = `${row.name} ${row.dni} ${row.code} ${row.exam}`.toLowerCase();
        return haystack.includes(term) && (!examFilter.value || row.exam === examFilter.value) && (!statusFilter.value || row.status === statusFilter.value);
    });
    body.innerHTML = filtered.map((row) => `<tr data-code="${row.code}" tabindex="0"><td><span class="time">${row.time}</span></td><td><div class="person"><span class="avatar">${row.initials}</span><div><strong>${row.name}</strong><small>DNI ${row.dni}</small></div></div></td><td><div class="exam"><strong>${row.exam}</strong><small>${row.detail}</small></div></td><td><span class="dest">${row.dest}</span></td><td><span class="status ${statusClass(row.status)}">${row.status}</span></td><td><button class="more" aria-label="Ver detalle">›</button></td></tr>`).join('');
    document.getElementById('resultCount').textContent = filtered.length;
    document.getElementById('emptyState').hidden = filtered.length > 0;
    body.querySelectorAll('tr').forEach(tr => {
        const open = () => openDetail(rows.find(r => r.code === tr.dataset.code));
        tr.addEventListener('click', open);
        tr.addEventListener('keydown', e => { if(e.key === 'Enter') open(); });
    });
}
function openDetail(row){
    selected = row;
    document.getElementById('modalInitials').textContent = row.initials;
    document.getElementById('modalPatient').textContent = row.name;
    document.getElementById('modalDni').textContent = `DNI ${row.dni}`;
    document.getElementById('modalTime').textContent = row.time;
    document.getElementById('modalCode').textContent = row.code;
    document.getElementById('modalExam').textContent = row.exam;
    document.getElementById('modalDest').textContent = row.dest;
    const confirm = document.getElementById('confirmEntry');
    confirm.textContent = row.status === 'Ingresó' ? 'Ingreso ya registrado' : 'Confirmar ingreso';
    confirm.disabled = row.status === 'Ingresó';
    modal.hidden = false;
}
if (body) {
[search, examFilter, statusFilter].forEach(el => el?.addEventListener('input', render));
document.getElementById('clearFilters')?.addEventListener('click', () => { search.value=''; examFilter.value=''; statusFilter.value=''; render(); });
document.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', () => modal.hidden = true));
modal?.addEventListener('click', e => { if(e.target === modal) modal.hidden = true; });
document.addEventListener('keydown', e => { if(e.key === 'Escape' && modal) modal.hidden = true; });
document.getElementById('confirmEntry')?.addEventListener('click', () => {
    if(!selected) return;
    selected.status = 'Ingresó'; modal.hidden = true; render();
    const toast = document.getElementById('toast'); toast.classList.add('show'); setTimeout(() => toast.classList.remove('show'), 3000);
});
document.getElementById('scanBtn')?.addEventListener('click', () => { search.focus(); search.placeholder = 'Ingresa o escanea el código de la cita...'; });
render();
}

document.getElementById('menuBtn')?.addEventListener('click', () => document.getElementById('sidebar')?.classList.toggle('open'));
const liveClock = document.getElementById('liveClock');

// Demostracion de visitas hospitalarias: permanece dentro de PorterÃ­a y no
// escribe datos hasta que se conecte al registro definitivo de hospitalizaciÃ³n.
const porterPanel = document.querySelector('.porter-search');
if (porterPanel) {
    const visitDemo = document.createElement('section');
    visitDemo.className = 'portal-panel visit-demo';
    visitDemo.innerHTML = `
        <div class="panel-title"><div><h2>Visitas hospitalarias</h2><p>Demostracion: registra al familiar que visita a un paciente hospitalizado.</p></div></div>
        <div class="visit-demo-body">
            <div class="visit-patient"><span>PA</span><div><strong>Paciente hospitalizado de ejemplo</strong><small>Hospitalizacion · Cama 12 · Medicina</small></div><b>Atencion activa</b></div>
            <label class="visit-patient-select">Paciente hospitalizado<select id="visitPatientSelect"><option value="maria">Maria Flores Gomez · Cama 12 · Medicina</option><option value="carlos">Carlos Ruiz Chunga · Cama 08 · Cirugia</option><option value="ana">Ana Torres Vilela · Cama 04 · Pediatria</option></select></label>
            <form id="visitDemoForm" class="visit-demo-form" novalidate>
                <label class="visit-name-field">Nombre del visitante<input name="name" required readonly aria-readonly="true" placeholder="Se completa al consultar el DNI"></label>
                <label class="visit-dni-field">DNI<div class="visit-dni-input"><input name="dni" required inputmode="numeric" pattern="[0-9]{8}" maxlength="8" autocomplete="off" placeholder="8 digitos"><button type="button" data-visit-scan aria-label="Escanear código de barras del DNI"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M8 9h8v6H8z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button></div><small data-visit-dni-status>Escribe o escanea los 8 dígitos.</small></label>
                <label class="visit-entry-time">Hora de entrada<input name="entry_time" type="time" required lang="en-US"></label>
                <label>Parentesco<select name="relationship" required><option value="">Seleccionar</option><option>Madre / padre</option><option>Hijo(a)</option><option>Hermano(a)</option><option>Conyuge</option><option>Otro familiar</option></select></label>
                <button class="primary-btn" type="submit">Registrar entrada</button>
            </form>
        </div>
        <div class="visit-demo-list"><div class="visit-demo-list-head"><strong>Visitas registradas ahora</strong><small>La salida se registra desde esta misma lista.</small></div><p class="portal-empty" id="visitDemoEmpty">Aun no hay visitantes registrados en esta demostracion.</p><div id="visitDemoRows"></div></div>
    `;
    const visitTitle = visitDemo.querySelector('.panel-title');
    const visitToggle = document.createElement('button');
    visitToggle.type = 'button';
    visitToggle.className = 'visit-minimize';
    visitToggle.dataset.visitDetailsToggle = '';
    visitToggle.setAttribute('aria-expanded', 'true');
    visitToggle.setAttribute('aria-label', 'Ocultar detalles de visitas hospitalarias');
    visitToggle.textContent = 'Ocultar detalles';
    visitTitle.appendChild(visitToggle);
    const visitContent = document.createElement('div');
    visitContent.className = 'visit-demo-content';
    while (visitTitle.nextElementSibling) visitContent.appendChild(visitTitle.nextElementSibling);
    visitDemo.appendChild(visitContent);
    const toggleVisitDetails = () => {
        const minimized = !visitDemo.classList.contains('is-collapsed');
        visitDemo.classList.toggle('is-collapsed', minimized);
        visitContent.hidden = minimized;
        visitToggle.textContent = minimized ? 'Mostrar detalles' : 'Ocultar detalles';
        visitToggle.setAttribute('aria-expanded', String(!minimized));
        visitToggle.setAttribute('aria-label', minimized ? 'Mostrar detalles de visitas hospitalarias' : 'Ocultar detalles de visitas hospitalarias');
    };
    visitToggle.addEventListener('click', event => {
        event.preventDefault();
        event.stopPropagation();
        toggleVisitDetails();
    });
    porterPanel.insertAdjacentElement('afterend', visitDemo);
    const visitForm = visitDemo.querySelector('#visitDemoForm');
    const visitDniInput = visitForm.elements.dni;
    const visitEntryTimeInput = visitForm.elements.entry_time;
    const currentTimeValue = () => new Date().toTimeString().slice(0, 5);
    visitEntryTimeInput.value = currentTimeValue();
    const visitRows = visitDemo.querySelector('#visitDemoRows');
    const visitEmpty = visitDemo.querySelector('#visitDemoEmpty');
    const visitList = visitDemo.querySelector('.visit-demo-list');
    const visitListHead = visitDemo.querySelector('.visit-demo-list-head');
    const visitListToggle = document.createElement('button');
    visitListToggle.type = 'button';
    visitListToggle.className = 'visit-list-toggle';
    visitListToggle.innerHTML = '⌃';
    visitListToggle.title = 'Minimizar visitas registradas';
    visitListToggle.setAttribute('aria-label', 'Minimizar visitas registradas');
    visitListToggle.setAttribute('aria-expanded', 'true');
    visitListHead.appendChild(visitListToggle);
    visitListToggle.addEventListener('click', () => {
        const minimized = !visitRows.hidden;
        visitRows.hidden = minimized;
        visitEmpty.hidden = minimized || visitRows.children.length !== 0;
        visitList.classList.toggle('is-minimized', minimized);
        visitListToggle.innerHTML = minimized ? '⌄' : '⌃';
        visitListToggle.title = minimized ? 'Maximizar visitas registradas' : 'Minimizar visitas registradas';
        visitListToggle.setAttribute('aria-label', visitListToggle.title);
        visitListToggle.setAttribute('aria-expanded', String(!minimized));
    });
    const visitPatientSelect = visitDemo.querySelector('#visitPatientSelect');
    const visitPatientCard = visitDemo.querySelector('.visit-patient');
    let hospitalPatients = {
        maria: { initials: 'MF', name: 'Maria Flores Gomez', detail: 'Hospitalizacion · Cama 12 · Medicina' },
        carlos: { initials: 'CR', name: 'Carlos Ruiz Chunga', detail: 'Hospitalizacion · Cama 08 · Cirugia' },
        ana: { initials: 'AT', name: 'Ana Torres Vilela', detail: 'Hospitalizacion · Cama 04 · Pediatria' },
    };
    const validVisitorDni = value => {
        const dni = String(value || '').replace(/[^0-9]/g, '');
        return /^[0-9]{8}$/.test(dni) && !/^(\d)\1{7}$/.test(dni);
    };
    const visitorNameInput = visitForm.elements.name;
    const visitorDniStatus = visitDemo.querySelector('[data-visit-dni-status]');
    let visitorLookupTimer;
    let resolvedVisitorDni = '';
    let manualVisitorEntry = true;
    const manualVisitorToggle = document.createElement('button');
    manualVisitorToggle.type = 'button';
    manualVisitorToggle.className = 'visit-manual-toggle';
    manualVisitorToggle.textContent = 'Usar consulta automática';
    visitorNameInput.insertAdjacentElement('afterend', manualVisitorToggle);
    visitorNameInput.readOnly = false;
    visitorNameInput.setAttribute('aria-readonly', 'false');
    visitorNameInput.placeholder = 'Nombres y apellidos del visitante';
    // Solo letras (con tildes/ñ), espacios, apóstrofos, puntos y guiones — nada de
    // números ni símbolos raros, para que no se cuele un DNI o un código por error.
    const VISITOR_NAME_INVALID_CHARS = /[^A-Za-zÁÉÍÓÚÑÜáéíóúñü\s'.-]/g;
    visitorNameInput.setAttribute('pattern', "[A-Za-zÁÉÍÓÚÑÜáéíóúñü\\s'.-]+");
    visitorNameInput.setAttribute('title', 'Solo letras y espacios, sin números ni símbolos.');
    visitorNameInput.addEventListener('input', () => {
        const sanitized = visitorNameInput.value.replace(VISITOR_NAME_INVALID_CHARS, '');
        if (sanitized !== visitorNameInput.value) visitorNameInput.value = sanitized;
    });
    const resetVisitorIdentity = () => {
        resolvedVisitorDni = '';
        if (!manualVisitorEntry) visitorNameInput.value = '';
        visitorDniStatus.className = '';
        visitorDniStatus.textContent = 'Escribe o escanea los 8 dígitos.';
    };
    manualVisitorToggle.addEventListener('click', () => {
        manualVisitorEntry = !manualVisitorEntry;
        visitorNameInput.readOnly = !manualVisitorEntry;
        visitorNameInput.setAttribute('aria-readonly', String(!manualVisitorEntry));
        visitorNameInput.placeholder = manualVisitorEntry ? 'Nombres y apellidos del visitante' : 'Se completa al consultar el DNI';
        manualVisitorToggle.textContent = manualVisitorEntry ? 'Consultar DNI automáticamente' : 'Ingresar manualmente';
        if (!manualVisitorEntry && validVisitorDni(visitDniInput.value)) lookupVisitorDni();
        else resetVisitorIdentity();
    });
    const lookupVisitorDni = async () => {
        const dni = visitDniInput.value.replace(/[^0-9]/g, '');
        // En ingreso manual, completar el DNI nunca debe consultar ni modificar
        // el nombre que el usuario ya escribio.
        if (manualVisitorEntry) return;
        if (!validVisitorDni(dni) || dni === resolvedVisitorDni) return;
        visitorDniStatus.className = 'is-loading';
        visitorDniStatus.textContent = 'Consultando identidad en RENIEC...';
        visitDniInput.disabled = true;
        try {
            const response = await fetch(`/api/consultar-dni/${dni}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'No se encontró el DNI.');
            if (visitDniInput.value !== dni) return;
            if (!manualVisitorEntry || !visitorNameInput.value.trim()) visitorNameInput.value = data.nombre_completo;
            resolvedVisitorDni = dni;
            visitorDniStatus.className = 'is-success';
            visitorDniStatus.textContent = 'Identidad verificada. Solo selecciona el parentesco.';
        } catch (error) {
            // Solo limpiamos un valor administrado por la consulta automatica.
            // Un nombre escrito manualmente siempre se conserva.
            if (!manualVisitorEntry) visitorNameInput.value = '';
            resolvedVisitorDni = '';
            visitorDniStatus.className = 'is-error';
            visitorDniStatus.textContent = error.message || 'No se pudo consultar el DNI.';
        } finally {
            visitDniInput.disabled = false;
        }
    };
    visitDniInput.addEventListener('input', () => {
        clearTimeout(visitorLookupTimer);
        const dni = visitDniInput.value.replace(/[^0-9]/g, '');
        visitDniInput.value = dni;
        const incomplete = dni.length < 8;
        const valid = validVisitorDni(dni);
        visitDniInput.setCustomValidity(incomplete || valid ? '' : 'Ingresa un DNI valido de 8 digitos.');
        visitDniInput.classList.toggle('is-invalid', !incomplete && !valid);
        if (dni !== resolvedVisitorDni) resetVisitorIdentity();
        if (valid && !manualVisitorEntry) visitorLookupTimer = setTimeout(lookupVisitorDni, 300);
    });
    visitDniInput.addEventListener('blur', () => {
        if (!manualVisitorEntry) lookupVisitorDni();
    });
    visitDemo.querySelector('[data-visit-scan]')?.addEventListener('click', async () => {
        if (!('BarcodeDetector' in window)) {
            showModuleToast('Escáner no compatible', 'Usa un lector USB/Bluetooth o escribe los 8 dígitos del DNI.');
            return;
        }
        let stream;
        let active = true;
        const overlay = document.createElement('div');
        overlay.className = 'scanner-overlay';
        overlay.innerHTML = '<section><button type="button" aria-label="Cerrar">×</button><h2>Escanear DNI del visitante</h2><p>Apunta la cámara al código de barras del documento.</p><div class="camera-frame"><video autoplay playsinline></video><i></i></div><small>La identidad se consultará automáticamente al detectar 8 dígitos.</small></section>';
        document.body.appendChild(overlay);
        document.documentElement.classList.add('scanner-open');
        document.body.classList.add('scanner-open');
        const video = overlay.querySelector('video');
        const close = () => { active = false; stream?.getTracks().forEach(track => track.stop()); overlay.remove(); document.documentElement.classList.remove('scanner-open'); document.body.classList.remove('scanner-open'); };
        overlay.querySelector('button').addEventListener('click', close);
        overlay.addEventListener('pointerdown', event => { if (event.target === overlay) close(); });
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } } });
            video.srcObject = stream;
            const detector = new BarcodeDetector({ formats: ['pdf417', 'code_128', 'code_39', 'qr_code'] });
            const detect = async () => {
                if (!active) return;
                try {
                    for (const code of await detector.detect(video)) {
                        const dni = code.rawValue.match(/\b\d{8}\b/)?.[0];
                        if (dni) { visitDniInput.value = dni; close(); visitDniInput.dispatchEvent(new Event('input', { bubbles: true })); return; }
                    }
                } catch { /* se sigue intentando mientras la cámara esté abierta */ }
                requestAnimationFrame(detect);
            };
            detect();
        } catch {
            close();
            showModuleToast('No se pudo abrir la cámara', 'Revisa el permiso de cámara o ingresa el DNI manualmente.');
        }
    });
    const updateVisitPatient = () => {
        const patient = hospitalPatients[visitPatientSelect.value];
        if (!patient) return;
        visitPatientCard.querySelector('span').textContent = patient.initials;
        visitPatientCard.querySelector('strong').textContent = patient.name;
        visitPatientCard.querySelector('small').textContent = patient.detail;
        loadHospitalVisits?.();
    };
    visitPatientSelect.addEventListener('change', updateVisitPatient);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    // Evita que un refresco de la lista (ej. al cambiar de paciente) reemplace
    // una fila cuya salida ya está en camino por una fresca y reactivada,
    // lo que permitiría un segundo clic real disparando una petición duplicada.
    const checkoutInFlight = new Set();
    const filterHospitalVisits = () => {
        const patient = hospitalPatients[visitPatientSelect.value];
        if (!patient) return;
        const visibleRows = [...visitRows.querySelectorAll('.visit-row')].filter(row => {
            const belongsToPatient = row.dataset.patientLabel?.includes(patient.name);
            row.hidden = !belongsToPatient;
            return belongsToPatient;
        });
        visitEmpty.hidden = visibleRows.length > 0;
        if (!visibleRows.length) visitEmpty.textContent = `No hay visitas registradas para ${patient.name}.`;
    };
    const renderHospitalVisit = visit => {
        const row = document.createElement('article');
        const hasExited = Boolean(visit.checked_out_at);
        const pending = checkoutInFlight.has(visit.id);
        row.className = `visit-row${hasExited ? ' visit-exited' : ''}`;
        row.dataset.logId = String(visit.id);
        row.dataset.visitorDni = visit.visitor_dni;
        row.dataset.patientLabel = visit.patient_label;
        if (pending) row.dataset.exited = '1'; // bloquea nuevos clics mientras la salida sigue en camino
        const entryTime = new Date(visit.registered_at).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', hour12: true });
        const exitTime = hasExited ? new Date(visit.checked_out_at).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', hour12: true }) : null;
        const buttonLabel = hasExited ? 'Salida registrada' : (pending ? 'Guardando...' : 'Registrar salida');
        row.innerHTML = `<div><strong>${escapeHtml(visit.visitor_name)}</strong><small>Visita a ${escapeHtml(visit.patient_label)} · DNI ${escapeHtml(visit.visitor_dni)} · ${escapeHtml(visit.relationship)}</small></div><time>${hasExited ? `Salida ${exitTime}` : `Entrada ${entryTime}`}</time><button type="button" ${hasExited || pending ? 'disabled' : ''}>${buttonLabel}</button>`;
        row.querySelector('time').innerHTML = `<span>Entrada ${entryTime}</span><span data-visit-exit>${exitTime ? `Salida ${exitTime}` : 'Salida pendiente'}</span>`;
        row.querySelector('button').addEventListener('click', async event => {
            const exitButton = event.currentTarget;
            if (row.dataset.exited || exitButton.disabled) return;
            exitButton.disabled = true;
            checkoutInFlight.add(visit.id);
            try {
                const response = await fetch(`/api/visitas-hospitalarias/${row.dataset.logId}/salida`, { method: 'PUT', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken } });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'No se pudo registrar la salida.');
                row.dataset.exited = '1'; row.classList.add('visit-exited');
                row.querySelector('[data-visit-exit]').textContent = `Salida ${new Date(payload.visit.checked_out_at).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', hour12: true })}`;
                exitButton.textContent = 'Salida registrada';
                showModuleToast('Salida registrada', 'La salida del visitante fue guardada en el sistema.');
            } catch (error) {
                // Si dos clics (propios o de otra persona en otra pantalla) llegan casi
                // a la vez, el primero sí registra la salida y el segundo choca con esta
                // validación — no es un fallo real, así que no debe verse como un error.
                if (/ya fue registrada/i.test(error.message || '')) {
                    row.dataset.exited = '1'; row.classList.add('visit-exited');
                    row.querySelector('[data-visit-exit]').textContent = 'Salida ya registrada';
                    exitButton.textContent = 'Salida registrada';
                    exitButton.disabled = true;
                    showModuleToast('Ya estaba registrada', 'La salida de este visitante ya se había guardado. No hace falta repetirla.');
                } else {
                    row.dataset.exited = '';
                    exitButton.disabled = false;
                    exitButton.textContent = 'Registrar salida';
                    showModuleToast('No se registró la salida', error.message || 'Inténtalo nuevamente.');
                }
            } finally {
                checkoutInFlight.delete(visit.id);
            }
        });
        visitRows.prepend(row); filterHospitalVisits();
    };
    const loadHospitalVisits = async () => {
        try {
            const patient = hospitalPatients[visitPatientSelect.value];
            if (!patient) return;
            const response = await fetch(`/api/visitas-hospitalarias?patient=${encodeURIComponent(patient.name)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const payload = await response.json();
            if (!response.ok) return;
            visitRows.replaceChildren();
            [...payload.visits].reverse().forEach(renderHospitalVisit);
            if (!payload.visits.length) visitEmpty.textContent = `No hay visitas registradas para ${patient.name}.`;
            visitEmpty.hidden = payload.visits.length > 0;
        } catch { /* La pantalla se mantiene operativa si no hay conexión. */ }
    };
    const loadHospitalizedPatients = async () => {
        visitPatientSelect.disabled = true;
        try {
            const response = await fetch('/api/pacientes-hospitalizados', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const payload = await response.json();
            if (!response.ok || !Array.isArray(payload.patients) || !payload.patients.length) throw new Error('No hay pacientes hospitalizados activos.');
            hospitalPatients = Object.fromEntries(payload.patients.map(patient => [patient.key, {
                ...patient,
                initials: patient.name.split(/\s+/).map(part => part[0]).join('').slice(0, 2).toUpperCase(),
            }]));
            visitPatientSelect.replaceChildren(...Object.entries(hospitalPatients).map(([key, patient]) => {
                const option = document.createElement('option');
                option.value = key;
                option.textContent = `${patient.name} · ${patient.detail.replace('Hospitalización · ', '')}`;
                return option;
            }));
            updateVisitPatient();
        } catch (error) {
            visitPatientSelect.replaceChildren();
            const option = document.createElement('option');
            option.textContent = 'No hay pacientes hospitalizados activos';
            option.value = '';
            visitPatientSelect.append(option);
            showModuleToast('Hospitalización', error.message || 'No se pudo cargar el listado de pacientes hospitalizados.');
        } finally {
            visitPatientSelect.disabled = false;
        }
    };
    loadHospitalizedPatients();
    visitForm.addEventListener('submit', async event => {
        event.preventDefault();
        const values = new FormData(visitForm);
        const name = String(values.get('name') || '').trim();
        const dni = String(values.get('dni') || '').replace(/[^0-9]/g, '');
        const relationship = String(values.get('relationship') || '').trim();
        const patient = hospitalPatients[visitPatientSelect.value];
        if (!patient) { showModuleToast('Paciente requerido', 'Selecciona un paciente hospitalizado activo.'); return; }
        if (!validVisitorDni(dni)) { showModuleToast('DNI invalido', 'Ingresa un DNI valido de 8 digitos.'); return; }
        if (!name || (!manualVisitorEntry && resolvedVisitorDni !== dni)) { showModuleToast('Identidad pendiente', manualVisitorEntry ? 'Ingresa el nombre completo del visitante.' : 'Espera la consulta RENIEC antes de registrar la visita.'); return; }
        if (!/^[A-Za-zÁÉÍÓÚÑÜáéíóúñü\s'.-]+$/.test(name)) { showModuleToast('Nombre inválido', 'El nombre solo puede tener letras y espacios, sin números ni símbolos.'); return; }
        if (!relationship) { showModuleToast('Parentesco requerido', 'Selecciona el parentesco del visitante.'); return; }
        const activeVisit = [...visitRows.querySelectorAll('.visit-row:not(.visit-exited)')]
            .find(row => row.dataset.visitorDni === dni);
        if (activeVisit) {
            showModuleToast('Visita duplicada', 'Este DNI ya tiene una visita activa. Registra su salida antes de ingresarlo nuevamente.');
            activeVisit.classList.add('highlight-flash');
            setTimeout(() => activeVisit.classList.remove('highlight-flash'), 1200);
            return;
        }
        const submitButton = visitForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        try {
            const response = await fetch('/api/visitas-hospitalarias', {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ patient_label: `${patient.name} · ${patient.detail}`, visitor_name: name, visitor_dni: dni, relationship }),
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'No se pudo registrar la visita.');
            renderHospitalVisit(payload.visit);
            visitForm.reset(); resetVisitorIdentity(); visitDniInput.setCustomValidity(''); visitDniInput.classList.remove('is-invalid');
            showModuleToast('Entrada registrada', 'La visita fue guardada y aparecerá en los reportes.');
        } catch (error) {
            showModuleToast('No se registró la entrada', error.message || 'Inténtalo nuevamente.');
        } finally {
            submitButton.disabled = false;
        }
        return;
        visitForm.elements.dni.value = dni;
        const row = document.createElement('article');
        row.className = 'visit-row';
        row.innerHTML = `<div><strong>${escapeHtml(name)}</strong><small>DNI ${escapeHtml(dni)} · ${escapeHtml(relationship)}</small></div><time>Entrada ${new Date().toLocaleTimeString('es-PE',{hour:'2-digit',minute:'2-digit'})}</time><button type="button">Registrar salida</button>`;
        row.querySelector('button').addEventListener('click', event => {
            const exitButton = event.currentTarget;
            if (row.dataset.exited) return;
            row.dataset.exited = '1';
            row.classList.add('visit-exited');
            row.querySelector('time').textContent = `Salida ${new Date().toLocaleTimeString('es-PE',{hour:'2-digit',minute:'2-digit'})}`;
            exitButton.textContent = 'Salida registrada';
            exitButton.disabled = true;
            showModuleToast('Salida registrada', 'La salida del visitante fue registrada correctamente.');
        });
        row.dataset.patient = visitPatientSelect.value;
        row.dataset.visitorDni = dni;
        row.querySelector('small').textContent = `Visita a ${patient.name} · DNI ${dni} · ${relationship}`;
        visitRows.prepend(row);
        visitEmpty.hidden = true;
        visitForm.reset();
        resetVisitorIdentity();
        visitDniInput.setCustomValidity('');
        visitDniInput.classList.remove('is-invalid');
        showModuleToast('Entrada registrada', 'El familiar aparece vinculado al paciente hospitalizado.');
    });
}

if (liveClock) {
    const updateClock = () => { liveClock.textContent = new Date().toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }); };
    updateClock();
    setInterval(updateClock, 1000);
}
const showModuleToast = (title = 'Acción completada', message = 'Los cambios se guardaron correctamente.') => {
    const toast = document.getElementById('moduleToast'); if(!toast) return;
    toast.querySelector('strong').textContent = title; toast.querySelector('p').textContent = message;
    toast.classList.add('show'); setTimeout(() => toast.classList.remove('show'), 2800);
};
let liveRefreshPending = false;
// Actualiza solamente las zonas de datos del módulo. Así una cita nueva o editada
// aparece sin reiniciar la página, perder filtros ni mostrar el preloader.
const refreshPortalDataInPlace = async (message = 'La información se actualizó sin recargar la página.') => {
    if (liveRefreshPending || !document.getElementById('patientsTableBody')) return;
    liveRefreshPending = true;
    try {
        const response = await fetch(window.location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error();
        const updatedDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
        ['.portal-stats', '#patientsTableBody', '#patientsFilterEmpty'].forEach(selector => {
            const current = document.querySelector(selector);
            const updated = updatedDocument.querySelector(selector);
            if (current && updated) current.outerHTML = updated.outerHTML;
        });
        document.dispatchEvent(new CustomEvent('appointments:changed'));
        document.getElementById('patientsSearch')?.dispatchEvent(new Event('input'));
        showModuleToast('Información actualizada', message);
    } catch {
        showModuleToast('Actualización pendiente', 'No se pudo sincronizar ahora. Se reintentará automáticamente.');
    } finally {
        liveRefreshPending = false;
    }
};
// Los enlaces de acceso rápido ya tienen un href real. Este comportamiento es
// únicamente para botones de demostración; aplicarlo también a los enlaces
// provocaba dos acciones durante el mismo clic y hacía Reportes intermitente.
document.querySelectorAll('button.moduleAction').forEach(btn => btn.addEventListener('click', () => {
    if (btn.dataset.url) window.location.assign(btn.dataset.url);
    else showModuleToast('Función disponible', 'El módulo está listo para conectar con la base de datos.');
}));

const attendanceModal = document.getElementById('attendanceModal');
if (attendanceModal) {
    // El contenedor del portal tiene una animación con transform; el modal debe
    // vivir directamente en body para posicionarse respecto a toda la ventana.
    document.body.appendChild(attendanceModal);
    const attendanceTitle = document.getElementById('attendanceModalTitle');
    const attendanceName = document.getElementById('attendanceModalName');
    const attendanceSub = document.getElementById('attendanceModalSub');
    const attendanceYes = document.getElementById('attendanceModalYes');
    let pendingAttendanceForm = null;
    const closeAttendanceModal = () => {
        attendanceModal.hidden = true;
        pendingAttendanceForm = null;
        document.documentElement.classList.remove('attendance-modal-open');
        document.body.classList.remove('attendance-modal-open');
    };
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-attendance-action]');
        if (!button) return;
        pendingAttendanceForm = document.getElementById(button.dataset.formTarget);
        const isLateArrival = button.dataset.attendanceAction === 'tardanza';
        attendanceTitle.textContent = isLateArrival ? '¿Registrar llegada tardía?' : '¿Desea tomar asistencia?';
        attendanceYes.textContent = isLateArrival ? 'Sí, registrar tardanza' : 'Sí, confirmar';
        attendanceYes.classList.remove('danger');
        attendanceName.textContent = button.dataset.patientName || '';
        attendanceSub.textContent = button.dataset.patientMeta || '';
        document.documentElement.classList.add('attendance-modal-open');
        document.body.classList.add('attendance-modal-open');
        attendanceModal.hidden = false;
    });
    attendanceYes.addEventListener('click', () => {
        // La fila puede haber sido reemplazada por una actualización en vivo. En
        // ese caso el formulario nuevo todavía no tenía enlazado el envío AJAX y
        // el botón de tardanza parecía no responder o recargaba toda la página.
        const form = pendingAttendanceForm;
        if (!form) {
            showModuleToast('No se pudo registrar', 'Actualiza la lista e intenta nuevamente.');
            closeAttendanceModal();
            return;
        }
        bindAjaxForm(form);
        form.requestSubmit();
        closeAttendanceModal();
    });
    document.getElementById('attendanceModalNo')?.addEventListener('click', closeAttendanceModal);
    document.getElementById('attendanceModalClose')?.addEventListener('click', closeAttendanceModal);
    attendanceModal.addEventListener('click', event => { if (event.target === attendanceModal) closeAttendanceModal(); });
    enableSwipeToDismiss(attendanceModal.querySelector('.modal'), closeAttendanceModal);
}
document.getElementById('patientButton')?.addEventListener('click', () => {
    document.getElementById('patientResult')?.classList.add('visible');
    showModuleToast('Paciente encontrado', 'Identidad y cita verificadas.');
});
document.getElementById('settingsForm')?.addEventListener('submit', e => { e.preventDefault(); showModuleToast('Configuración guardada'); });
const historySearch = document.getElementById('historySearch');
const accessFilter = document.getElementById('accessFilter');
const filterHistory = () => document.querySelectorAll('#historyBody tr').forEach(row => {
    const matchesText = row.textContent.toLowerCase().includes((historySearch?.value || '').toLowerCase());
    const matchesType = !accessFilter?.value || row.dataset.movement === accessFilter.value;
    row.hidden = !(matchesText && matchesType);
});
[historySearch, accessFilter].forEach(el => el?.addEventListener('input', filterHistory));
document.getElementById('helpSearch')?.addEventListener('input', e => document.querySelectorAll('.help-grid article').forEach(card => card.hidden = !card.textContent.toLowerCase().includes(e.target.value.toLowerCase())));

const accessForm = document.getElementById('accessForm');
const accessDni = document.getElementById('accessDni');
const registerAccess = document.getElementById('registerAccess');
document.querySelectorAll('[data-access-type]').forEach(button => button.addEventListener('click', () => {
    document.querySelectorAll('[data-access-type]').forEach(item => item.classList.remove('active'));
    button.classList.add('active');
    const type = button.dataset.accessType;
    document.getElementById('movementType').value = type;
    registerAccess.textContent = `Confirmar ${type.toLowerCase()}`;
    registerAccess.classList.toggle('exit-button', type === 'Salida');
}));
const lookupDni = async () => {
    const hint = document.getElementById('dniHint'); const button = document.getElementById('verifyDni');
    if (!/^\d{8}$/.test(accessDni.value)) { hint.textContent='Ingresa un DNI válido de 8 dígitos.'; hint.classList.add('error'); return; }
    button.disabled=true; button.textContent='Consultando...'; hint.classList.remove('error'); hint.textContent='Consumiendo API RENIEC...';
    try {
        const response=await fetch(`/api/consultar-dni/${accessDni.value}`,{headers:{Accept:'application/json'}}); const data=await response.json();
        if(!response.ok) throw new Error(data.message||'No se encontró el DNI.');
        document.getElementById('identifiedInitials').textContent=data.nombre_completo.split(/\s+/).slice(0,2).map(word=>word[0]).join('').toUpperCase();
        document.getElementById('identifiedName').textContent=data.nombre_completo; document.getElementById('identifiedDni').textContent=`Paciente · DNI ${data.dni}`;
        document.getElementById('identifiedPerson').hidden=false; registerAccess.disabled=false; hint.textContent='Nombre completo obtenido correctamente.';
    } catch(error) { hint.textContent=error.message; hint.classList.add('error'); document.getElementById('identifiedPerson').hidden=true; registerAccess.disabled=true; }
    finally { button.disabled=false; button.textContent='Consultar'; }
};
document.getElementById('verifyDni')?.addEventListener('click',lookupDni);
accessDni?.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();lookupDni();}});
accessDni?.addEventListener('input', () => { registerAccess.disabled = true; document.getElementById('identifiedPerson').hidden = true; });
accessForm?.addEventListener('submit', e => {
    e.preventDefault();
    const type = document.getElementById('movementType').value;
    const recent = document.getElementById('recentAccessList');
    const now = new Date().toLocaleTimeString('es-PE', {hour:'2-digit', minute:'2-digit', hour12: true});
    recent.insertAdjacentHTML('afterbegin', `<article class="new-movement"><span class="recent-arrow ${type.toLowerCase()}">${type === 'Ingreso' ? '→' : '←'}</span><div><strong>Ana Ruiz Mendoza</strong><p>${type} · ${document.getElementById('accessPoint').value}</p></div><time>${now}</time></article>`);
    showModuleToast(`${type} registrado`, `El movimiento de Ana Ruiz Mendoza se guardó a las ${now}.`);
    accessForm.reset(); document.getElementById('identifiedPerson').hidden = true; registerAccess.disabled = true;
});

// El área/destino debe pertenecer al mismo servicio que la atención elegida,
// si no el backend rechaza la cita con "El área no pertenece al servicio seleccionado."
const appointmentTypeSelect = document.getElementById('appointmentTypeSelect');
const serviceAreaSelect = document.getElementById('serviceAreaSelect');
if (appointmentTypeSelect && serviceAreaSelect) {
    const areaOptions = [...serviceAreaSelect.querySelectorAll('option')];
    const areaGroups = [...serviceAreaSelect.querySelectorAll('optgroup')];
    const syncAreaOptions = () => {
        const selectedOption = appointmentTypeSelect.selectedOptions[0];
        const serviceId = selectedOption?.dataset.serviceId || '';
        let currentStillValid = false;
        areaOptions.forEach(option => {
            const matches = !option.dataset.serviceId || !serviceId || option.dataset.serviceId === serviceId;
            option.hidden = !matches;
            if (matches && option === serviceAreaSelect.selectedOptions[0]) currentStillValid = true;
        });
        areaGroups.forEach(group => { group.hidden = ![...group.children].some(option => !option.hidden); });
        if (!currentStillValid) { serviceAreaSelect.value = ''; serviceAreaSelect.dispatchEvent(new Event('change', { bubbles: true })); }
    };
    appointmentTypeSelect.addEventListener('change', syncAreaOptions);
    syncAreaOptions();
}

const appointmentDni = document.querySelector('.appointment-form input[name="dni"]');
const appointmentName = document.querySelector('.appointment-form input[name="patient_name"]');
if (appointmentDni && appointmentName) {
    let manualDniEntry = false;
    const lookupStatus = document.createElement('small');
    lookupStatus.className = 'appointment-dni-status';
    const lookupControls = document.createElement('div');
    lookupControls.className = 'appointment-dni-controls';
    const manualButton = document.createElement('button');
    manualButton.type = 'button';
    manualButton.className = 'dni-manual-toggle';
    manualButton.textContent = '✎ Ingreso manual';
    lookupControls.append(lookupStatus, manualButton);
    appointmentDni.insertAdjacentElement('afterend', lookupControls);
    const appointmentForm = appointmentDni.closest('form');
    ['first_names','paternal_surname','maternal_surname'].forEach(name => {
        const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = name; appointmentForm.appendChild(hidden);
    });
    const patientDetails = document.createElement('details');
    patientDetails.className = 'patient-extra-fields form-wide';
    patientDetails.innerHTML = `<summary>＋ Datos adicionales del paciente</summary><div class="appointment-form"><label>Fecha de nacimiento<input type="date" name="birth_date"></label><label>Sexo<select name="sex"><option value="">No especificado</option><option value="F">Femenino</option><option value="M">Masculino</option><option value="O">Otro</option></select></label><label>Teléfono<input name="phone" maxlength="20"></label><label>Correo<input type="email" name="email"></label><label>Historia clínica<input name="medical_record_number" maxlength="30"></label><label>Seguro<input name="insurance" maxlength="100"></label><label class="form-wide">Dirección<input name="address" maxlength="180"></label></div>`;
    appointmentForm.querySelector('button.primary-btn')?.insertAdjacentElement('beforebegin', patientDetails);
    let lookupTimer;
    let lastDni = '';
    const lookupAppointmentDni = async () => {
        if (manualDniEntry) return;
        const dni = appointmentDni.value.replace(/\D/g, '').slice(0, 8);
        appointmentDni.value = dni;
        if (dni.length !== 8 || dni === lastDni) return;
        lastDni = dni;
        appointmentDni.classList.add('is-loading');
        appointmentName.readOnly = true;
        lookupStatus.className = 'appointment-dni-status loading';
        lookupStatus.textContent = 'Consumiendo API RENIEC...';
        try {
            const response = await fetch(`/api/consultar-dni/${dni}`, {headers:{Accept:'application/json'}});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'No fue posible consultar el DNI.');
            appointmentName.value = data.nombre_completo;
            appointmentForm.elements.first_names.value = data.nombres || '';
            appointmentForm.elements.paternal_surname.value = data.apellido_paterno || '';
            appointmentForm.elements.maternal_surname.value = data.apellido_materno || '';
            if (data.direccion) appointmentForm.elements.address.value = data.direccion;
            if (data.fecha_nacimiento) appointmentForm.elements.birth_date.value = data.fecha_nacimiento;
            if (data.sexo) appointmentForm.elements.sex.value = data.sexo;
            const hasExtra = data.direccion || data.fecha_nacimiento || data.sexo;
            if (hasExtra) patientDetails.open = true;
            lookupStatus.className = 'appointment-dni-status success';
            lookupStatus.textContent = hasExtra ? '✓ Identidad y datos adicionales encontrados en RENIEC' : '✓ Identidad encontrada en RENIEC';
        } catch (error) {
            appointmentName.value = '';
            appointmentForm.elements.first_names.value = '';
            appointmentForm.elements.paternal_surname.value = '';
            appointmentForm.elements.maternal_surname.value = '';
            appointmentForm.elements.address.value = '';
            appointmentForm.elements.birth_date.value = '';
            appointmentForm.elements.sex.value = '';
            appointmentName.readOnly = false;
            lookupStatus.className = 'appointment-dni-status error';
            lookupStatus.textContent = `${error.message} Puedes ingresar el nombre manualmente.`;
        } finally {
            appointmentDni.classList.remove('is-loading');
        }
    };
    appointmentDni.addEventListener('input', () => {
        clearTimeout(lookupTimer);
        if (appointmentDni.value.replace(/\D/g,'').length < 8) {
            lastDni = ''; appointmentName.value = ''; appointmentName.readOnly = false; lookupStatus.textContent = '';
            appointmentForm.elements.first_names.value = ''; appointmentForm.elements.paternal_surname.value = ''; appointmentForm.elements.maternal_surname.value = '';
        }
        lookupTimer = setTimeout(lookupAppointmentDni, 350);
    });
    appointmentDni.addEventListener('blur', lookupAppointmentDni);
    manualButton.addEventListener('click', () => {
        manualDniEntry = !manualDniEntry;
        appointmentName.readOnly = false;
        if (manualDniEntry) { appointmentForm.elements.first_names.value=''; appointmentForm.elements.paternal_surname.value=''; appointmentForm.elements.maternal_surname.value=''; }
        appointmentDni.classList.toggle('manual-entry', manualDniEntry);
        appointmentName.classList.toggle('manual-entry', manualDniEntry);
        manualButton.textContent = manualDniEntry ? '↻ Usar consulta automática' : '✎ Ingreso manual';
        lookupStatus.className = `appointment-dni-status ${manualDniEntry ? 'manual' : ''}`;
        lookupStatus.textContent = manualDniEntry ? 'Modo manual activo: no se consumirán consultas RENIEC.' : 'Escribe los 8 dígitos para consultar RENIEC.';
        lastDni = '';
        if (!manualDniEntry && appointmentDni.value.length === 8) lookupAppointmentDni();
    });
}
if (document.getElementById('currentTime')) document.getElementById('currentTime').textContent = new Date().toLocaleTimeString('es-PE', {hour:'2-digit',minute:'2-digit',hour12:true});

document.getElementById('scanDni')?.addEventListener('click',async()=>{
    if(!('BarcodeDetector' in window)){showModuleToast('Escáner no compatible','Escribe el DNI y pulsa Consultar.');return;}
    let stream; const overlay=document.createElement('div'); overlay.className='scanner-overlay'; overlay.innerHTML='<section><button type="button">×</button><h2>Escanear DNI</h2><p>Coloca el código del documento dentro del recuadro.</p><div class="camera-frame"><video autoplay playsinline></video><i></i></div><small>La cámara se cerrará al detectar 8 dígitos.</small></section>'; document.body.appendChild(overlay);
    const video=overlay.querySelector('video'); let active=true; const close=()=>{active=false;stream?.getTracks().forEach(track=>track.stop());overlay.remove();}; overlay.querySelector('button').addEventListener('click',close);
    try { stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}}}); video.srcObject=stream; const detector=new BarcodeDetector({formats:['qr_code','pdf417','code_128','code_39']});
        const detect=async()=>{if(!active)return;try{for(const code of await detector.detect(video)){const match=code.rawValue.match(/\b\d{8}\b/);if(match){accessDni.value=match[0];close();await lookupDni();return;}}}catch{}requestAnimationFrame(detect);};detect();
    } catch {close();showModuleToast('No se pudo abrir la cámara','Revisa el permiso de cámara o escribe el DNI manualmente.');}
});

const roleSidebarEl = document.querySelector('.role-sidebar');
let roleSidebarBackdrop = null;
if (roleSidebarEl) {
    roleSidebarBackdrop = document.createElement('div');
    roleSidebarBackdrop.className = 'role-sidebar-backdrop';
    roleSidebarEl.insertAdjacentElement('afterend', roleSidebarBackdrop);
}
const openRoleSidebar = () => {
    roleSidebarEl?.classList.add('open');
    roleSidebarBackdrop?.classList.add('visible');
    document.body.classList.add('menu-open');
};
const closeRoleSidebar = () => {
    roleSidebarEl?.classList.remove('open');
    roleSidebarBackdrop?.classList.remove('visible');
    document.body.classList.remove('menu-open');
};
document.getElementById('roleMenu')?.addEventListener('click', () => {
    roleSidebarEl?.classList.contains('open') ? closeRoleSidebar() : openRoleSidebar();
});
roleSidebarBackdrop?.addEventListener('click', closeRoleSidebar);
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && roleSidebarEl?.classList.contains('open')) closeRoleSidebar();
});

// Gestos táctiles: deslizar desde el borde izquierdo abre el menú, deslizar
// el menú hacia la izquierda lo cierra (patrón de "drawer" fluido en móvil).
if (roleSidebarEl) {
    let touchStartX = 0, touchStartY = 0, tracking = false, fromEdge = false;
    // Zona amplia para que sea cómodo en teléfonos, sin interceptar el scroll vertical.
    const EDGE = 72, THRESHOLD = 45;
    document.addEventListener('touchstart', event => {
        if (window.innerWidth > 760 || event.touches.length !== 1) return;
        const x = event.touches[0].clientX;
        const isOpen = roleSidebarEl.classList.contains('open');
        if (!isOpen && x > EDGE) return;
        touchStartX = x;
        touchStartY = event.touches[0].clientY;
        fromEdge = !isOpen;
        tracking = true;
    }, { passive: true });
    document.addEventListener('touchmove', event => {
        if (!tracking || event.touches.length !== 1) return;
        const dx = event.touches[0].clientX - touchStartX;
        const dy = event.touches[0].clientY - touchStartY;
        if (Math.abs(dy) > Math.abs(dx)) { tracking = false; return; }
    }, { passive: true });
    document.addEventListener('touchend', event => {
        if (!tracking) return;
        tracking = false;
        const dx = (event.changedTouches[0]?.clientX ?? touchStartX) - touchStartX;
        const isOpen = roleSidebarEl.classList.contains('open');
        if (fromEdge && !isOpen && dx > THRESHOLD) openRoleSidebar();
        else if (isOpen && dx < -THRESHOLD) closeRoleSidebar();
    }, { passive: true });
}
const userMenuButton = document.getElementById('userMenuButton');
if (roleSidebarEl) {
    const collapseButton = document.createElement('button');
    collapseButton.type = 'button';
    collapseButton.className = 'sidebar-collapse';
    collapseButton.innerHTML = '<span>‹</span>';
    roleSidebarEl.appendChild(collapseButton);
    const setCollapsed = collapsed => {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        collapseButton.innerHTML = `<span>${collapsed ? '›' : '‹'}</span>`;
        collapseButton.setAttribute('aria-label', collapsed ? 'Mostrar menú lateral' : 'Ocultar menú lateral');
    };
    if (window.innerWidth > 760) setCollapsed(localStorage.getItem('hospital-sidebar') === 'collapsed');
    collapseButton.addEventListener('click', () => {
        if (window.innerWidth <= 760) {
            closeRoleSidebar();
            return;
        }
        const collapsed = !document.body.classList.contains('sidebar-collapsed');
        setCollapsed(collapsed);
        localStorage.setItem('hospital-sidebar', collapsed ? 'collapsed' : 'expanded');
    });
    window.addEventListener('resize', () => window.innerWidth <= 760 ? document.body.classList.remove('sidebar-collapsed') : setCollapsed(localStorage.getItem('hospital-sidebar') === 'collapsed'));
}
const userDropdown = document.getElementById('userDropdown');
const closeUserDropdown = () => {
    if (!userDropdown) return;
    userDropdown.hidden = true;
    userMenuButton?.setAttribute('aria-expanded', 'false');
};
// Da prioridad absoluta al enlace Reportes frente a los manejadores globales
// que cierran el menú, animan la página o atienden gestos táctiles.
document.querySelectorAll('[data-direct-navigation]').forEach(link => {
    link.addEventListener('click', event => {
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        event.stopPropagation();
        const destination = link.href;
        closeUserDropdown();
        showLoader('Abriendo reportes...');
        window.location.assign(destination);
    }, { capture: true });
});
userMenuButton?.addEventListener('click', event => {
    event.stopPropagation();
    const isOpening = userDropdown.hidden;
    document.querySelector('.notifications-popover')?.setAttribute('hidden', '');
    userDropdown.hidden = !isOpening;
    userMenuButton.setAttribute('aria-expanded', String(isOpening));
});
document.addEventListener('click', event => {
    if (userDropdown && !userDropdown.hidden && !event.target.closest('.user-menu')) {
        closeUserDropdown();
    }
});
enableSwipeToDismiss(userDropdown, () => {
    if (!userDropdown || userDropdown.hidden) return;
    closeUserDropdown();
});
const patientsDateFilter = document.getElementById('patientsDateFilter');
if (patientsDateFilter) {
    let dateFilterTimer;
    const submitDateFilter = () => patientsDateFilter.requestSubmit();
    patientsDateFilter.addEventListener('change', submitDateFilter);
    patientsDateFilter.addEventListener('input', () => {
        clearTimeout(dateFilterTimer);
        dateFilterTimer = setTimeout(submitDateFilter, 350);
    });
    if (!patientsDateFilter.querySelector('[data-apply-dates]')) {
        const applyDatesButton = document.createElement('button');
        applyDatesButton.type = 'submit';
        applyDatesButton.dataset.applyDates = '1';
        applyDatesButton.textContent = 'Aplicar fechas';
        patientsDateFilter.appendChild(applyDatesButton);
    }
}
const patientsSearch = document.getElementById('patientsSearch');
if (patientsSearch) {
    const patientsServiceFilter = document.getElementById('patientsServiceFilter');
    const patientsStatusFilter = document.getElementById('patientsStatusFilter');
    const patientsEmpty = document.getElementById('patientsFilterEmpty');
    const applyPatientFilters = () => {
        const patientsRows = [...document.querySelectorAll('#patientsTableBody tr[data-search]')];
        const term = patientsSearch.value.trim().toLocaleLowerCase('es');
        const service = patientsServiceFilter.value;
        const status = patientsStatusFilter.value;
        let visible = 0;
        patientsRows.forEach(row => {
            const matches = (!term || row.dataset.search.includes(term)) && (!service || row.dataset.service === service) && (!status || row.dataset.status === status);
            row.hidden = !matches;
            if (matches) visible++;
        });
        if (patientsEmpty) patientsEmpty.hidden = patientsRows.length === 0 || visible !== 0;
    };
    patientsSearch.addEventListener('input', applyPatientFilters);
    patientsServiceFilter.addEventListener('change', applyPatientFilters);
    patientsStatusFilter.addEventListener('change', applyPatientFilters);
}
const patientsSortHeaders = document.querySelectorAll('#upcomingPatients th[data-sort-key]');
if (patientsSortHeaders.length) {
    const sortAttr = { scheduled: 'scheduledAt', patient: 'patientName', service: 'serviceName', status: 'status', entry: 'entryAt', exit: 'exitAt' };
    patientsSortHeaders.forEach(th => {
        th.addEventListener('click', () => {
            const tableBody = document.getElementById('patientsTableBody');
            if (!tableBody) return;
            const dir = th.dataset.sortDir === 'asc' ? 'desc' : 'asc';
            patientsSortHeaders.forEach(other => delete other.dataset.sortDir);
            th.dataset.sortDir = dir;
            const attr = sortAttr[th.dataset.sortKey];
            const rows = [...tableBody.querySelectorAll('tr[data-row-id]')];
            rows.sort((a, b) => {
                const va = a.dataset[attr] || '';
                const vb = b.dataset[attr] || '';
                if (va === '' && vb === '') return 0;
                if (va === '') return 1;
                if (vb === '') return -1;
                const cmp = va.localeCompare(vb, 'es', { numeric: true });
                return dir === 'asc' ? cmp : -cmp;
            });
            rows.forEach(row => tableBody.appendChild(row));
        });
    });
}
if (document.getElementById('patientsTableBody')) {
    let appointmentsVersion = null;
    const appointmentsVersionUrl = new URL('/api/portero/citas-version', window.location.origin);
    const range = new URLSearchParams(window.location.search);
    ['from', 'to'].forEach(key => { if (range.has(key)) appointmentsVersionUrl.searchParams.set(key, range.get(key)); });
    const refreshAppointmentsWhenChanged = async () => {
        try {
            const response = await fetch(appointmentsVersionUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) return;
            const { version } = await response.json();
            if (appointmentsVersion !== null && appointmentsVersion !== version) {
                appointmentsVersion = version;
                await refreshPortalDataInPlace('Se detectó un cambio en las citas.');
                return;
            }
            appointmentsVersion = version;
        } catch { /* La consulta manual seguirá disponible si no hay conexión. */ }
    };
    refreshAppointmentsWhenChanged();
    setInterval(refreshAppointmentsWhenChanged, 5000);
}
const notifBell = document.getElementById('notifBell');
if (notifBell) {
    const notifBadge = document.getElementById('notifBadge');
    const notificationsPanel = document.createElement('section');
    notificationsPanel.className = 'notifications-popover';
    notificationsPanel.hidden = true;
    notificationsPanel.innerHTML = '<div class="notifications-head"><strong>Notificaciones</strong><button type="button" aria-label="Cerrar notificaciones">×</button></div><div class="notifications-body"><p>Cargando notificaciones...</p></div>';
    notifBell.parentElement.appendChild(notificationsPanel);
    const closeNotifications = () => {
        notificationsPanel.hidden = true;
        notifBell.setAttribute('aria-expanded', 'false');
    };
    const renderNotifications = notifications => {
        const body = notificationsPanel.querySelector('.notifications-body');
        body.innerHTML = notifications.length
            ? notifications.map(item => `<article><span>●</span><div><strong>${escapeHtml(item.patient)}</strong><small>${escapeHtml(item.service)} · ${escapeHtml(item.time)}</small></div></article>`).join('')
            : '<p class="notifications-empty">No hay pacientes próximos por atender.</p>';
    };
    // Se mantiene precargado desde el mismo sondeo de 20s de la campana, para
    // que abrir el panel se sienta instantáneo en vez de esperar un fetch.
    let cachedNotifications = null;
    const refreshNotificationsCache = async () => {
        try {
            const response = await fetch('/api/portero/notificaciones', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) return;
            const payload = await response.json();
            cachedNotifications = payload.notifications || [];
            if (!notificationsPanel.hidden) renderNotifications(cachedNotifications);
        } catch { /* se reintenta en el siguiente ciclo */ }
    };
    notifBell.addEventListener('click', () => {
        const isOpening = notificationsPanel.hidden;
        if (isOpening) closeUserDropdown();
        notificationsPanel.hidden = !isOpening;
        notifBell.setAttribute('aria-expanded', String(isOpening));
        if (!isOpening) return;
        if (cachedNotifications) renderNotifications(cachedNotifications); // instantáneo con lo último en caché
        refreshNotificationsCache(); // y se refresca en silencio por si cambió algo
    });
    refreshNotificationsCache();
    notificationsPanel.querySelector('button').addEventListener('click', closeNotifications);
    document.addEventListener('click', event => { if (!event.target.closest('.notif-bell, .notifications-popover')) closeNotifications(); });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        closeNotifications();
        closeUserDropdown();
    });
    let lastKnownCount = parseInt(notifBadge?.textContent || '0', 10);
    const pollPending = async () => {
        try {
            const response = await fetch(notifBell.dataset.pollUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const { count } = await response.json();
            if (notifBadge) {
                notifBadge.textContent = count;
                notifBadge.classList.toggle('notif-hidden', count === 0);
            }
            if (count !== lastKnownCount) {
                notifBell.classList.add('notif-pulse');
                setTimeout(() => notifBell.classList.remove('notif-pulse'), 1500);
                if (count > lastKnownCount) refreshPortalDataInPlace('Hay cambios en las citas programadas.');
                lastKnownCount = count;
                refreshNotificationsCache();
            }
        } catch { /* red intermitente: se reintenta en el siguiente ciclo */ }
    };
    setInterval(pollPending, 20000);
}
document.getElementById('portalSearch')?.addEventListener('click',async()=>{
    const dni=document.getElementById('portalDni').value; const result=document.getElementById('portalPatientResult'); const button=document.getElementById('portalSearch');
    if (button.disabled) return;
    if(!/^\d{8}$/.test(dni)){result.hidden=false;result.innerHTML='<div class="portal-empty">Ingresa un DNI válido de 8 dígitos.</div>';return;}
    button.disabled=true;button.textContent='Buscando...';
    result.hidden=false;
    result.innerHTML='<div class="skeleton-card"><span class="skeleton-avatar"></span><div><span class="skeleton-line" style="width:55%"></span><span class="skeleton-line" style="width:35%"></span></div></div>';
    try{const response=await fetch(`/api/pacientes/${dni}/citas`,{headers:{Accept:'application/json'}});const data=await response.json();if(!response.ok)throw new Error(data.message);
        const initials=escapeHtml(data.patient.name.split(/\s+/).slice(0,2).map(x=>x[0]).join(''));
        const cards=data.appointments.map(a=>`<article class="appointment-card"><div class="appointment-card-head"><div class="appointment-date"><strong>${escapeHtml(a.time)}</strong><small>${escapeHtml(a.date)}</small></div><b class="appointment-status">${escapeHtml(a.status)}</b></div><div class="appointment-info"><span class="appointment-service">${escapeHtml(a.service)}</span><h3>${escapeHtml(a.type)}</h3><p>${escapeHtml(a.location||'Ubicación por confirmar')}</p>${a.preparation?`<small class="prep">Preparación: ${escapeHtml(a.preparation)}</small>`:''}</div></article>`).join('')||'<div class="portal-empty">El paciente no tiene citas vigentes.</div>';
        result.hidden=false;result.innerHTML=`<div class="patient-summary"><div class="avatar">${initials}</div><div><strong>${escapeHtml(data.patient.name)}</strong><p>DNI ${escapeHtml(data.patient.dni)} · Seguro ${escapeHtml(data.patient.insurance||'No registrado')}</p></div><span>${data.appointments.length} cita(s) vigente(s)</span></div><div class="appointment-cards">${cards}</div>`;
    }catch(error){result.hidden=false;result.innerHTML=`<div class="portal-empty">${error.message||'No fue posible realizar la búsqueda.'}</div>`;}finally{button.disabled=false;button.textContent='Buscar citas';}
});
document.getElementById('portalDni')?.addEventListener('input', event => {
    event.target.value = event.target.value.replace(/\D/g,'').slice(0,8);
    if (event.target.value.length === 8) document.getElementById('portalSearch')?.click();
});
document.getElementById('portalDni')?.addEventListener('keydown', event => {
    if(event.key === 'Enter'){ event.preventDefault(); document.getElementById('portalSearch')?.click(); }
});
document.addEventListener('appointments:changed', () => {
    const dni = document.getElementById('portalDni');
    const result = document.getElementById('portalPatientResult');
    if (dni?.value.length === 8 && result && !result.hidden) document.getElementById('portalSearch')?.click();
});

const scanModalElement = document.getElementById('scanModal');
if (scanModalElement) {
    const scanModal = document.getElementById('scanModal');
    // Keep the scanner outside animated/transformed portal containers. A fixed
    // element inside one of those containers is positioned against the page
    // content, which made the camera appear below the visible viewport.
    if (scanModal.parentElement !== document.body) document.body.appendChild(scanModal);
    const scanVideo = document.getElementById('scanVideo');
    const scanHint = document.getElementById('scanHint');
    let scanStream = null;
    let scanRafId = null;
    let scanScrollY = 0;

    const lockScannerScroll = () => {
        scanScrollY = window.scrollY;
        document.documentElement.classList.add('scanner-open');
        document.body.classList.add('scanner-open');
    };

    const unlockScannerScroll = () => {
        document.documentElement.classList.remove('scanner-open');
        document.body.classList.remove('scanner-open');
        window.scrollTo(0, scanScrollY);
    };

    const extractDni = raw => {
        const boundaryMatch = raw.match(/\b\d{8}\b/);
        if (boundaryMatch) return boundaryMatch[0];
        const digitsOnly = raw.replace(/\D/g, '');
        return digitsOnly.length >= 8 ? digitsOnly.slice(0, 8) : null;
    };

    const stopScan = () => {
        if (scanRafId) cancelAnimationFrame(scanRafId);
        scanRafId = null;
        scanStream?.getTracks().forEach(track => track.stop());
        scanStream = null;
        scanModal.hidden = true;
        unlockScannerScroll();
    };

    const scanFrame = async detector => {
        if (!scanStream) return;
        try {
            const codes = await detector.detect(scanVideo);
            const dni = codes.length ? extractDni(codes[0].rawValue) : null;
            if (dni) {
                const input = document.getElementById('portalDni');
                input.value = dni;
                stopScan();
                input.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }
        } catch { /* frame not ready yet, keep trying */ }
        scanRafId = requestAnimationFrame(() => scanFrame(detector));
    };

    const startScan = async () => {
        lockScannerScroll();
        scanModal.hidden = false;
        if (!('BarcodeDetector' in window)) {
            scanHint.textContent = 'Tu navegador no soporta escaneo por cámara. Usa un lector USB/Bluetooth o ingresa el DNI manualmente.';
            return;
        }
        scanHint.textContent = 'Apunta la cámara al código de barras.';
        try {
            scanStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            scanVideo.srcObject = scanStream;
            await scanVideo.play().catch(() => {});
            const detector = new BarcodeDetector({ formats: ['code_128', 'code_39', 'code_93', 'codabar', 'ean_13', 'ean_8', 'itf', 'pdf417', 'qr_code'] });
            scanRafId = requestAnimationFrame(() => scanFrame(detector));
        } catch (error) {
            scanHint.textContent = 'No se pudo acceder a la cámara. Revisa los permisos del navegador.';
        }
    };

    document.addEventListener('click', event => {
        if (event.target.closest('#portalScanBtn')) startScan();
    });
    // pointerdown en captura evita que el video o el zoom del navegador intercepte la X.
    document.addEventListener('pointerdown', event => {
        if (event.target.closest('#scanModalClose')) {
            event.preventDefault();
            event.stopPropagation();
            stopScan();
            return;
        }
        // En móviles el gesto puede no terminar como click. Cerrar desde
        // pointerdown garantiza que tocar el fondo oscuro siempre funcione.
        if (event.target === scanModal) {
            event.preventDefault();
            stopScan();
        }
    }, true);
    document.getElementById('scanModalClose')?.addEventListener('click', stopScan);
    scanModal.addEventListener('click', event => { if (event.target === scanModal) stopScan(); });
    enableSwipeToDismiss(scanModal.querySelector('.modal'), stopScan);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !scanModal.hidden) stopScan();
    });
}

// Aviso de llegada de la hora de cita para Portería. Se genera con Web Audio
// para no depender de archivos externos y se recuerda por cita para no repetirlo.
const patientsTableBody = document.getElementById('patientsTableBody');
if (patientsTableBody) {
    let alertAudioContext = null;
    const alertedAppointmentsKey = `hospital-alerted-appointments-${new Date().toISOString().slice(0, 10)}`;
    const alertedAppointments = new Set(JSON.parse(localStorage.getItem(alertedAppointmentsKey) || '[]'));

    const enableAppointmentSound = () => {
        alertAudioContext ??= new (window.AudioContext || window.webkitAudioContext)();
        if (alertAudioContext.state === 'suspended') alertAudioContext.resume();
    };

    ['pointerdown', 'keydown'].forEach(eventName => document.addEventListener(eventName, enableAppointmentSound, { once: true }));

    const playAppointmentAlert = () => {
        if (!alertAudioContext || alertAudioContext.state !== 'running') return;
        [0, 0.22].forEach((delay, index) => {
            const oscillator = alertAudioContext.createOscillator();
            const gain = alertAudioContext.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = index ? 880 : 660;
            gain.gain.setValueAtTime(0.0001, alertAudioContext.currentTime + delay);
            gain.gain.exponentialRampToValueAtTime(0.16, alertAudioContext.currentTime + delay + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, alertAudioContext.currentTime + delay + 0.18);
            oscillator.connect(gain).connect(alertAudioContext.destination);
            oscillator.start(alertAudioContext.currentTime + delay);
            oscillator.stop(alertAudioContext.currentTime + delay + 0.2);
        });
    };

    const checkAppointmentsDueNow = () => {
        const now = Date.now();
        // El cuerpo de la tabla puede reemplazarse durante una sincronización en vivo.
        document.getElementById('patientsTableBody')?.querySelectorAll('tr[data-row-id][data-scheduled-at][data-status="programada"]').forEach(row => {
            const scheduledAt = new Date(row.dataset.scheduledAt).getTime();
            const appointmentId = row.dataset.rowId;
            if (!Number.isFinite(scheduledAt) || alertedAppointments.has(appointmentId) || now < scheduledAt || now - scheduledAt > 60_000) return;
            alertedAppointments.add(appointmentId);
            localStorage.setItem(alertedAppointmentsKey, JSON.stringify([...alertedAppointments]));
            playAppointmentAlert();
            showModuleToast('Cita programada ahora', `${row.dataset.patientName} · ${row.dataset.serviceName}`);
        });
    };

    checkAppointmentsDueNow();
    setInterval(checkAppointmentsDueNow, 5_000);
}

document.getElementById('passkeyLogin')?.addEventListener('click',async()=>{
    const button=document.getElementById('passkeyLogin');button.disabled=true;
    try{await Passkeys.verify();window.location.href='/portal';}catch(error){button.classList.add('passkey-error');button.querySelector('small').textContent='No se pudo verificar. Intenta nuevamente.';}finally{button.disabled=false;}
});
document.getElementById('passkeyRegister')?.addEventListener('click',async()=>{
    const button=document.getElementById('passkeyRegister');button.disabled=true;button.textContent='Esperando confirmación del dispositivo...';
    try{
        if(!window.isSecureContext) throw new Error('La huella requiere HTTPS o localhost.');
        if(!Passkeys.isSupported()) throw new Error('Este navegador o dispositivo no admite huella o Passkeys.');
        await Passkeys.register({name:`Dispositivo ${new Date().toLocaleDateString('es-PE')}`});
        button.textContent='✓ Huella configurada correctamente';setTimeout(()=>window.location.reload(),900);
    }catch(error){
        const cancelled=['NotAllowedError','AbortError'].includes(error?.name);
        button.textContent=cancelled?'La configuración fue cancelada. Intenta nuevamente.':(error?.message||'No se pudo configurar la huella.');
        button.classList.add('passkey-error');button.disabled=false;
    }
});

// --- Actualizaciones en vivo: aplicar HTML ya renderizado del servidor a un contenedor ---
function applyRowUpdate({ selector, action, id, html }) {
    const container = document.querySelector(selector);
    if (!container) return;
    const existing = id != null ? container.querySelector(`[data-row-id="${CSS.escape(String(id))}"]`) : null;
    if (action === 'deleted') {
        existing?.remove();
        if (container.classList.contains('role-list')) {
            document.querySelectorAll('select[name="custom_role_id"]').forEach(select => {
                [...select.options].forEach(option => { if (option.value === String(id)) option.remove(); });
                if (select.options.length === 0) {
                    select.disabled = true;
                    select.add(new Option('No hay roles personalizados todavía', ''));
                }
            });
        }
    } else if (html) {
        const template = document.createElement('template');
        template.innerHTML = html.trim();
        const newEl = template.content.firstElementChild;
        if (!newEl) return;
        if (existing) existing.replaceWith(newEl);
        else container.prepend(newEl);
        bindAjaxForm(newEl.matches?.('form') ? newEl : newEl.querySelector?.('form') || null);
        newEl.querySelectorAll?.('form').forEach(bindAjaxForm);
        // El nodo se reemplaza entero con el HTML fresco del servidor, así que sin
        // esto el cambio guardado no se nota a simple vista hasta refrescar la página.
        if (newEl.nodeType === 1) {
            newEl.classList.add('highlight-flash');
            setTimeout(() => newEl.classList.remove('highlight-flash'), 1200);
        }
    }
    const placeholder = container.querySelector('[data-empty-placeholder]');
    if (placeholder) placeholder.hidden = container.querySelector('[data-row-id]') !== null;
    const roleCount = document.getElementById('roleCount');
    if (roleCount && container.classList.contains('role-list')) roleCount.textContent = container.querySelectorAll('[data-row-id]').length;
    if (container.classList.contains('role-list') && action === 'created' && html) {
        const roleName = container.querySelector(`[data-row-id="${CSS.escape(String(id))}"] strong`)?.textContent?.trim();
        if (roleName) document.querySelectorAll('select[name="custom_role_id"]').forEach(select => {
            if (select.disabled) {
                select.disabled = false;
                select.innerHTML = '';
                select.add(new Option('Sin rol personalizado', ''));
            }
            if (![...select.options].some(option => option.value === String(id))) select.add(new Option(roleName, String(id)));
        });
    }
    if (selector === '#patientsTableBody') document.dispatchEvent(new CustomEvent('appointments:changed'));
}

// --- Envío de formularios por fetch, sin recargar la página ---
function bindAjaxForm(form) {
    if (!form || form.dataset.ajaxBound) return;
    form.dataset.ajaxBound = '1';
    form.addEventListener('submit', async event => {
        if (event.defaultPrevented) return;
        event.preventDefault();
        if (form.dataset.confirm && !(await showConfirmModal(form.dataset.confirm))) return;
        const submitBtn = form.querySelector('button[type="submit"], button:not([type])');
        if (submitBtn) submitBtn.disabled = true;
        try {
            // Siempre se envía como POST real (con _method spoofeado en el body si
            // corresponde): PHP solo parsea multipart/form-data en $_POST para
            // peticiones POST, así que un PUT/DELETE real con FormData nunca ve
            // el _token y el CSRF falla con 419.
            const response = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json' }, body: new FormData(form) });
            if (response.status === 419) {
                // La sesión quedó abierta tanto tiempo que el token de seguridad
                // de la página venció. No hay nada que reintentar sin recargar:
                // el formulario en pantalla nunca va a tener un token válido.
                showModuleToast('La página estuvo abierta mucho tiempo', 'Vamos a recargarla para que puedas guardar de nuevo.');
                setTimeout(() => window.location.reload(), 1600);
                return;
            }
            const payload = await response.json().catch(() => null);
            if (!response.ok) {
                showModuleToast('No se pudo guardar', payload?.message || 'Revisa los datos e intenta nuevamente.');
                return;
            }
            (payload?.targets || []).forEach(applyRowUpdate);
            if (form.dataset.resetOnSuccess) form.reset();
            // Los listeners de 'ajax:success' pueden cambiar el propio formulario
            // (ej. volver de modo edición a modo creación) — no se debe pisar eso
            // después con un texto de botón "original" capturado antes del envío.
            form.dispatchEvent(new CustomEvent('ajax:success', { detail: payload }));
            showModuleToast('Listo', 'Los cambios se guardaron correctamente.');
        } catch {
            showModuleToast('Sin conexión', 'No se pudo completar la acción. Intenta nuevamente.');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    });
}
document.querySelectorAll('.ajax-form').forEach(bindAjaxForm);

// Si un administrador cambia la contraseña, el usuario recibe el aviso y se
// cierra esta sesión en pocos segundos, incluso si permanece en otra pantalla.
if (document.querySelector('meta[name="csrf-token"]')) {
    setInterval(async () => {
        try {
            const response = await fetch('/api/sesion/estado', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (response.status !== 409) return;
            const payload = await response.json().catch(() => ({}));
            showModuleToast('Contraseña actualizada', payload.message || 'Tu sesión será cerrada por seguridad.');
            setTimeout(() => window.location.assign('/login'), 1300);
        } catch { /* La comprobación no debe afectar el uso normal de la página. */ }
    }, 8000);
}

// Al marcar una acción, su módulo padre queda habilitado automáticamente.
document.addEventListener('change', event => {
    const action = event.target.closest('.module-actions input[type="checkbox"]');
    if (action?.checked) {
        const module = action.closest('.module-permission-card')?.querySelector('.module-toggle input[type="checkbox"]');
        if (module) module.checked = true;
    }
});

// --- Turnos de profesionales: el formulario de arriba sirve tanto para crear como editar ---
const refreshCaptchaButton = document.getElementById('refreshCaptcha');
refreshCaptchaButton?.addEventListener('click', async () => {
    refreshCaptchaButton.disabled = true;
    try {
        const response = await fetch(refreshCaptchaButton.dataset.url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (!response.ok) throw new Error('captcha');
        const payload = await response.json();
        document.getElementById('captchaCode').textContent = payload.code;
        document.querySelector('input[name="captcha"]')?.focus();
    } catch {
        alert('No se pudo generar otro código. Inténtalo nuevamente.');
    } finally {
        refreshCaptchaButton.disabled = false;
    }
});

const scheduleForm = document.getElementById('scheduleForm');
if (scheduleForm) {
    const methodField = document.getElementById('scheduleFormMethod');
    const submitBtn = document.getElementById('scheduleFormSubmit');
    const cancelBtn = document.getElementById('scheduleFormCancel');
    const createUrl = scheduleForm.dataset.createUrl;
    const updateTemplate = scheduleForm.dataset.updateUrlTemplate;
    const syncSelectLabels = () => scheduleForm.querySelectorAll('select').forEach(s => s.dispatchEvent(new Event('change', { bubbles: true })));
    const setCreateMode = () => {
        scheduleForm.action = createUrl;
        methodField.value = '';
        submitBtn.textContent = 'Registrar turno';
        cancelBtn.hidden = true;
        scheduleForm.reset();
        syncSelectLabels();
    };
    document.getElementById('schedulesTableBody')?.addEventListener('click', event => {
        const button = event.target.closest('[data-edit-schedule]');
        if (!button) return;
        const row = button.closest('[data-row-id]');
        scheduleForm.action = updateTemplate.replace('__ID__', row.dataset.rowId);
        methodField.value = 'PUT';
        submitBtn.textContent = 'Guardar cambios';
        cancelBtn.hidden = false;
        scheduleForm.elements.professional_id.value = row.dataset.professionalId || '';
        scheduleForm.elements.service_id.value = row.dataset.serviceId || '';
        scheduleForm.elements.service_area_id.value = row.dataset.serviceAreaId || '';
        scheduleForm.elements.work_shift_id.value = row.dataset.workShiftId || '';
        scheduleForm.elements.scheduled_date.value = row.dataset.scheduledDate || '';
        scheduleForm.elements.notes.value = row.dataset.notes || '';
        syncSelectLabels();
        scheduleForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    cancelBtn?.addEventListener('click', setCreateMode);
    scheduleForm.addEventListener('ajax:success', () => { if (methodField.value === 'PUT') setCreateMode(); });
}

// --- Canales en vivo (Reverb/Echo): solo se suscribe si la pantalla tiene el contenedor correspondiente ---
if (window.Echo) {
    const onRowUpdated = data => {
        (data.targets || []).forEach(applyRowUpdate);
        if ((data.targets || []).length) showModuleToast('Información actualizada', 'Los cambios se aplicaron en tiempo real.');
        else refreshPortalDataInPlace('Se recibió una actualización en tiempo real.');
    };
    if (document.getElementById('patientsTableBody') || document.getElementById('serviceTableBody')) {
        window.Echo.private('citas').listen('.row.updated', onRowUpdated);
    }
    if (document.getElementById('schedulesTableBody')) {
        window.Echo.private('admin.schedules').listen('.row.updated', onRowUpdated);
    }
    if (document.querySelector('.role-list')) {
        window.Echo.private('admin.roles').listen('.row.updated', onRowUpdated);
    }
    if (document.getElementById('professionalsList')) {
        window.Echo.private('admin.professionals').listen('.row.updated', onRowUpdated);
    }
    if (document.getElementById('usersList')) {
        window.Echo.private('admin.users').listen('.row.updated', onRowUpdated);
    }
}

if (document.querySelector('form[action$="/logout"]')) {
    const checkSessionStatus = async () => {
        try {
            const response = await fetch('/api/sesion/estado', { headers: { Accept: 'application/json' } });
            if (response.status !== 409) return;
            const payload = await response.json().catch(() => null);
            clearInterval(sessionStatusTimer);
            alert(payload?.message || 'Tu sesión se cerró. Inicia sesión nuevamente.');
            window.location.href = '/login';
        } catch { /* sin conexión: se reintenta en el siguiente sondeo */ }
    };
    const sessionStatusTimer = setInterval(checkSessionStatus, 20000);
}

// Registro del service worker mínimo: habilita instalar la app y los atajos
// del ícono en Android. No cachea nada (ver public/sw.js).
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

// --- Aviso de nueva versión: detecta cambios en el código del servidor sin
// que nadie tenga que "subir la versión" a mano (ver App\Support\AppVersion) ---
const appVersionMeta = document.querySelector('meta[name="app-version"]');
if (appVersionMeta) {
    const currentAppVersion = appVersionMeta.content;
    const updateBanner = document.createElement('div');
    updateBanner.className = 'update-banner';
    updateBanner.innerHTML = '<span class="update-dot"></span><div><strong>Hay una actualización disponible</strong><small>Recarga la página para ver los últimos cambios.</small></div><button type="button" class="update-reload">Recargar</button><button type="button" class="update-dismiss" aria-label="Cerrar">×</button>';
    document.body.appendChild(updateBanner);
    let updateDismissed = false;
    updateBanner.querySelector('.update-reload').addEventListener('click', () => window.location.reload());
    updateBanner.querySelector('.update-dismiss').addEventListener('click', () => {
        updateDismissed = true;
        updateBanner.classList.remove('visible');
    });
    const checkAppVersion = async () => {
        if (updateDismissed || updateBanner.classList.contains('visible')) return;
        try {
            const response = await fetch('/api/version', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) return;
            const { version } = await response.json();
            if (version && version !== currentAppVersion) updateBanner.classList.add('visible');
        } catch { /* se reintenta en el siguiente ciclo */ }
    };
    setInterval(checkAppVersion, 30000);
}
