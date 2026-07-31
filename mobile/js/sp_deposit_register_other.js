'use strict';

const form = document.getElementById('registerOtherForm');
const formStatus = document.getElementById('formStatus');
const submitBtn = document.getElementById('submitBtn');

const qtyInput = document.getElementById('quantity');
document.querySelector('.qty-minus').addEventListener('click', () => {
  qtyInput.value = String(Math.max(Number(qtyInput.min) || 1, Number(qtyInput.value) - 1));
});
document.querySelector('.qty-plus').addEventListener('click', () => {
  qtyInput.value = String(Number(qtyInput.value) + 1);
});

form.addEventListener('submit', (event) => {
  event.preventDefault();
  formStatus.textContent = '';

  if (!form.reportValidity()) {
    return;
  }

  const addToMaster = form.querySelector('input[name="add_to_master"]:checked').value === '1';

  const payload = {
    patient_id: window.depositPatientId,
    item_name: document.getElementById('itemName').value.trim(),
    quantity: Number(qtyInput.value),
    condition_note: document.getElementById('conditionNote').value.trim(),
    stored_at: document.getElementById('storedAt').value,
    stored_by: document.getElementById('storedBy').value,
    storage_location_id: document.getElementById('storageLocation').value,
    remarks: document.getElementById('remarks').value,
    add_to_master: addToMaster,
  };

  submitBtn.disabled = true;
  formStatus.textContent = '登録中...';

  fetch('sp_deposit_register_other_process.php', {
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
      formStatus.textContent = data.message || '登録に失敗しました。';
    })
    .catch(() => {
      submitBtn.disabled = false;
      formStatus.textContent = '通信エラーが発生しました。時間をおいて再度お試しください。';
    });
});
