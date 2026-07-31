'use strict';

const form = document.getElementById('registerForm');
const formStatus = document.getElementById('formStatus');
const submitBtn = document.getElementById('submitBtn');
const selectedCountEl = document.getElementById('selectedCount');
const rows = Array.from(document.querySelectorAll('.deposit-item-table tbody tr'));

function updateSelectedCount() {
  const count = rows.filter((row) => row.querySelector('.item-checkbox').checked).length;
  selectedCountEl.textContent = String(count);
}

rows.forEach((row) => {
  const minusBtn = row.querySelector('.qty-minus');
  const plusBtn = row.querySelector('.qty-plus');
  const qtyInput = row.querySelector('.qty-input');
  const checkbox = row.querySelector('.item-checkbox');
  const step = Number(qtyInput.step) || 1;
  const min = Number(qtyInput.min) || 0;

  minusBtn.addEventListener('click', () => {
    const next = Math.max(min, Number(qtyInput.value) - step);
    qtyInput.value = String(next);
  });

  plusBtn.addEventListener('click', () => {
    const next = Number(qtyInput.value) + step;
    qtyInput.value = String(next);
    if (Number(qtyInput.value) > 0) {
      checkbox.checked = true;
      updateSelectedCount();
    }
  });

  checkbox.addEventListener('change', updateSelectedCount);
});

updateSelectedCount();

form.addEventListener('submit', (event) => {
  event.preventDefault();
  formStatus.textContent = '';

  const items = rows
    .filter((row) => row.querySelector('.item-checkbox').checked)
    .map((row) => ({
      item_master_id: Number(row.dataset.itemMasterId),
      name: row.dataset.name,
      unit: row.dataset.unit,
      quantity: Number(row.querySelector('.qty-input').value),
    }))
    .filter((item) => item.quantity > 0);

  if (items.length === 0) {
    formStatus.textContent = '登録する品目を1件以上選択してください。';
    return;
  }

  if (!form.reportValidity()) {
    return;
  }

  const payload = {
    patient_id: window.depositPatientId,
    items,
    stored_at: document.getElementById('storedAt').value,
    stored_by: document.getElementById('storedBy').value,
    storage_location_id: document.getElementById('storageLocation').value,
    remarks: document.getElementById('remarks').value,
  };

  submitBtn.disabled = true;
  formStatus.textContent = '登録中...';

  fetch('sp_deposit_register_process.php', {
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
