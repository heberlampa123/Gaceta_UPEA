import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.min.css';
import '../css/app.css'; // después de Bootstrap, para que nuestros estilos tengan prioridad
import * as bootstrap from 'bootstrap';
import './bootstrap';
import { iniciarCamposAdicionales } from './campos-adicionales';
import { iniciarFlujoDocumental } from './flujo-documental';
import { iniciarPortal } from './portal';

window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    iniciarCamposAdicionales();
    iniciarFlujoDocumental();
    iniciarPortal();

    // Confirmación para acciones sensibles: <form data-confirmar="¿Seguro?">
    document.querySelectorAll('form[data-confirmar]').forEach((form) => {
        form.addEventListener('submit', (evento) => {
            if (!window.confirm(form.dataset.confirmar)) {
                evento.preventDefault();
            }
        });
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
