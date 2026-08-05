import './bootstrap';

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
const showModuleToast = (title = 'Acción completada', message = 'Los cambios se guardaron correctamente.') => {
    const toast = document.getElementById('moduleToast'); if(!toast) return;
    toast.querySelector('strong').textContent = title; toast.querySelector('p').textContent = message;
    toast.classList.add('show'); setTimeout(() => toast.classList.remove('show'), 2800);
};
document.querySelectorAll('.moduleAction').forEach(btn => btn.addEventListener('click', () => btn.dataset.url ? window.location.href = btn.dataset.url : showModuleToast('Función disponible', 'El módulo está listo para conectar con la base de datos.')));
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
if (document.getElementById('currentTime')) document.getElementById('currentTime').textContent = new Date().toLocaleTimeString('es-PE', {hour:'2-digit',minute:'2-digit'});

document.getElementById('scanDni')?.addEventListener('click',async()=>{
    if(!('BarcodeDetector' in window)){showModuleToast('Escáner no compatible','Escribe el DNI y pulsa Consultar.');return;}
    let stream; const overlay=document.createElement('div'); overlay.className='scanner-overlay'; overlay.innerHTML='<section><button type="button">×</button><h2>Escanear DNI</h2><p>Coloca el código del documento dentro del recuadro.</p><div class="camera-frame"><video autoplay playsinline></video><i></i></div><small>La cámara se cerrará al detectar 8 dígitos.</small></section>'; document.body.appendChild(overlay);
    const video=overlay.querySelector('video'); let active=true; const close=()=>{active=false;stream?.getTracks().forEach(track=>track.stop());overlay.remove();}; overlay.querySelector('button').addEventListener('click',close);
    try { stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}}}); video.srcObject=stream; const detector=new BarcodeDetector({formats:['qr_code','pdf417','code_128','code_39']});
        const detect=async()=>{if(!active)return;try{for(const code of await detector.detect(video)){const match=code.rawValue.match(/\b\d{8}\b/);if(match){accessDni.value=match[0];close();await lookupDni();return;}}}catch{}requestAnimationFrame(detect);};detect();
    } catch {close();showModuleToast('No se pudo abrir la cámara','Revisa el permiso de cámara o escribe el DNI manualmente.');}
});

document.getElementById('roleMenu')?.addEventListener('click',()=>document.querySelector('.role-sidebar')?.classList.toggle('open'));
document.getElementById('portalSearch')?.addEventListener('click',async()=>{
    const dni=document.getElementById('portalDni').value; const result=document.getElementById('portalPatientResult'); const button=document.getElementById('portalSearch');
    if(!/^\d{8}$/.test(dni)){result.hidden=false;result.innerHTML='<div class="portal-empty">Ingresa un DNI válido de 8 dígitos.</div>';return;}
    button.disabled=true;button.textContent='Buscando...';
    try{const response=await fetch(`/api/pacientes/${dni}/citas`,{headers:{Accept:'application/json'}});const data=await response.json();if(!response.ok)throw new Error(data.message);
        result.hidden=false;result.innerHTML=`<div class="patient-summary"><div class="avatar">${data.patient.name.split(/\s+/).slice(0,2).map(x=>x[0]).join('')}</div><div><strong>${data.patient.name}</strong><p>DNI ${data.patient.dni} · Seguro ${data.patient.insurance||'No registrado'}</p></div><span>${data.appointments.length} cita(s) vigente(s)</span></div><div class="appointment-cards">${data.appointments.map(a=>`<article><div class="appointment-date"><strong>${a.time}</strong><small>${a.date}</small></div><div><span>${a.service}</span><h3>${a.type}</h3><p>${a.location||'Ubicación por confirmar'}</p>${a.preparation?`<small class="prep">Preparación: ${a.preparation}</small>`:''}</div><b>${a.status}</b></article>`).join('')||'<div class="portal-empty">El paciente no tiene citas vigentes.</div>'}</div>`;
    }catch(error){result.hidden=false;result.innerHTML=`<div class="portal-empty">${error.message||'No fue posible realizar la búsqueda.'}</div>`;}finally{button.disabled=false;button.textContent='Buscar citas';}
});
