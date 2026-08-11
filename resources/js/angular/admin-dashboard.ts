import '@angular/compiler';
import { Component, inject } from '@angular/core';
import { bootstrapApplication } from '@angular/platform-browser';

/**
 * Angular se integra sobre el tablero que Laravel ya entregó. De este modo el
 * panel no se desmonta ni vuelve a pintarse después del preloader.
 */
@Component({
    standalone: true,
    selector: 'hospital-admin-enhancer',
    template: '',
})
class AdminDashboardEnhancerComponent {
    private readonly host = inject(HTMLElement);

    ngOnInit() {
        const dashboard = this.host.previousElementSibling;
        if (!(dashboard instanceof HTMLElement) || !dashboard.matches('.quick-actions')) return;

        dashboard.setAttribute('aria-label', 'Accesos de administracion');
        dashboard.querySelectorAll<HTMLElement>('.moduleAction').forEach(action => {
            action.setAttribute('aria-live', 'polite');
        });
    }
}

const legacyDashboard = document.querySelector('.quick-actions');

if (legacyDashboard) {
    const host = document.createElement('hospital-admin-enhancer');
    legacyDashboard.after(host);
    bootstrapApplication(AdminDashboardEnhancerComponent).catch(error => {
        console.error('No se pudo iniciar el apoyo de administracion.', error);
    });
}
