'use strict';

const form = document.getElementById('returnForm');
const formStatus = document.getElementById('formStatus');
const submitBtn = document.getElementById('submitBtn');
const selectAllBtn = document.getElementById('selectAllBtn');
const checkboxes = Array.from(document.querySelectorAll('.return-checkbox'));
const selectedCountEls = [document.getElementById('selectedCount'), document.getElementById('selectedCount2')];
const returnToOtherInput = document.getElementById('returnToOther');

function updateSelectedCount() {
  const count = checkboxes.filter((cb) => cb.checked).length;
  selectedCountEls.forEach((el) => { el.textContent = String(count); });
}

checkboxes.forEach((cb) => cb.addEventListener('change', updateSelectedCount));
updateSelectedCount();

selectAllBtn.addEventListener('click', () => {
  const allChecked = checkboxes.every((cb) => cb.checked);
  checkboxes.forEach((cb) => { cb.checked = !allChecked; });
  updateSelectedCount();
});

form.querySelectorAll('input[name="return_to"]').forEach((radio) => {
  radio.addEventListener('change', () => {
    returnToOtherInput.disabled = radio.value !== 'その他' ? true : false;
    if (radio.checked && radio.value === 'その他') {
      returnToOtherInput.disabled = false;
    } else if (radio.checked) {
      returnToOtherInput.disabled = true;
      returnToOtherInput.value = '';
    }
  });
});

form.addEventListener('submit', (event) => {
  event.preventDefault();
  formStatus.textContent = '';

  const depositIds = checkboxes.filter((cb) => cb.checked).map((cb) => Number(cb.value));

  if (depositIds.length === 0) {
    formStatus.textContent = '返却する預かり品を1件以上選択してください。';
    return;
  }

  if (!form.reportValidity()) {
    return;
  }

  const returnTo = form.querySelector('input[name="return_to"]:checked').value;
  if (returnTo === 'その他' && returnToOtherInput.value.trim() === '') {
    formStatus.textContent = '返却先を入力してください。';
    returnToOtherInput.focus();
    return;
  }

  const payload = {
    patient_id: window.depositPatientId,
    deposit_ids: depositIds,
    returned_at: document.getElementById('returnedAt').value,
    returned_by: document.getElementById('returnedBy').value,
    return_to: returnTo,
    return_to_other: returnToOtherInput.value.trim(),
    return_remarks: document.getElementById('returnRemarks').value,
  };

  submitBtn.disabled = true;
  formStatus.textContent = '返却処理中...';

  fetch('sp_deposit_return_process.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        window.location.href = data.redirect;
        return;
      }
      submitBtn.disabled = false;
      formStatus.textContent = data.message || '返却処理に失敗しました。';
    })
    .catch(() => {
      submitBtn.disabled = false;
      formStatus.textContent = '通信エラーが発生しました。時間をおいて再度お試しください。';
    });
});
