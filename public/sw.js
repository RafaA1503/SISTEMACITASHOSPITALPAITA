// Service worker mínimo, solo para que Android permita instalar la app y
// mostrar los atajos del ícono. A propósito NO cachea nada: esta app muestra
// datos en vivo (citas, visitas, WebSockets) y cachear respuestas viejas
// causaría errores de datos desactualizados.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {
    // Sin intercepción: cada petición va siempre a la red, tal cual.
});
