/* Notification bell, live counters and small toast messages shared by every page. */
(function () {
  'use strict';

  const wrap = document.getElementById('notifWrap');
  const btn = document.getElementById('notificationBtn');
  const dropdown = document.getElementById('notificationDropdown');
  const listEl = document.getElementById('notificationContent');
  const dot = document.getElementById('notifDot');
  const countEl = document.getElementById('notifCount');
  const markAll = document.getElementById('markAllRead');
  const cartBadge = document.getElementById('cartBadge');
  const ordersBadge = document.getElementById('navOrdersBadge');
  const feedbackBadge = document.getElementById('navFeedbackBadge');
  const toastStack = document.getElementById('toastStack');

  function setBadge(el, value, cap) {
    if (!el) return;
    const number = Number(value) || 0;
    el.hidden = number < 1;
    el.textContent = cap && number > cap ? cap + '+' : String(number);
  }

  function setUnread(unread) {
    const number = Number(unread) || 0;
    if (dot) dot.hidden = number < 1;
    if (countEl) countEl.textContent = number > 0 ? number + ' new' : '';
  }

  function toast(message, kind) {
    if (!toastStack) return;
    const item = document.createElement('div');
    item.className = 'toast' + (kind === 'error' ? ' toast-error' : '');
    item.textContent = message;
    toastStack.appendChild(item);
    window.setTimeout(() => item.classList.add('toast-hide'), 3200);
    window.setTimeout(() => item.remove(), 3700);
  }

  window.BQ = {
    toast: toast,
    setCartCount: (value) => setBadge(cartBadge, value, 99),
  };

  if (!wrap || !btn || !dropdown || !listEl) return;

  const endpoint = wrap.dataset.endpoint;
  const readEndpoint = wrap.dataset.readEndpoint;
  const token = wrap.dataset.token;
  let items = [];

  function render() {
    listEl.textContent = '';
    if (!items.length) {
      const empty = document.createElement('p');
      empty.className = 'notif-empty';
      empty.textContent = 'You have no notifications yet.';
      listEl.appendChild(empty);
      return;
    }
    items.forEach((item) => {
      const row = document.createElement('button');
      row.type = 'button';
      row.className = 'notif-item' + (item.is_read ? '' : ' unread');
      const text = document.createElement('span');
      text.className = 'notif-text';
      text.textContent = item.message;
      const time = document.createElement('span');
      time.className = 'notif-time';
      time.textContent = item.time;
      row.appendChild(text);
      row.appendChild(time);
      row.addEventListener('click', () => openItem(item));
      listEl.appendChild(row);
    });
  }

  function post(body) {
    const data = new FormData();
    data.append('csrf_token', token);
    Object.keys(body).forEach((key) => data.append(key, body[key]));
    return fetch(readEndpoint, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch' },
    }).then((response) => response.json());
  }

  function openItem(item) {
    const go = () => {
      if (item.link) window.location.href = item.link;
    };
    if (item.is_read) {
      go();
      return;
    }
    post({ id: item.id }).then(go, go);
  }

  function load() {
    return fetch(endpoint, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then((response) => {
        if (response.status === 401) {
          window.location.href = '../index.html?mode=login';
          throw new Error('signed out');
        }
        return response.json();
      })
      .then((data) => {
        if (!data.ok) return;
        items = data.items || [];
        setUnread(data.unread);
        if (typeof data.cart !== 'undefined') setBadge(cartBadge, data.cart, 99);
        if (typeof data.pending_orders !== 'undefined') setBadge(ordersBadge, data.pending_orders, 99);
        if (typeof data.open_feedback !== 'undefined') setBadge(feedbackBadge, data.open_feedback, 99);
        if (!dropdown.hidden) render();
      })
      .catch(() => {});
  }

  function closeDropdown() {
    dropdown.hidden = true;
    btn.setAttribute('aria-expanded', 'false');
  }

  btn.addEventListener('click', (event) => {
    event.stopPropagation();
    if (dropdown.hidden) {
      dropdown.hidden = false;
      btn.setAttribute('aria-expanded', 'true');
      render();
      load();
    } else {
      closeDropdown();
    }
  });

  dropdown.addEventListener('click', (event) => event.stopPropagation());
  document.addEventListener('click', closeDropdown);
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeDropdown();
  });

  if (markAll) {
    markAll.addEventListener('click', () => {
      post({ all: '1' })
        .then(() => {
          items.forEach((item) => {
            item.is_read = true;
          });
          setUnread(0);
          render();
        })
        .catch(() => {});
    });
  }

  load();
  window.setInterval(() => {
    if (!document.hidden) load();
  }, 15000);
})();
