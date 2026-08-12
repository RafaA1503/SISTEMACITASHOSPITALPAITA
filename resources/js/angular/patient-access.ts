import '@angular/compiler';
import { CommonModule } from '@angular/common';
import { Component, OnDestroy, signal } from '@angular/core';
import { bootstrapApplication } from '@angular/platform-browser';

type Appointment = {
    code: string;
    date: string;
    time: string;
    type: string;
    service: string;
    location?: string;
    status: string;
    preparation?: string;
};

type PatientResult = {
    patient: { dni: string; name: string; insurance?: string };
    appointments: Appointment[];
};

@Component({
    standalone: true,
    selector: 'hospital-patient-access',
    imports: [CommonModule],
    template: `
      <section class="portal-panel porter-search">
        <div class="panel-title">
          <div><h2>Buscar paciente</h2><p>Consulta por DNI y recibe sus citas actualizadas en tiempo real.</p></div>
          <span class="secure-badge">● Consulta segura</span>
        </div>
        <div class="porter-form">
          <label><span>DNI DEL PACIENTE</span><div>
            <input id="portalDni" maxlength="8" inputmode="numeric" autocomplete="off" autofocus
              placeholder="Ingresa los 8 dígitos o escanea el código de barras"
              [value]="dni()" (input)="onDniInput($any($event.target).value)">
            <button type="button" id="portalScanBtn" class="scan-btn" title="Escanear código de barras" aria-label="Escanear código de barras">⌁</button>
            <button type="button" [disabled]="loading()" (click)="search()">{{ loading() ? 'Buscando…' : 'Buscar citas' }}</button>
          </div></label>
        </div>
        <div class="portal-patient" *ngIf="error()"><div class="portal-empty">{{ error() }}</div></div>
        <div class="portal-patient" *ngIf="result() as data">
          <div class="patient-summary"><div class="avatar">{{ initials(data.patient.name) }}</div><div><strong>{{ data.patient.name }}</strong><p>DNI {{ data.patient.dni }} · Seguro {{ data.patient.insurance || 'No registrado' }}</p></div><span>{{ data.appointments.length }} cita(s) vigente(s)</span></div>
          <div class="appointment-cards">
            <article *ngFor="let appointment of data.appointments"><div class="appointment-date"><strong>{{ appointment.time }}</strong><small>{{ appointment.date }}</small></div><div><span>{{ appointment.service }}</span><h3>{{ appointment.type }}</h3><p>{{ appointment.location || 'Ubicación por confirmar' }}</p><small class="prep" *ngIf="appointment.preparation">Preparación: {{ appointment.preparation }}</small></div><b>{{ appointment.status.replace('_', ' ') }}</b></article>
          </div>
        </div>
      </section>
    `,
})
class PatientAccessComponent implements OnDestroy {
    readonly dni = signal('');
    readonly loading = signal(false);
    readonly error = signal('');
    readonly result = signal<PatientResult | null>(null);
    private refreshTimer = window.setInterval(() => {
        if (this.dni().length === 8 && this.result()) this.search(true);
    }, 5000);

    onDniInput(value: string): void {
        const dni = value.replace(/\D/g, '').slice(0, 8);
        this.dni.set(dni);
        this.error.set('');
        if (dni.length < 8) this.result.set(null);
        if (dni.length === 8) this.search();
    }

    async search(silent = false): Promise<void> {
        if (this.dni().length !== 8 || this.loading()) return;
        this.loading.set(true);
        if (!silent) this.error.set('');
        try {
            const response = await fetch(`/api/pacientes/${this.dni()}/citas`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'No fue posible realizar la búsqueda.');
            this.result.set(data);
        } catch (error) {
            if (!silent) this.error.set(error instanceof Error ? error.message : 'No fue posible realizar la búsqueda.');
        } finally {
            this.loading.set(false);
        }
    }

    initials(name: string): string {
        return name.split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('').toUpperCase();
    }

    ngOnDestroy(): void {
        window.clearInterval(this.refreshTimer);
    }
}

const legacyPanel = document.querySelector('.porter-search');
if (legacyPanel) {
    const host = document.createElement('hospital-patient-access');
    host.id = 'angularPatientSearch';
    // Solo se reemplaza el panel original (con el DNI, el botón de escanear y
    // "Buscar citas" ya funcionando) si Angular realmente logra iniciar. Si el
    // bootstrap falla — como pasa hoy porque el CSP bloquea eval() y Angular
    // necesita el compilador JIT — el panel original se queda intacto en vez
    // de desaparecer y dejar la búsqueda de pacientes sin nada funcional.
    bootstrapApplication(PatientAccessComponent)
        .then(() => legacyPanel.replaceWith(host))
        .catch(error => console.error('No se pudo iniciar el módulo Angular de pacientes; se mantiene el panel original.', error));
}
