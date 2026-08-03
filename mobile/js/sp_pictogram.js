'use strict';

// B-Care Mobile - ピクトグラム選択画面
// 配置先: js/sp_pictogram.js

const main = document.querySelector('.pictogram-main');
const patientId = main.dataset.patientId;

const grid = document.getElementById('pictogramGrid');
const pictogramScrollbar = document.getElementById('pictogramScrollbar');
const pictogramScrollbarThumb = document.getElementById('pictogramScrollbarThumb');
const chips = document.getElementById('categoryChips');
const selectedGrid = document.getElementById('selectedGrid');
const selectedScrollbar = document.getElementById('selectedScrollbar');
const selectedScrollbarThumb = document.getElementById('selectedScrollbarThumb');
const selectedEmpty = document.getElementById('selectedEmpty');
const selectedCount = document.getElementById('selectedCount');
const applyBtn = document.getElementById('applyBtn');
const clearAllBtn = document.getElementById('clearAllBtn');
const toast = document.getElementById('toast');

const SELECTED_COLS_PER_PAGE = 3;
const SELECTED_ROWS_PER_PAGE = 2;
const PICKER_COLS_PER_PAGE = 3;
const PICKER_ROWS_PER_PAGE = 4;

// TOPページ（患者ホーム）のピクトグラムブロックと同じ「N段×M列を1ページ」
// の並びに揃えるための行・列を計算する
function gridPosition(index, colsPerPage, rowsPerPage) {
  const perPage = colsPerPage * rowsPerPage;
  const page = Math.floor(index / perPage);
  const posInPage = index % perPage;
  return {
    row: Math.floor(posInPage / colsPerPage) + 1,
    col: page * colsPerPage + (posInPage % colsPerPage) + 1,
  };
}

function updateScrollbar(scrollEl, trackEl, thumbEl) {
  const maxScroll = scrollEl.scrollWidth - scrollEl.clientWidth;

  if (maxScroll <= 1) {
    trackEl.hidden = true;
    return;
  }
  trackEl.hidden = false;

  const thumbRatio = scrollEl.clientWidth / scrollEl.scrollWidth;
  const thumbWidthPct = Math.max(thumbRatio * 100, 12);
  const scrollRatio = scrollEl.scrollLeft / maxScroll;

  thumbEl.style.width = thumbWidthPct + '%';
  thumbEl.style.left = scrollRatio * (100 - thumbWidthPct) + '%';
}

function updateSelectedScrollbar() {
  updateScrollbar(selectedGrid, selectedScrollbar, selectedScrollbarThumb);
}

function updatePictogramScrollbar() {
  updateScrollbar(grid, pictogramScrollbar, pictogramScrollbarThumb);
}

selectedGrid.addEventListener('scroll', updateSelectedScrollbar);
grid.addEventListener('scroll', updatePictogramScrollbar);
window.addEventListener('resize', () => {
  updateSelectedScrollbar();
  updatePictogramScrollbar();
});

const items = Array.from(grid.querySelectorAll('.pictogram-item'));
const selected = new Map();

items.forEach((el) => {
  if (el.classList.contains('selected')) {
    selected.set(el.dataset.id, { name: el.dataset.name, img: el.dataset.img });
  }
});

function toggle(id) {
  const el = items.find((item) => item.dataset.id === id);
  if (selected.has(id)) {
    selected.delete(id);
    if (el) el.classList.remove('selected');
  } else {
    if (!el) return;
    selected.set(id, { name: el.dataset.name, img: el.dataset.img });
    el.classList.add('selected');
  }
  renderSelected();
}

function renderSelected() {
  selectedGrid.innerHTML = '';
  selectedCount.textContent = `：${selected.size}件`;

  if (selected.size === 0) {
    selectedEmpty.hidden = false;
    updateSelectedScrollbar();
    return;
  }
  selectedEmpty.hidden = true;

  let index = 0;
  selected.forEach((val, id) => {
    const item = document.createElement('div');
    item.className = 'selected-item';

    const { row, col } = gridPosition(index, SELECTED_COLS_PER_PAGE, SELECTED_ROWS_PER_PAGE);
    item.style.gridRow = row;
    item.style.gridColumn = col;
    index++;

    const img = document.createElement('img');
    img.src = val.img;
    img.alt = '';
    item.appendChild(img);

    const label = document.createElement('span');
    label.className = 'label';
    label.textContent = val.name;
    item.appendChild(label);

    const remove = document.createElement('span');
    remove.className = 'remove';
    remove.setAttribute('aria-hidden', 'true');
    remove.textContent = '×';
    item.appendChild(remove);

    item.addEventListener('click', () => toggle(id));
    selectedGrid.appendChild(item);
  });

  updateSelectedScrollbar();
}

items.forEach((el) => {
  el.addEventListener('click', () => toggle(el.dataset.id));
});

clearAllBtn.addEventListener('click', () => {
  selected.clear();
  items.forEach((el) => el.classList.remove('selected'));
  renderSelected();
});

function layoutPictogramGrid(category) {
  let index = 0;
  items.forEach((el) => {
    const visible = category === 'すべて' || el.dataset.cat === category;
    el.hidden = !visible;
    if (visible) {
      const { row, col } = gridPosition(index, PICKER_COLS_PER_PAGE, PICKER_ROWS_PER_PAGE);
      el.style.gridRow = row;
      el.style.gridColumn = col;
      index++;
    }
  });
  grid.scrollLeft = 0;
  updatePictogramScrollbar();
}

chips.querySelectorAll('.chip').forEach((chip) => {
  chip.addEventListener('click', () => {
    chips.querySelectorAll('.chip').forEach((c) => c.classList.remove('is-active'));
    chip.classList.add('is-active');
    layoutPictogramGrid(chip.dataset.cat);
  });
});

document.querySelectorAll('.collapsible').forEach((toggleHeader) => {
  toggleHeader.addEventListener('click', () => {
    const body = document.getElementById(toggleHeader.dataset.target);
    body.hidden = !body.hidden;
    toggleHeader.classList.toggle('is-collapsed', body.hidden);
  });
});

function showToast(message) {
  toast.textContent = message;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 1800);
}

applyBtn.addEventListener('click', () => {
  applyBtn.disabled = true;

  const pictogramIds = Array.from(selected.keys());

  fetch('sp_pictogram_process.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ patient_id: patientId, pictogram_ids: pictogramIds }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showToast(`${pictogramIds.length}件のピクトグラムを設定しました`);
        setTimeout(() => {
          window.location.href = data.redirect;
        }, 700);
        return;
      }
      applyBtn.disabled = false;
      showToast(data.message || '設定に失敗しました。');
    })
    .catch(() => {
      applyBtn.disabled = false;
      showToast('通信エラーが発生しました。時間をおいて再度お試しください。');
    });
});

renderSelected();
layoutPictogramGrid('すべて');
