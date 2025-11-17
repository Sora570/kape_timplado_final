(function (global) {
  const TYPE_CLASSES = [
    'toast-success',
    'toast-error',
    'toast-warning',
    'toast-info',
    'success',
    'error',
    'warning',
    'info'
  ];

  function ensureToastContainer() {
    let toast = document.getElementById('toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'toast';
      toast.className = 'toast';
      toast.style.display = 'none';
      document.body.appendChild(toast);
    }

    if (!toast.querySelector('#toast-message')) {
      const span = document.createElement('span');
      span.id = 'toast-message';
      toast.appendChild(span);
    }

    toast.setAttribute('aria-live', 'polite');
    toast.setAttribute('role', 'status');

    return toast;
  }

  function clearTypeClasses(toast) {
    TYPE_CLASSES.forEach(cls => toast.classList.remove(cls));
  }

  function showToast(message, type = 'info', options = {}) {
    const { duration = 4000 } = options;
    const toast = ensureToastContainer();
    const messageEl = toast.querySelector('#toast-message') || toast;

    if (toast._hideTimeout) {
      clearTimeout(toast._hideTimeout);
    }
    if (toast._cleanupTimeout) {
      clearTimeout(toast._cleanupTimeout);
    }

    messageEl.textContent = message;
    toast.style.display = 'block';
    toast.classList.remove('show');
    void toast.offsetWidth;

    toast.classList.add('toast');
    toast.classList.add('show');

    clearTypeClasses(toast);
    if (type) {
      toast.classList.add(type);
      toast.classList.add(`toast-${type}`);
    }

    toast._hideTimeout = setTimeout(() => {
      toast.classList.remove('show');
      toast._cleanupTimeout = setTimeout(() => {
        clearTypeClasses(toast);
        toast.style.display = 'none';
      }, 350);
    }, duration);
  }

  global.showToast = showToast;
})(window);
