/**
 * B-Care Manager - ユーザー管理（モック）
 * 配置先: manager/js/user_management.js
 *
 * DB未接続のフロントエンドのみのモックです。
 * ページを再読み込みすると変更内容は失われます。
 */

const users = [
  {
    id: "U001",
    name: "山田 太郎",
    email: "yamada.taro@tomare.co.jp",
    ward: "3階東病棟",
    role: "管理者",
    status: "有効",
    lastLogin: "2026/08/05 09:12",
    updatedAt: "2026/08/01 14:20"
  },
  {
    id: "U002",
    name: "佐藤 花子",
    email: "sato.hanako@tomare.co.jp",
    ward: "3階東病棟",
    role: "スタッフ",
    status: "有効",
    lastLogin: "2026/08/04 16:30",
    updatedAt: "2026/07/28 10:15"
  },
  {
    id: "U003",
    name: "田中 一郎",
    email: "tanaka.ichiro@tomare.co.jp",
    ward: "4階西病棟",
    role: "スタッフ",
    status: "有効",
    lastLogin: "2026/08/03 11:20",
    updatedAt: "2026/07/25 09:40"
  },
  {
    id: "U004",
    name: "鈴木 奈々",
    email: "suzuki.nana@tomare.co.jp",
    ward: "4階西病棟",
    role: "スタッフ",
    status: "有効",
    lastLogin: "2026/08/02 18:45",
    updatedAt: "2026/07/20 16:10"
  },
  {
    id: "U005",
    name: "高橋 健",
    email: "takahashi.ken@tomare.co.jp",
    ward: "2階南病棟",
    role: "スタッフ",
    status: "無効",
    lastLogin: "2026/07/20 10:15",
    updatedAt: "2026/07/20 10:20"
  },
  {
    id: "U006",
    name: "伊藤 美咲",
    email: "ito.misaki@tomare.co.jp",
    ward: "事務部",
    role: "管理者",
    status: "有効",
    lastLogin: "2026/08/05 08:50",
    updatedAt: "2026/08/02 13:05"
  }
];

const tbody = document.getElementById("userTableBody");
const recordCount = document.getElementById("recordCount");
const keywordInput = document.getElementById("keywordInput");
const roleFilter = document.getElementById("roleFilter");
const wardFilter = document.getElementById("wardFilter");
const statusFilter = document.getElementById("statusFilter");
const showInactive = document.getElementById("showInactive");

const modal = document.getElementById("userModal");
const modalTitle = document.getElementById("modalTitle");
const form = document.getElementById("userForm");
const editingId = document.getElementById("editingId");

function renderUsers(list) {
  tbody.innerHTML = "";

  list.forEach((user) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${user.id}</td>
      <td>${user.name}</td>
      <td>${user.email}</td>
      <td>${user.ward}</td>
      <td>
        <span class="badge ${user.role === "管理者" ? "badge-admin" : "badge-staff"}">
          ${user.role}
        </span>
      </td>
      <td>
        <span class="badge ${user.status === "有効" ? "badge-active" : "badge-inactive"}">
          ${user.status}
        </span>
      </td>
      <td>${user.lastLogin}</td>
      <td>${user.updatedAt}</td>
      <td><button class="btn-select" data-id="${user.id}" type="button">編集</button></td>
    `;
    tbody.appendChild(tr);
  });

  recordCount.textContent = list.length
    ? `全${list.length}件中 1〜${list.length}件を表示`
    : "該当するユーザーはいません";
}

function applyFilters() {
  const keyword = keywordInput.value.trim().toLowerCase();
  const role = roleFilter.value;
  const ward = wardFilter.value;
  const status = statusFilter.value;

  const filtered = users.filter((user) => {
    const keywordMatch =
      !keyword ||
      user.id.toLowerCase().includes(keyword) ||
      user.name.toLowerCase().includes(keyword) ||
      user.email.toLowerCase().includes(keyword);

    const roleMatch = !role || user.role === role;
    const wardMatch = !ward || user.ward === ward;
    const statusMatch = !status || user.status === status;
    const inactiveMatch = showInactive.checked || user.status !== "無効";

    return keywordMatch && roleMatch && wardMatch && statusMatch && inactiveMatch;
  });

  renderUsers(filtered);
}

function resetFilters() {
  keywordInput.value = "";
  roleFilter.value = "";
  wardFilter.value = "";
  statusFilter.value = "";
  showInactive.checked = false;
  applyFilters();
}

function openModal(mode, user = null) {
  modal.classList.remove("hidden");
  modal.setAttribute("aria-hidden", "false");

  if (mode === "create") {
    modalTitle.textContent = "新規ユーザー追加";
    editingId.value = "";
    form.reset();
    document.getElementById("userStatus").value = "有効";
    document.getElementById("userId").disabled = false;
  } else {
    modalTitle.textContent = "ユーザー編集";
    editingId.value = user.id;
    document.getElementById("userId").value = user.id;
    document.getElementById("userName").value = user.name;
    document.getElementById("userEmail").value = user.email;
    document.getElementById("userWard").value = user.ward;
    document.getElementById("userRole").value = user.role;
    document.getElementById("userStatus").value = user.status;
    document.getElementById("userId").disabled = true;
  }
}

function closeModal() {
  modal.classList.add("hidden");
  modal.setAttribute("aria-hidden", "true");
}

document.getElementById("filterButton").addEventListener("click", applyFilters);
document.getElementById("clearButton").addEventListener("click", resetFilters);
showInactive.addEventListener("change", applyFilters);
keywordInput.addEventListener("keydown", (event) => {
  if (event.key === "Enter") applyFilters();
});

document.getElementById("openCreateModal").addEventListener("click", () => openModal("create"));
document.getElementById("closeModal").addEventListener("click", closeModal);
document.getElementById("cancelModal").addEventListener("click", closeModal);

tbody.addEventListener("click", (event) => {
  const button = event.target.closest(".btn-select");
  if (!button) return;
  const user = users.find((item) => item.id === button.dataset.id);
  if (user) openModal("edit", user);
});

form.addEventListener("submit", (event) => {
  event.preventDefault();

  const now = new Date();
  const formattedNow = `${now.getFullYear()}/${String(now.getMonth() + 1).padStart(2, "0")}/${String(now.getDate()).padStart(2, "0")} ${String(now.getHours()).padStart(2, "0")}:${String(now.getMinutes()).padStart(2, "0")}`;

  const data = {
    id: document.getElementById("userId").value.trim(),
    name: document.getElementById("userName").value.trim(),
    email: document.getElementById("userEmail").value.trim(),
    ward: document.getElementById("userWard").value,
    role: document.getElementById("userRole").value,
    status: document.getElementById("userStatus").value,
    lastLogin: "-",
    updatedAt: formattedNow
  };

  if (editingId.value) {
    const index = users.findIndex((user) => user.id === editingId.value);
    if (index >= 0) {
      const wasActive = users[index].status !== "無効";
      if (wasActive && data.status === "無効") {
        const confirmed = confirm(
          `${data.name}さんのアカウントを無効にしますか？\n無効化するとB-Careへログインできなくなります。`
        );
        if (!confirmed) return;
      }

      users[index] = {
        ...users[index],
        ...data,
        id: users[index].id,
        lastLogin: users[index].lastLogin
      };
    }
  } else {
    if (users.some((user) => user.id === data.id)) {
      alert("同じユーザーIDがすでに登録されています。");
      return;
    }
    users.push(data);
  }

  closeModal();
  applyFilters();
});

modal.addEventListener("click", (event) => {
  if (event.target === modal) closeModal();
});

renderUsers(users.filter((user) => user.status !== "無効"));
