import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            // Nested git worktrees (and their .env symlinks) can EINVAL the
            // Docker-mounted FS watcher when the worktree is removed mid-run.
            ignored: ['**/.worktrees/**'],
        },
    },
});
