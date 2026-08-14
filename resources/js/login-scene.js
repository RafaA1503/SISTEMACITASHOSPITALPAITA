// Halo 3D decorativo alrededor del logo en la pantalla de login. Vive en su
// propio entry de Vite para no pesar en el resto de la app (solo se carga aquí).
// Movimiento calibrado "estilo Apple": entrada suave con easing expo, giro que
// respira (acelera/desacelera) en vez de una vuelta constante y mecánica.
import * as THREE from 'three';

const easeOutExpo = t => (t >= 1 ? 1 : 1 - Math.pow(2, -10 * t));
const canvas = document.getElementById('loginScene');

if (canvas && window.WebGLRenderingContext && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(40, 1, 0.1, 100);
    camera.position.set(0, 0, 6.8);

    const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.15;

    scene.add(new THREE.AmbientLight(0xffffff, 0.45));
    const goldLight = new THREE.PointLight(0xf3d78a, 4, 25);
    goldLight.position.set(3, 2.5, 4);
    scene.add(goldLight);
    const blueLight = new THREE.PointLight(0x5c8dff, 2.4, 25);
    blueLight.position.set(-3.5, -2, 3.5);
    scene.add(blueLight);
    const rimLight = new THREE.PointLight(0xffffff, 1.6, 20);
    rimLight.position.set(0, 3.5, -3);
    scene.add(rimLight);

    // Grupo raíz: así podemos animar la entrada completa (escala + giro) con un solo easing.
    const rig = new THREE.Group();
    scene.add(rig);

    const glassMat = extra => new THREE.MeshPhysicalMaterial({
        metalness: 0.85, roughness: 0.15, clearcoat: 1, clearcoatRoughness: 0.1,
        transmission: 0.05, reflectivity: 0.6, ...extra,
    });

    const ring = new THREE.Mesh(new THREE.TorusGeometry(2.15, 0.05, 32, 128), glassMat({ color: 0xc9a33d, emissive: 0x4a3610, emissiveIntensity: 0.6 }));
    ring.rotation.x = Math.PI / 2.35;
    rig.add(ring);

    const ring2 = new THREE.Mesh(new THREE.TorusGeometry(1.82, 0.022, 32, 128), glassMat({ color: 0x6ea1ff, emissive: 0x0c1c40, emissiveIntensity: 0.5 }));
    ring2.rotation.x = Math.PI / 1.65;
    ring2.rotation.y = Math.PI / 5;
    rig.add(ring2);

    const particleGeo = new THREE.SphereGeometry(0.05, 12, 12);
    const particleCount = 16;
    for (let i = 0; i < particleCount; i++) {
        const gold = i % 2 === 0;
        const particle = new THREE.Mesh(particleGeo, glassMat({
            color: gold ? 0xf3d78a : 0x9dc0ff,
            emissive: gold ? 0xc9a33d : 0x3f6fd6,
            emissiveIntensity: 0.85,
            roughness: 0.05,
        }));
        const angle = (i / particleCount) * Math.PI * 2;
        const radius = 2.45 + Math.sin(i * 1.7) * 0.18;
        particle.position.set(Math.cos(angle) * radius, Math.sin(angle * 1.6) * 0.5, Math.sin(angle) * radius);
        rig.add(particle);
    }

    const resize = () => {
        const size = canvas.clientWidth || canvas.parentElement?.clientWidth || 220;
        renderer.setSize(size, size, false);
        camera.aspect = 1;
        camera.updateProjectionMatrix();
    };
    new ResizeObserver(resize).observe(canvas);
    resize();

    // Entrada: la pieza aparece "encogida" y gira hasta su posición final con
    // una curva ease-out-expo — el mismo tipo de aterrizaje suave que usa Apple
    // en sus reveals de producto, en vez de simplemente aparecer de golpe.
    const introDuration = 1.5;
    rig.scale.setScalar(0.4);
    canvas.style.opacity = '0';
    canvas.style.transition = 'opacity 1s ease';

    const clock = new THREE.Clock();
    let frameId = null;
    let introStarted = false;
    const animate = () => {
        const t = clock.getElapsedTime();
        if (!introStarted) { introStarted = true; requestAnimationFrame(() => { canvas.style.opacity = '1'; }); }

        const introT = easeOutExpo(Math.min(t / introDuration, 1));
        rig.scale.setScalar(0.4 + 0.6 * introT);
        rig.rotation.y = (1 - introT) * Math.PI * 0.6;

        // Giro que "respira": la velocidad oscila suavemente en vez de ser constante.
        const breathe = 1 + Math.sin(t * 0.35) * 0.5;
        ring.rotation.z += 0.0035 * breathe;
        ring2.rotation.z -= 0.005 * breathe;
        rig.rotation.x = Math.sin(t * 0.22) * 0.06;
        camera.position.y = Math.sin(t * 0.3) * 0.12;

        renderer.render(scene, camera);
        frameId = requestAnimationFrame(animate);
    };
    animate();

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            if (frameId) cancelAnimationFrame(frameId);
            frameId = null;
        } else if (!frameId) {
            animate();
        }
    });
}
