document.addEventListener('DOMContentLoaded', () => {
  const backdrop = document.createElement('div');
  backdrop.id = 'actionConfirm';
  backdrop.className = 'action-confirm-backdrop';
  backdrop.hidden = true;
  backdrop.innerHTML =
    '<section class="action-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="actionConfirmTitle" aria-describedby="actionConfirmMessage"><h2 id="actionConfirmTitle" data-confirm-title></h2><p id="actionConfirmMessage" data-confirm-message></p><div class="action-confirm-buttons"><button class="action-confirm-yes" type="button" data-confirm-yes>Yes</button><button class="action-confirm-no" type="button" data-confirm-no>No</button></div></section>';
  document.body.appendChild(backdrop);

  const title = backdrop.querySelector('[data-confirm-title]');
  const message = backdrop.querySelector('[data-confirm-message]');
  const yesButton = backdrop.querySelector('[data-confirm-yes]');
  const noButton = backdrop.querySelector('[data-confirm-no]');
  let activeForm = null;

  function closeDialog() {
    backdrop.hidden = true;
    document.body.classList.remove('confirm-open');
    activeForm = null;
  }

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (form.dataset.confirmed === 'true') {
        delete form.dataset.confirmed;
        return;
      }

      event.preventDefault();
      activeForm = form;
      title.textContent = form.dataset.confirmTitle || 'Please confirm';
      let confirmationMessage = form.dataset.confirmMessage || 'Are you sure you want to continue?';
      if (form.hasAttribute('data-confirm-role')) {
        const roleSelect = form.querySelector('select[name="role"]');
        confirmationMessage = confirmationMessage.replace(
          '{role}',
          roleSelect?.selectedOptions[0]?.textContent || 'selected role',
        );
      }
      message.textContent = confirmationMessage;
      backdrop.hidden = false;
      document.body.classList.add('confirm-open');
      noButton.focus();
    });
  });

  yesButton.addEventListener('click', () => {
    if (!activeForm) return;
    const form = activeForm;
    form.dataset.confirmed = 'true';
    closeDialog();
    form.requestSubmit();
  });

  noButton.addEventListener('click', closeDialog);
  backdrop.addEventListener('click', (event) => {
    if (event.target === backdrop) closeDialog();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !backdrop.hidden) closeDialog();
  });
});
