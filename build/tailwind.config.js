// Rebuild assets/css/tailwind.css after changing any classes:
//   npx tailwindcss@3.4.16 -c build/tailwind.config.js -i build/input.css -o assets/css/tailwind.css --minify
module.exports = {
    content: ['./index.php', './pages/**/*.php', './templates/**/*.php', './assets/js/**/*.js', './includes/**/*.php'],
    theme: {
        extend: {
            colors: {
                primary: { 50: '#f0fdfa', 100: '#ccfbf1', 200: '#99f6e4', 300: '#5eead4', 400: '#2dd4bf',
                           500: '#14b8a6', 600: '#0d9488', 700: '#0f766e', 800: '#115e59', 900: '#134e4a' },
            },
        },
    },
};
