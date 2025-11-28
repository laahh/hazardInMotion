import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { viteStaticCopy } from 'vite-plugin-static-copy'

export default defineConfig({
    build: {
        manifest: true,
        rtl: true,
        outDir: 'public/build/',
        cssCodeSplit: true,
        
    },
    plugins: [
        
        viteStaticCopy({
            targets: [
                {
                    src: 'resources/css',
                    dest: 'css'
                },
                {
                    src: 'resources/fonts',
                    dest: ''
                },
                {
                    src: 'resources/images',
                    dest: ''
                },
                {
                    src: 'resources/js',
                    dest: ''
                },
                {
                    src: 'resources/maps',
                    dest: ''
                },
                {
                    src: 'resources/scss',
                    dest: ''
                },
            ],
        })
    ],
});
