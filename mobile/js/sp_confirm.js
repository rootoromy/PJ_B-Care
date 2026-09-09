// B-Care Mobile - 共通確認モーダル ＋ フォームのfetch送信
// 配置先: mobile/js/sp_confirm.js
//
// 【確認モーダル】
// ブラウザ標準の confirm() はダイアログの見出しにページのオリジン
// （IPアドレスやホスト名）を表示してしまい、院内共有端末などでは
// ネットワーク情報が意図せず見えてしまう。タイトル・本文を自由に
// 指定できる確認モーダルを、window.confirmDialog() として提供する。
//
// 使い方（JSから直接呼ぶ場合）:
//   const ok = await confirmDialog({ title: "ラベルを割り当て", message: "..." });
//   if (!ok) return;
//
// 使い方（<form>の送信前に確認したい場合）:
//   <form data-confirm-title="退院処理" data-confirm-message="この患者を退院処理しますか？">
//   のように data-confirm-message を付けるだけで、このファイルが
//   submit イベントを横取りしてモーダル確認後に送信する
//   （onsubmit="return confirm(...)" の代わりに使う）。
//
// 【フォームのfetch送信】
// このアプリはhttp（未暗号化）で運用しているため、<form>をブラウザの
// ネイティブ機能でそのまま送信すると、Safari/Chrome(iOS版はどちらも
// AppleのWebKitエンジン)が「送信しようとしている情報は保護されません」
// という警告を表示する。これはページ側のコードでは止められない
// ブラウザ本体の仕様（<form>のページ遷移としての送信にのみ働く）。
// 通信が暗号化されない点は変わらないが、fetch()でPOSTしてから
// リダイレクト先へ location.href で移動すれば、この警告は出ない。
// window.submitFormViaFetch(form, submitter) として提供する。

(function () {
  let modalEl = null;

  function ensureModal() {
    if (modalEl) return modalEl;

    modalEl = document.createElement("div");
    modalEl.className = "confirm-modal-overlay";
    modalEl.innerHTML = `
      <div class="confirm-modal" role="alertdialog" aria-modal="true" aria-labelledby="confirmModalTitle" aria-describedby="confirmModalMessage">
        <h2 id="confirmModalTitle" class="confirm-modal-title"></h2>
        <p id="confirmModalMessage" class="confirm-modal-message"></p>
        <div class="confirm-modal-actions">
          <button type="button" class="confirm-modal-cancel"></button>
          <button type="button" class="confirm-modal-ok"></button>
        </div>
      </div>
    `;
    document.body.appendChild(modalEl);
    return modalEl;
  }

  window.confirmDialog = function ({ title = "確認", message = "", okLabel = "OK", cancelLabel = "キャンセル" } = {}) {
    const modal = ensureModal();
    modal.querySelector(".confirm-modal-title").textContent = title;
    modal.querySelector(".confirm-modal-message").textContent = message;

    const okBtn = modal.querySelector(".confirm-modal-ok");
    const cancelBtn = modal.querySelector(".confirm-modal-cancel");
    okBtn.textContent = okLabel;
    cancelBtn.textContent = cancelLabel;

    modal.classList.add("is-visible");

    return new Promise((resolve) => {
      function cleanup(result) {
        modal.classList.remove("is-visible");
        okBtn.removeEventListener("click", onOk);
        cancelBtn.removeEventListener("click", onCancel);
        modal.removeEventListener("click", onOverlayClick);
        resolve(result);
      }
      function onOk() { cleanup(true); }
      function onCancel() { cleanup(false); }
      function onOverlayClick(event) {
        if (event.target === modal) cleanup(false);
      }

      okBtn.addEventListener("click", onOk);
      cancelBtn.addEventListener("click", onCancel);
      modal.addEventListener("click", onOverlayClick);
    });
  };

  // <form>をネイティブ送信の代わりにfetch()で送信し、レスポンス（サーバー側の
  // header('Location: ...')リダイレクトをfetchが辿った後の最終URL）へ遷移する。
  // submitter（実際に押された <button type="submit" name=... value=...>）は
  // FormDataに自動で乗らないため、渡された場合は明示的に追加する。
  window.submitFormViaFetch = async function (form, submitter) {
    const formData = new FormData(form);
    if (submitter && submitter.name) {
      formData.append(submitter.name, submitter.value);
    }

    try {
      const response = await fetch(form.getAttribute("action") || location.href, {
        method: (form.getAttribute("method") || "POST").toUpperCase(),
        body: formData,
      });
      location.href = response.url;
    } catch (error) {
      console.error("フォームの送信に失敗しました。", error);
      alert("通信エラーが発生しました。もう一度お試しください。");
    }
  };

  // data-confirm-message を持つ <form> の送信を横取りし、モーダルで
  // 確認が取れてからfetchで送信する。
  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.dataset.confirmMessage) return;

    event.preventDefault();
    const submitter = event.submitter;

    confirmDialog({
      title: form.dataset.confirmTitle || "確認",
      message: form.dataset.confirmMessage,
    }).then((ok) => {
      if (!ok) return;
      submitFormViaFetch(form, submitter);
    });
  });
})();
