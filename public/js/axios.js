// Dynamically load Axios from CDN
(function loadAxios() {
  const script = document.createElement('script');
  script.src = 'https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js';
  script.onload = configureAxios;
  document.head.appendChild(script);
})();

// Configure Axios once it's loaded
function configureAxios() {
  axios.defaults.withCredentials = true;
  axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  axios.defaults.headers.common['Content-Type'] = 'application/x-www-form-urlencoded';

  // Grab CSRF token from meta tag
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-Token'] = csrfToken;
  }

  console.log('Axios is ready with CSRF and credentials configured.');
}
