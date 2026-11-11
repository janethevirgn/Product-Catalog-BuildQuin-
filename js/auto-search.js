document.addEventListener('DOMContentLoaded', () => {
  const forms = document.querySelectorAll('form[data-auto-search]');

  forms.forEach((form) => {
    const input = form.querySelector('input[name="q"]');
    if (!input) return;

    const storageKey = `restore-search-${form.dataset.autoSearch}`;
    try {
      if (sessionStorage.getItem(storageKey) === '1') {
        sessionStorage.removeItem(storageKey);
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
      }
    } catch (error) {
      // Search still works when session storage is unavailable.
    }

    let timer;
    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => {
        try {
          sessionStorage.setItem(storageKey, '1');
        } catch (error) {
          // Submitting the form does not depend on session storage.
        }
        form.requestSubmit();
      }, 350);
    });
  });
});
