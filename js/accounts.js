document.addEventListener('DOMContentLoaded', () => {
  // Suspend dialog
  const modal = document.getElementById('suspendModal');
  if (modal) {
    const close = () => {
      modal.hidden = true;
    };
    document.querySelectorAll('[data-suspend]').forEach((button) => {
      button.addEventListener('click', () => {
        document.getElementById('suspendUserId').value = button.dataset.suspend;
        document.getElementById('suspendName').textContent = button.dataset.name;
        document.getElementById('suspendAmount').value = 7;
        modal.hidden = false;
        document.getElementById('suspendAmount').focus();
      });
    });
    document.getElementById('suspendCancel').addEventListener('click', close);
    modal.addEventListener('click', (event) => {
      if (event.target === modal) close();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !modal.hidden) close();
    });
  }
});
