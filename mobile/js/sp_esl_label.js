// B-Care Mobile - ラベル割当画面
// 配置先: mobile/js/sp_esl_label.js
// 対応HTML: mobile/sp_esl_label.php
//
// 「割り当てるラベルを選択」一覧のクライアント側検索フィルタと、
// ラベル未選択時は割当ボタンを押せないようにする制御を行う。
// 実際の割当処理は sp_confirm.js の submitFormViaFetch() で
// sp_esl_label_process.php へfetch送信する（ネイティブ<form>送信のまま
// だとhttp運用のためブラウザの「安全でないフォーム送信」警告が出るため）。

const labelSearchInput = document.getElementById("labelSearchInput");
const labelOptionList = document.getElementById("labelOptionList");
const labelSearchEmpty = document.getElementById("labelSearchEmpty");
const assignSubmitBtn = document.getElementById("assignSubmitBtn");
const assignForm = document.getElementById("assignForm");

if (assignForm) {
  assignForm.addEventListener("submit", (event) => {
    event.preventDefault();
    submitFormViaFetch(assignForm, event.submitter);
  });
}

function normalize(value) {
  return String(value).replaceAll(" ", "").trim().toLowerCase();
}

if (labelOptionList) {
  const options = Array.from(labelOptionList.querySelectorAll(".esl-option"));

  if (labelSearchInput) {
    labelSearchInput.addEventListener("input", () => {
      const keyword = normalize(labelSearchInput.value);
      let visibleCount = 0;

      options.forEach((option) => {
        const matches = !keyword || (option.dataset.code ?? "").includes(keyword);
        option.hidden = !matches;
        if (matches) visibleCount += 1;
      });

      if (labelSearchEmpty) {
        labelSearchEmpty.hidden = visibleCount !== 0;
      }
    });
  }

  if (assignSubmitBtn) {
    labelOptionList.addEventListener("change", (event) => {
      if (event.target.matches('input[type="radio"][name="label_code"]')) {
        assignSubmitBtn.disabled = false;
      }
    });
  }
}
