/** Tailwind build config — compiles assets/tailwind.min.css, which replaces the
 *  Play CDN (cdn.tailwindcss.com) that must never be used in production.
 *  Rebuild after changing classes/config with:
 *    npx tailwindcss@3.4 -c tailwind.config.js -i tailwind.input.css -o assets/tailwind.min.css --minify
 *  app.js builds class names inside JS strings (roster/attendance/chat
 *  renderers), so it must stay in `content` or those classes disappear. */
module.exports = {
  content: ['./*.php', './assets/*.js'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        display: ['Fraunces', 'Georgia', 'Times New Roman', 'serif'],
      },
    },
  },
  plugins: [],
};
