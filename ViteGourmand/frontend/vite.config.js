// frontend/vite.config.js
import { defineConfig } from 'vite'
import { resolve } from 'path'
import fs from 'fs'
import path from 'path'

export default defineConfig({
  root: '.',
  publicDir: 'public',
  server: {
    port: 3000,
    open: true,
    historyApiFallback: true,
    proxy: {
      '/api': {
        target: 'http://localhost/vite-gourmand/backend/api',
        changeOrigin: true,
        secure: false,
        rewrite: (path) => path.replace(/^\/api/, '')
      }
    }
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
  css: {
    preprocessorOptions: {
      scss: {
        api: 'modern-compiler',
        includePaths: [resolve(__dirname, 'node_modules')]
      }
    }
  },
  resolve: {
    alias: {
      '~bootstrap': resolve(__dirname, 'node_modules/bootstrap')
    }
  },
  plugins: [
    {
      name: 'copy-backend',
      buildStart: async () => {
        // Copier le backend vers le dossier Apache à chaque build
        await copyBackendToApache();
      },
      handleHotUpdate: async () => {
        // Copier en mode développement quand les fichiers changent
        await copyBackendToApache();
      }
    },
    {
      name: 'copy-dirs',
      closeBundle: async () => {
        const fs = await import('fs')
        const path = await import('path')
        
        // Dossiers à copier
        const dirsToCopy = ['pages', 'js', 'headers', 'Router']
        const outDir = 'dist'
        
        // Copier les fichiers CSS compilés
        const cssFiles = ['scss/main.css', 'scss/main.css.map']
        for (const cssFile of cssFiles) {
          const srcCss = path.resolve(cssFile)
          const destCss = path.resolve(outDir, cssFile)
          
          if (fs.existsSync(srcCss)) {
            const destDir = path.dirname(destCss)
            if (!fs.existsSync(destDir)) {
              fs.mkdirSync(destDir, { recursive: true })
            }
            fs.copyFileSync(srcCss, destCss)
            console.log(`✓ Copié ${cssFile} vers dist/${cssFile}`)
          }
        }
        
        for (const dir of dirsToCopy) {
          const srcDir = path.resolve(dir)
          const destDir = path.resolve(outDir, dir)
          
          if (fs.existsSync(srcDir)) {
            if (!fs.existsSync(destDir)) {
              fs.mkdirSync(destDir, { recursive: true })
            }
            
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

// Fonction pour copier le backend vers Apache
async function copyBackendToApache() {
  const backendPath = resolve(__dirname, '../backend')
  const apachePath = 'C:/xampp/htdocs/vite-gourmand/backend'
  
  if (fs.existsSync(backendPath)) {
    // Créer le dossier de destination s'il n'existe pas
    if (!fs.existsSync(apachePath)) {
      fs.mkdirSync(apachePath, { recursive: true })
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
    
    copyRecursive(backendPath, apachePath)
    console.log('✓ Backend copié vers Apache')
  }
}