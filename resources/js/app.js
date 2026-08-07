import './bootstrap';
import { Passkeys } from '@laravel/passkeys';

const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);

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
const hideLoader = () => requestAnimationFrame(() => requestAnimationFrame(() => pageLoader.classList.remove('visible')));
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
document.querySelectorAll('.moduleAction').forEach(btn => btn.addEventListener('click', () => btn.dataset.url ? window.location.href = btn.dataset.url : showModuleToast('Función disponible', 'El módulo está listo para conectar con la base de datos.')));

const attendanceModal = document.getElementById('attendanceModal');
if (attendanceModal) {
    const attendanceTitle = document.getElementById('attendanceModalTitle');
    const attendanceName = document.getElementById('attendanceModalName');
    const attendanceSub = document.getElementById('attendanceModalSub');
    const attendanceYes = document.getElementById('attendanceModalYes');
    let pendingAttendanceForm = null;
    const closeAttendanceModal = () => { attendanceModal.hidden = true; pendingAttendanceForm = null; };
    document.querySelectorAll('[data-attendance-action]').forEach(button => button.addEventListener('click', () => {
        pendingAttendanceForm = document.getElementById(button.dataset.formTarget);
        const isNoShow = button.dataset.attendanceAction === 'no-asistio';
        attendanceTitle.textContent = isNoShow ? '¿Confirma que el paciente no asistió?' : '¿Desea tomar asistencia?';
        attendanceYes.textContent = isNoShow ? 'Sí, marcar falta' : 'Sí, confirmar';
        attendanceYes.classList.toggle('danger', isNoShow);
        attendanceName.textContent = button.dataset.patientName || '';
        attendanceSub.textContent = button.dataset.patientMeta || '';
        attendanceModal.hidden = false;
    }));
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

document.getElementById('roleMenu')?.addEventListener('click', () => {
    const sidebar = document.querySelector('.role-sidebar');
    sidebar?.classList.toggle('open');
    document.body.classList.toggle('menu-open', sidebar?.classList.contains('open'));
});
document.addEventListener('click', event => {
    const sidebar = document.querySelector('.role-sidebar');
    if (window.innerWidth <= 760 && sidebar?.classList.contains('open') && !event.target.closest('.role-sidebar') && !event.target.closest('#roleMenu')) {
        sidebar.classList.remove('open');
        document.body.classList.remove('menu-open');
    }
});
const userMenuButton = document.getElementById('userMenuButton');
const roleSidebar = document.querySelector('.role-sidebar');
if (roleSidebar) {
    const collapseButton = document.createElement('button');
    collapseButton.type = 'button';
    collapseButton.className = 'sidebar-collapse';
    collapseButton.innerHTML = '<span>‹</span>';
    roleSidebar.appendChild(collapseButton);
    const setCollapsed = collapsed => {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        collapseButton.innerHTML = `<span>${collapsed ? '›' : '‹'}</span>`;
        collapseButton.setAttribute('aria-label', collapsed ? 'Mostrar menú lateral' : 'Ocultar menú lateral');
    };
    if (window.innerWidth > 760) setCollapsed(localStorage.getItem('hospital-sidebar') === 'collapsed');
    collapseButton.addEventListener('click', () => {
        if (window.innerWidth <= 760) {
            roleSidebar.classList.remove('open');
            document.body.classList.remove('menu-open');
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
document.getElementById('patientsDateFilter')?.addEventListener('change', event => event.currentTarget.requestSubmit());
const patientsSearch = document.getElementById('patientsSearch');
if (patientsSearch) {
    const patientsServiceFilter = document.getElementById('patientsServiceFilter');
    const patientsStatusFilter = document.getElementById('patientsStatusFilter');
    const patientsRows = [...document.querySelectorAll('#patientsTableBody tr[data-search]')];
    const patientsEmpty = document.getElementById('patientsFilterEmpty');
    const applyPatientFilters = () => {
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
const notifBell = document.getElementById('notifBell');
if (notifBell) {
    const notifBadge = document.getElementById('notifBadge');
    const notifTarget = document.querySelector(notifBell.dataset.target);
    notifBell.addEventListener('click', () => {
        notifTarget?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        notifTarget?.classList.add('highlight-flash');
        setTimeout(() => notifTarget?.classList.remove('highlight-flash'), 1200);
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
                if (count > lastKnownCount) window.location.reload();
                lastKnownCount = count;
            }
        } catch { /* red intermitente: se reintenta en el siguiente ciclo */ }
    };
    setInterval(pollPending, 20000);
}
document.getElementById('portalSearch')?.addEventListener('click',async()=>{
    const dni=document.getElementById('portalDni').value; const result=document.getElementById('portalPatientResult'); const button=document.getElementById('portalSearch');
    if(!/^\d{8}$/.test(dni)){result.hidden=false;result.innerHTML='<div class="portal-empty">Ingresa un DNI válido de 8 dígitos.</div>';return;}
    button.disabled=true;button.textContent='Buscando...';
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

const scanBtn = document.getElementById('portalScanBtn');
if (scanBtn) {
    const scanModal = document.getElementById('scanModal');
    const scanVideo = document.getElementById('scanVideo');
    const scanHint = document.getElementById('scanHint');
    let scanStream = null;
    let scanRafId = null;

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
        scanModal.hidden = false;
        if (!('BarcodeDetector' in window)) {
            scanHint.textContent = 'Tu navegador no soporta escaneo por cámara. Usa un lector USB/Bluetooth o ingresa el DNI manualmente.';
            return;
        }
        scanHint.textContent = 'Apunta la cámara al código de barras.';
        try {
            scanStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            scanVideo.srcObject = scanStream;
            const detector = new BarcodeDetector({ formats: ['code_128', 'code_39', 'code_93', 'codabar', 'ean_13', 'ean_8', 'itf', 'pdf417', 'qr_code'] });
            scanRafId = requestAnimationFrame(() => scanFrame(detector));
        } catch (error) {
            scanHint.textContent = 'No se pudo acceder a la cámara. Revisa los permisos del navegador.';
        }
    };

    scanBtn.addEventListener('click', startScan);
    document.getElementById('scanModalClose')?.addEventListener('click', stopScan);
    scanModal.addEventListener('click', event => { if (event.target === scanModal) stopScan(); });
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
