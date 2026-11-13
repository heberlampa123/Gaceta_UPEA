import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'], // app.js importa resources/scss/app.scss (Bootstrap personalizado)
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            // Bootstrap 5.3 aún usa @import y funciones de color antiguas de Sass: se silencian sus avisos.
            scss: { quietDeps: true, silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'mixed-decls'] },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
