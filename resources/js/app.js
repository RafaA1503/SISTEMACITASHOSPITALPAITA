import './bootstrap';

const rows = window.appointments || [];
const body = document.getElementById('appointmentsBody');
const search = document.getElementById('searchInput');
const examFilter = document.getElementById('examFilter');
const statusFilter = document.getElementById('statusFilter');
const modal = document.getElementById('detailModal');
let selected = null;

const statusClass = status => status.toLowerCase().replaceAll(' ', '-').replace('ó','o');
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
[search, examFilter, statusFilter].forEach(el => el.addEventListener('input', render));
document.getElementById('clearFilters').addEventListener('click', () => { search.value=''; examFilter.value=''; statusFilter.value=''; render(); });
document.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', () => modal.hidden = true));
modal.addEventListener('click', e => { if(e.target === modal) modal.hidden = true; });
document.addEventListener('keydown', e => { if(e.key === 'Escape') modal.hidden = true; });
document.getElementById('confirmEntry').addEventListener('click', () => {
    if(!selected) return;
    selected.status = 'Ingresó'; modal.hidden = true; render();
    const toast = document.getElementById('toast'); toast.classList.add('show'); setTimeout(() => toast.classList.remove('show'), 3000);
});
document.getElementById('scanBtn').addEventListener('click', () => { search.focus(); search.placeholder = 'Ingresa o escanea el código de la cita...'; });
document.getElementById('menuBtn').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));
render();
