@echo off
cd ..
mkdir staff-web
cd staff-web
call npm init -y
call npm pkg set type="module"
call npm install react react-dom react-router-dom axios
call npm install -D vite @vitejs/plugin-react typescript @types/react @types/react-dom tailwindcss postcss autoprefixer
call npx tailwindcss init -p
echo DONE
