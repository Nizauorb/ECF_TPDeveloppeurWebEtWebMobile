import { defineConfig } from 'vite'
import { resolve } from 'path'

export default defineConfig({
  root: '.',
  publicDir: 'public',
  server: {
    port: 3000,
    open: true,
    historyApiFallback: true
  },
  build: {
    outDir: 'dist',
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'index.html')
      }
    },
    assetsDir: 'assets',
    copyPublicDir: true
  },
  // Plugin pour copier les dossiers supplémentaires
  plugins: [
    {
      name: 'copy-dirs',
      closeBundle: async () => {
        const fs = await import('fs')
        const path = await import('path')
        
        // Dossiers à copier
        const dirsToCopy = ['pages', 'js', 'headers', 'Router', 'scss']
        const outDir = 'dist'
        
        for (const dir of dirsToCopy) {
          const srcDir = path.resolve(dir)
          const destDir = path.resolve(outDir, dir)
          
          if (fs.existsSync(srcDir)) {
            // Créer le dossier de destination s'il n'existe pas
            if (!fs.existsSync(destDir)) {
              fs.mkdirSync(destDir, { recursive: true })
            }
            
            // Copier récursivement
            const copyRecursive = (src, dest) => {
              const files = fs.readdirSync(src)
              files.forEach(file => {
                const srcPath = path.join(src, file)
                const destPath = path.join(dest, file)
                
                if (fs.statSync(srcPath).isDirectory()) {
                  if (!fs.existsSync(destPath)) {
                    fs.mkdirSync(destPath, { recursive: true })
                  }
                  copyRecursive(srcPath, destPath)
                } else {
                  fs.copyFileSync(srcPath, destPath)
                }
              })
            }
            
            copyRecursive(srcDir, destDir)
            console.log(`✓ Copié ${dir}/ vers dist/${dir}/`)
          }
        }
      }
    }
  ]
})