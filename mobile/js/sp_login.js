const form = document.getElementById('loginForm');
const userId = document.getElementById('userId');
const password = document.getElementById('password');
const passwordToggle = document.getElementById('passwordToggle');
const userIdError = document.getElementById('userIdError');
const passwordError = document.getElementById('passwordError');
const formStatus = document.getElementById('formStatus');

passwordToggle.addEventListener('click', () => {
  const isPasswordVisible = password.type === 'text';
  password.type = isPasswordVisible ? 'password' : 'text';
  passwordToggle.classList.toggle('is-visible', !isPasswordVisible);
  passwordToggle.setAttribute(
    'aria-label',
    isPasswordVisible ? 'パスワードを表示' : 'パスワードを非表示'
  );
});

function clearError(input, errorElement) {
  input.classList.remove('input-error');
  input.removeAttribute('aria-invalid');
  errorElement.textContent = '';
}

function setError(input, errorElement, message) {
  input.classList.add('input-error');
  input.setAttribute('aria-invalid', 'true');
  errorElement.textContent = message;
}

userId.addEventListener('input', () => clearError(userId, userIdError));
password.addEventListener('input', () => clearError(password, passwordError));

form.addEventListener('submit', (event) => {
  event.preventDefault();

  clearError(userId, userIdError);
  clearError(password, passwordError);
  formStatus.textContent = '';

  let isValid = true;

  if (!userId.value.trim()) {
    setError(userId, userIdError, 'ユーザーIDを入力してください。');
    isValid = false;
  }

  if (!password.value) {
    setError(password, passwordError, 'パスワードを入力してください。');
    isValid = false;
  }

  if (!isValid) {
    const firstInvalidInput = form.querySelector('[aria-invalid="true"]');
    firstInvalidInput?.focus();
    return;
  }

  const submitButton = form.querySelector('.login-button');
  submitButton.disabled = true;
  formStatus.textContent = 'ログイン中...';

  const params = new URLSearchParams();
  params.set('userId', userId.value.trim());
  params.set('password', password.value);
  params.set('redirect', form.dataset.redirect || '');

  fetch('sp_login_process.php', {
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
      formStatus.textContent = data.message || 'ログインに失敗しました。';
    })
    .catch(() => {
      submitButton.disabled = false;
      formStatus.textContent = '通信エラーが発生しました。時間をおいて再度お試しください。';
    });
});
