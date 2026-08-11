import './bootstrap';
import { Passkeys } from '@laravel/passkeys';

// Angular se descarga solo en Portería; los demás módulos no cargan su peso.
if (document.querySelector('.porter-search')) import('./angular/patient-access');
if (document.querySelector('.quick-actions')) import('./angular/admin-dashboard');

const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
const normalizeSearch = value => String(value ?? '').toLocaleLowerCase('es').normalize('NFD').replace(/[̀-ͯ]/g, '');

// Convierte un <select> con muchas opciones en un combo con buscador, sin tocar
// el <select> original (sigue enviándose igual en el formulario y respeta
// required/validación nativa; solo se oculta visualmente).
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

document.querySelectorAll('.confirm-delete-form').forEach(form => form.addEventListener('submit', event => {
    if (!confirm(form.dataset.confirmMessage)) event.preventDefault();
}));

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
const hideLoader = () => pageLoader.classList.remove('visible');
const showLoader = (message = 'Cargando sistema...') => {
    pageLoader.querySelector('span').textContent = message;
    pageLoader.classList.add('visible');
};
window.addEventListener('load', hideLoader);
window.addEventListener('pageshow', hideLoader);
setTimeout(hideLoader, 900);
document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || link.target === '_blank' || link.hasAttribute('download')) return;
    const target = new URL(link.href, window.location.href);
    if (target.origin === window.location.origin && target.href !== window.location.href && !target.hash) showLoader('Cambiando de módulo...');
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
const themeToggle = document.createElement('button');
themeToggle.type = 'button';
themeToggle.id = 'themeToggle';
themeToggle.className = 'theme-toggle';
document.body.appendChild(themeToggle);
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
                <label>Nombre del visitante<input name="name" required maxlength="100" placeholder="Nombres y apellidos"></label>
                <label>DNI<input name="dni" required inputmode="numeric" pattern="[0-9]{8}" maxlength="8" placeholder="8 digitos"></label>
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
    visitToggle.setAttribute('aria-expanded', 'true');
    visitToggle.setAttribute('aria-label', 'Ocultar detalles de visitas hospitalarias');
    visitToggle.innerHTML = '<span>Ocultar detalles</span><i>⌃</i>';
    visitTitle.appendChild(visitToggle);
    const visitContent = document.createElement('div');
    visitContent.className = 'visit-demo-content';
    while (visitTitle.nextElementSibling) visitContent.appendChild(visitTitle.nextElementSibling);
    visitDemo.appendChild(visitContent);
    visitToggle.addEventListener('click', () => {
        const minimized = !visitContent.hidden;
        visitContent.hidden = minimized;
        visitToggle.innerHTML = minimized ? '<span>Mostrar detalles</span><i>⌄</i>' : '<span>Ocultar detalles</span><i>⌃</i>';
        visitToggle.setAttribute('aria-expanded', String(!minimized));
        visitToggle.setAttribute('aria-label', minimized ? 'Mostrar detalles de visitas hospitalarias' : 'Ocultar detalles de visitas hospitalarias');
    });
    porterPanel.insertAdjacentElement('afterend', visitDemo);
    const visitForm = visitDemo.querySelector('#visitDemoForm');
    const visitDniInput = visitForm.elements.dni;
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
    const hospitalPatients = {
        maria: { initials: 'MF', name: 'Maria Flores Gomez', detail: 'Hospitalizacion · Cama 12 · Medicina' },
        carlos: { initials: 'CR', name: 'Carlos Ruiz Chunga', detail: 'Hospitalizacion · Cama 08 · Cirugia' },
        ana: { initials: 'AT', name: 'Ana Torres Vilela', detail: 'Hospitalizacion · Cama 04 · Pediatria' },
    };
    const validVisitorDni = value => {
        const dni = String(value || '').replace(/[^0-9]/g, '');
        return /^[0-9]{8}$/.test(dni) && !/^(\d)\1{7}$/.test(dni);
    };
    visitDniInput.addEventListener('input', () => {
        const dni = visitDniInput.value.replace(/[^0-9]/g, '');
        visitDniInput.value = dni;
        const incomplete = dni.length < 8;
        const valid = validVisitorDni(dni);
        visitDniInput.setCustomValidity(incomplete || valid ? '' : 'Ingresa un DNI valido de 8 digitos.');
        visitDniInput.classList.toggle('is-invalid', !incomplete && !valid);
    });
    const updateVisitPatient = () => {
        const patient = hospitalPatients[visitPatientSelect.value];
        visitPatientCard.querySelector('span').textContent = patient.initials;
        visitPatientCard.querySelector('strong').textContent = patient.name;
        visitPatientCard.querySelector('small').textContent = patient.detail;
    };
    visitPatientSelect.addEventListener('change', updateVisitPatient);
    visitForm.addEventListener('submit', event => {
        event.preventDefault();
        const values = new FormData(visitForm);
        const name = String(values.get('name') || '').trim();
        const dni = String(values.get('dni') || '').replace(/[^0-9]/g, '');
        const relationship = String(values.get('relationship') || '').trim();
        const patient = hospitalPatients[visitPatientSelect.value];
        if (!name) { showModuleToast('Nombre requerido', 'Ingresa el nombre completo del visitante.'); return; }
        if (!validVisitorDni(dni)) { showModuleToast('DNI invalido', 'Ingresa un DNI valido de 8 digitos.'); return; }
        if (!relationship) { showModuleToast('Parentesco requerido', 'Selecciona el parentesco del visitante.'); return; }
        const activeVisit = [...visitRows.querySelectorAll('.visit-row:not(.visit-exited)')]
            .find(row => row.dataset.visitorDni === dni);
        if (activeVisit) {
            showModuleToast('Visita duplicada', 'Este DNI ya tiene una visita activa. Registra su salida antes de ingresarlo nuevamente.');
            activeVisit.classList.add('highlight-flash');
            setTimeout(() => activeVisit.classList.remove('highlight-flash'), 1200);
            return;
        }
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
        visitDniInput.setCustomValidity('');
        visitDniInput.classList.remove('is-invalid');
        showModuleToast('Entrada registrada', 'El familiar aparece vinculado al paciente hospitalizado.');
    });
}

if (liveClock) {
    const updateClock = () => { liveClock.textContent = new Date().toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }); };
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
document.querySelectorAll('.moduleAction').forEach(btn => btn.addEventListener('click', () => btn.dataset.url ? window.location.href = btn.dataset.url : showModuleToast('Función disponible', 'El módulo está listo para conectar con la base de datos.')));

const attendanceModal = document.getElementById('attendanceModal');
if (attendanceModal) {
    const attendanceTitle = document.getElementById('attendanceModalTitle');
    const attendanceName = document.getElementById('attendanceModalName');
    const attendanceSub = document.getElementById('attendanceModalSub');
    const attendanceYes = document.getElementById('attendanceModalYes');
    let pendingAttendanceForm = null;
    const closeAttendanceModal = () => { attendanceModal.hidden = true; pendingAttendanceForm = null; };
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
        attendanceModal.hidden = false;
    });
    attendanceYes.addEventListener('click', () => { pendingAttendanceForm?.requestSubmit(); closeAttendanceModal(); });
    document.getElementById('attendanceModalNo')?.addEventListener('click', closeAttendanceModal);
    document.getElementById('attendanceModalClose')?.addEventListener('click', closeAttendanceModal);
    attendanceModal.addEventListener('click', event => { if (event.target === attendanceModal) closeAttendanceModal(); });
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
    const now = new Date().toLocaleTimeString('es-PE', {hour:'2-digit', minute:'2-digit'});
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
if (document.getElementById('currentTime')) document.getElementById('currentTime').textContent = new Date().toLocaleTimeString('es-PE', {hour:'2-digit',minute:'2-digit'});

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
userMenuButton?.addEventListener('click', event => {
    event.stopPropagation();
    userDropdown.hidden = !userDropdown.hidden;
    userMenuButton.setAttribute('aria-expanded', String(!userDropdown.hidden));
});
document.addEventListener('click', event => {
    if (userDropdown && !userDropdown.hidden && !event.target.closest('.user-menu')) {
        userDropdown.hidden = true;
        userMenuButton?.setAttribute('aria-expanded', 'false');
    }
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
    const closeNotifications = () => { notificationsPanel.hidden = true; };
    const renderNotifications = notifications => {
        const body = notificationsPanel.querySelector('.notifications-body');
        body.innerHTML = notifications.length
            ? notifications.map(item => `<article><span>●</span><div><strong>${escapeHtml(item.patient)}</strong><small>${escapeHtml(item.service)} · ${escapeHtml(item.time)}</small></div></article>`).join('')
            : '<p class="notifications-empty">No hay pacientes próximos por atender.</p>';
    };
    notifBell.addEventListener('click', async () => {
        const isOpening = notificationsPanel.hidden;
        notificationsPanel.hidden = !isOpening;
        if (!isOpening) return;
        try {
            const response = await fetch('/api/portero/notificaciones', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error();
            const payload = await response.json();
            renderNotifications(payload.notifications || []);
        } catch {
            notificationsPanel.querySelector('.notifications-body').innerHTML = '<p class="notifications-empty">No se pudieron cargar las notificaciones.</p>';
        }
    });
    notificationsPanel.querySelector('button').addEventListener('click', closeNotifications);
    document.addEventListener('click', event => { if (!event.target.closest('.notif-bell, .notifications-popover')) closeNotifications(); });
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
        const cards=data.appointments.map(a=>`<article><div class="appointment-date"><strong>${escapeHtml(a.time)}</strong><small>${escapeHtml(a.date)}</small></div><div><span>${escapeHtml(a.service)}</span><h3>${escapeHtml(a.type)}</h3><p>${escapeHtml(a.location||'Ubicación por confirmar')}</p>${a.preparation?`<small class="prep">Preparación: ${escapeHtml(a.preparation)}</small>`:''}</div><b>${escapeHtml(a.status)}</b></article>`).join('')||'<div class="portal-empty">El paciente no tiene citas vigentes.</div>';
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
setInterval(() => {
    const dni = document.getElementById('portalDni');
    const result = document.getElementById('portalPatientResult');
    if (dni?.value.length === 8 && result && !result.hidden) document.getElementById('portalSearch')?.click();
}, 5000);

const scanModalElement = document.getElementById('scanModal');
if (scanModalElement) {
    const scanModal = document.getElementById('scanModal');
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
    document.getElementById('scanModalClose')?.addEventListener('click', stopScan);
    scanModal.addEventListener('click', event => { if (event.target === scanModal) stopScan(); });
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
        if (event.defaultPrevented) return; // ej. cancelado por confirm() en .confirm-delete-form
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
            return;
        }
        event.preventDefault();
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

// --- Cierre forzado de sesión: si la contraseña cambió (desde este mismo
// dispositivo en otra pestaña, o porque un admin la restablece a futuro), el
// resto de sesiones abiertas se enteran en el siguiente sondeo y salen solas
// en vez de seguir "adentro" con una contraseña que ya no es válida.
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
