import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import ui from '@nuxt/ui/vite'

export default defineConfig({
  plugins: [
    laravel({ input: ['resources/js/app.ts'], refresh: true }),
    vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
    ui({ router: 'inertia', colorMode: false, ui: { colors: { primary: 'emerald', neutral: 'slate' } } }),
  ],
})
