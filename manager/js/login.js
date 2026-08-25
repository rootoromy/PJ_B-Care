'use strict';

const form = document.getElementById('loginForm');
const loginId = document.getElementById('loginId');
const password = document.getElementById('password');
const togglePassword = document.getElementById('togglePassword');
const loginIdError = document.getElementById('loginIdError');
const passwordError = document.getElementById('passwordError');
const formMessage = document.getElementById('formMessage');

togglePassword.addEventListener('click', () => {
  const isPassword = password.type === 'password';
  password.type = isPassword ? 'text' : 'password';
  togglePassword.classList.toggle('is-visible', isPassword);
  togglePassword.setAttribute(
    'aria-label',
    isPassword ? 'パスワードを非表示' : 'パスワードを表示'
  );
});

function setError(input, target, message) {
  input.classList.toggle('is-error', Boolean(message));
  target.textContent = message;
}

function validateForm() {
  const loginValue = loginId.value.trim();
  const passwordValue = password.value;
  let isValid = true;

  setError(loginId, loginIdError, '');
  setError(password, passwordError, '');
  formMessage.textContent = '';

  if (!loginValue) {
    setError(loginId, loginIdError, 'ユーザーIDを入力してください。');
    isValid = false;
  }

  if (!passwordValue) {
    setError(password, passwordError, 'パスワードを入力してください。');
    isValid = false;
  }

  return isValid;
}

form.addEventListener('submit', (event) => {
  event.preventDefault();

  if (!validateForm()) {
    const firstInvalid = form.querySelector('.is-error');
    firstInvalid?.focus();
    return;
  }

  const submitButton = form.querySelector('.login-button');
  submitButton.disabled = true;
  formMessage.textContent = 'ログイン中...';

  const params = new URLSearchParams();
  params.set('login_id', loginId.value.trim());
  params.set('password', password.value);
  params.set('redirect', form.dataset.redirect || '');

  fetch('login_process.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: params,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        window.location.href = data.redirect;
        return;
      }
      submitButton.disabled = false;
      formMessage.textContent = data.message || 'ログインに失敗しました。';
    })
    .catch(() => {
      submitButton.disabled = false;
      formMessage.textContent = '通信エラーが発生しました。時間をおいて再度お試しください。';
    });
});

[loginId, password].forEach((input) => {
  input.addEventListener('input', () => {
    if (input === loginId && loginIdError.textContent) {
      setError(loginId, loginIdError, '');
    }

    if (input === password && passwordError.textContent) {
      setError(password, passwordError, '');
    }

    formMessage.textContent = '';
  });
});
