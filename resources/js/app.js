import '@fontsource-variable/inter';
import 'bootstrap-icons/font/bootstrap-icons.min.css';
import '../scss/app.scss'; // Bootstrap personalizado + estilos propios (Fase 9)
import * as bootstrap from 'bootstrap';
import './bootstrap';
import { iniciarCamposAdicionales } from './campos-adicionales';
import { iniciarFlujoDocumental } from './flujo-documental';
import { iniciarPortal } from './portal';
import { iniciarNotificaciones } from './notificaciones';
import { iniciarRelacionesDocumentales } from './relaciones-documentales';

window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    iniciarCamposAdicionales();
    iniciarFlujoDocumental();
    iniciarPortal();
    iniciarNotificaciones();
    iniciarRelacionesDocumentales();

    // Confirmación para acciones sensibles: <form data-confirmar="¿Seguro?">
    document.querySelectorAll('form[data-confirmar]').forEach((form) => {
        form.addEventListener('submit', (evento) => {
            if (!window.confirm(form.dataset.confirmar)) {
                evento.preventDefault();
            }
        });
    });

    // Evita el doble envío: al enviar un formulario POST se desactivan sus botones (corrección 13/11/2026).
    // Se registra en el documento para ejecutarse después de la confirmación; si el usuario la cancela
    // (o el formulario lo maneja JavaScript), el evento ya viene cancelado y no se desactiva nada.
    document.addEventListener('submit', (evento) => {
        const form = evento.target;
        if (evento.defaultPrevented || form.method.toLowerCase() === 'get') return;
        setTimeout(() => {
            form.querySelectorAll('button[type="submit"], button:not([type])').forEach((boton) => {
                boton.disabled = true;
            });
        }, 0);
    });

    // Mostrar u ocultar contraseña: <button data-alternar-contrasena="#id">
    document.querySelectorAll('[data-alternar-contrasena]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const campo = document.querySelector(boton.dataset.alternarContrasena);
            const visible = campo.type === 'text';
            campo.type = visible ? 'password' : 'text';
            boton.querySelector('i')?.classList.toggle('bi-eye', visible);
            boton.querySelector('i')?.classList.toggle('bi-eye-slash', !visible);
            boton.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
        });
    });
});
