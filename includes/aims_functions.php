<?php
/**
 * AIMS (SoluM ESL) 連携用の共通関数
 * 配置先: includes/aims_functions.php
 */

/**
 * ESL上に表示するピクトグラムの最大枠数。
 * DATA_12〜DATA_(11+N)にピクトグラム画像番号(pictogram_id)、
 * DATA_18〜DATA_(17+N)に対応するピクトグラム名(name)を1個ずつ展開する。
 * この2つの範囲が重ならないよう、6を超える値には変更しないこと
 * (7以上にするとID側とname側のDATA番号が衝突する)。
 * 件数が枠数に満たない場合、IDは"0"、nameは空文字列で埋めて送るため、
 * AIMS側に前回配信時の古い値が残り続けることはない。
 */
const AIMS_PICTOGRAM_SLOT_COUNT = 6;

/**
 * 患者情報(patientsテーブルのレコード + doctor_name/primary_nurse)を
 * AIMS Article API (POST /articles) の dataList 要素1件分に変換する。
 *
 * data配下のDATA_1〜のフィールド割り当ては、AIMS側のラベルテンプレート
 * (Template Designerで作成する表示レイアウト)にどのフィールドを
 * バインドしたかによって意味が変わる。ここでの割り当ては仮のものなので、
 * 実際に使うテンプレートを作成した後、テンプレート側の項目名と
 * 突き合わせて調整すること。
 */
function patientToAimsArticlePayload(array $patient): array {
    $riskLabel = !empty($patient['fall_risk'])
        ? '転倒危険度' . (int)$patient['fall_risk']
        : '転倒危険度0';

    $data = [
        'Product_ID' => $patient['patient_id'],
        'Product_Name' => $patient['patient_name'],
        'DATA_1' => $patient['room_no'],
        'DATA_2' => $patient['bed_no'],
        'DATA_3' => $patient['gender'] ?? '',
        'DATA_4' => (string)($patient['age'] ?? ''),
        'DATA_5' => $patient['doctor_name'] ?? '',
        'DATA_6' => $patient['primary_nurse'] ?? '',
        'DATA_7' => $riskLabel,
        'DATA_8' => $patient['transfer_type'] ?? '',
        'DATA_9' => empty($patient['qr_url']) ? '' : BCARE_BASE_URL . '/' . ltrim($patient['qr_url'], '/'),
        'DATA_10' => $patient['pictogram_names'] ?? '',
        'DATA_11' => ((int)($patient['dup_count'] ?? 0) > 0 || (int)($patient['has_namesake'] ?? 0) === 1) ? '同姓同名有' : '',
        'DATA_24' => $patient['patient_kana'] ?? '',
    ];

    /*
     * ピクトグラムはESL上では最大6個まで表示する。
     *   - DATA_12〜DATA_17: ピクトグラム画像番号(pictogram_id)を1個ずつ、最大6個分
     *   - DATA_18〜DATA_23: 対応するピクトグラムのname(表示名)
     *     DATA_12の画像名 → DATA_18 / DATA_13の画像名 → DATA_19 / DATA_14の画像名 → DATA_20
     *     DATA_15の画像名 → DATA_21 / DATA_16の画像名 → DATA_22 / DATA_17の画像名 → DATA_23
     * pictogram_ids・pictogram_namesは表示順(display_order)で対応するインデックスが一致する
     * 前提(呼び出し元のGROUP_CONCAT ... ORDER BY pp.display_orderが両方に揃っていること)。
     */
    $pictogramIds = array_values(array_filter(explode(',', $patient['pictogram_ids'] ?? ''), static function ($id) {
        return $id !== '';
    }));
    $pictogramNameList = array_values(array_filter(explode('、', $patient['pictogram_names'] ?? ''), static function ($name) {
        return $name !== '';
    }));
    for ($i = 0; $i < AIMS_PICTOGRAM_SLOT_COUNT; $i++) {
        $data['DATA_' . (12 + $i)] = $pictogramIds[$i] ?? '0';
        $data['DATA_' . (18 + $i)] = $pictogramNameList[$i] ?? '';
    }

    return [
        'id' => $patient['patient_id'],
        'name' => $patient['patient_name'] . '様の画面',
        'nfc' => '',
        'stationCode' => AIMS_STATION_CODE,
        'reservedOne' => $patient['patient_name'],
        'reservedTwo' => 'manager/patient_detail.php?patient_id=' . $patient['patient_id'],
        'reservedThree' => $patient['ward_name'],
        'data' => $data,
    ];
}

/**
 * 複数患者分の変換結果を POST /articles のリクエストボディ形式にまとめる。
 */
function patientsToAimsArticlesRequestBody(array $patients): array {
    $dataList = [];
    foreach ($patients as $patient) {
        $dataList[] = patientToAimsArticlePayload($patient);
    }
    return ['dataList' => $dataList];
}

/**
 * AIMSへのHTTPリクエストを実行する共通関数。
 * @return array{httpCode:int, body:?array, rawBody:string, error:string}
 */
function aimsRequest(string $method, string $path, ?array $jsonBody = null): array {
    $url = AIMS_BASE_URL . $path;

    $headers = ['Accept: application/json'];
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
    ];
    if ($jsonBody !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($jsonBody, JSON_UNESCAPED_UNICODE);
        $headers[] = 'Content-Type: application/json';
        $options[CURLOPT_HTTPHEADER] = $headers;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, $options);
    $rawBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $body = null;
    if ($rawBody !== false && $rawBody !== '') {
        $decoded = json_decode($rawBody, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $body = $decoded;
        }
    }

    return [
        'httpCode' => $httpCode,
        'body' => $body,
        'rawBody' => (string)$rawBody,
        'error' => $error,
    ];
}

/**
 * ESLラベル一覧を取得する(GET /labels)。
 */
function getAimsLabels(): array {
    return aimsRequest('GET', '/labels?' . http_build_query([
        'stationCode' => AIMS_STATION_CODE,
        'page' => 0,
        'size' => 200,
    ]));
}

/**
 * ラベル状態のサマリーを取得する(GET /labels/summary)。
 */
function getAimsLabelsSummary(): array {
    return aimsRequest('GET', '/labels/summary?' . http_build_query([
        'stationCode' => AIMS_STATION_CODE,
    ]));
}

/**
 * 全ラベルのAlive状態(オンライン/オフライン)をAIMS側に再チェックさせる(PUT /labels/alive)。
 */
function refreshAimsLabelsAlive(): array {
    return aimsRequest('PUT', '/labels/alive?' . http_build_query([
        'stationCode' => AIMS_STATION_CODE,
    ]));
}

/**
 * 患者(Article)を指定のESLラベル(labelCode)に紐付けて配信する。
 * POST /labels/link/article/{stationCode} を使用し、Article登録と
 * ラベルへの紐付け・配信を1回で行う（STEP5のPOST /articlesとは別経路）。
 *
 * templateNameは省略可能（AIMS側スキーマ上required外）だが、省略すると
 * ラベルに元々割り当てられていた別テンプレートがそのまま使われてしまい、
 * 患者名が表示されない不具合があったため、AIMS_TEMPLATE_NAMEで明示的に指定する。
 */
function linkPatientArticleToLabel(array $patient, string $labelCode): array {
    $article = patientToAimsArticlePayload($patient);

    $requestBody = [
        'dataList' => [$article],
        'labelCode' => $labelCode,
        'templateName' => AIMS_TEMPLATE_NAME,
    ];

    return aimsRequest('POST', '/labels/link/article/' . AIMS_STATION_CODE, $requestBody);
}

/**
 * ラベルとArticleの紐付けを解除し、ラベル本体の表示もクリアする(APIガイド 3.3.18)。
 * POST /labels/unlink?labelCode={labelcode}
 */
function unlinkArticleFromLabel(string $labelCode): array {
    return aimsRequest('POST', '/labels/unlink?' . http_build_query(['labelCode' => $labelCode]));
}

/**
 * 患者のESLラベル割当・解除処理（Manager/Mobile共通）。
 * 配置先: includes/aims_functions.php
 *
 * patient_detail.php / esl_management.php (Manager) と
 * sp_esl_label_process.php (Mobile) の両方から呼び出す共通ロジック。
 * DB上の紐付け更新とAIMSへの配信/解除通知までをまとめて行う。
 */

/**
 * ESL配信ペイロード作成に必要な患者情報一式を、doctor_name/primary_nurse/
 * pictogram情報・同姓同名件数も含めて1患者分取得する。
 */
function fetchPatientForEslDelivery(mysqli $mysqli, string $patient_id): ?array {
    $stmt = $mysqli->prepare("
        SELECT p.*, doc.name AS doctor_name, nur.name AS primary_nurse, pic.pictogram_names, pic.pictogram_ids,
            (SELECT COUNT(*) FROM patients p2 WHERE p2.patient_name = p.patient_name AND p2.patient_id != p.patient_id) AS dup_count
        FROM patients p
        LEFT JOIN patients_staff ps_doc ON ps_doc.patient_id = p.patient_id AND ps_doc.role = 'doctor'
        LEFT JOIN staff doc ON doc.staff_id = ps_doc.staff_id
        LEFT JOIN patients_staff ps_nur ON ps_nur.patient_id = p.patient_id AND ps_nur.role = 'nurse'
        LEFT JOIN staff nur ON nur.staff_id = ps_nur.staff_id
        LEFT JOIN (
            SELECT pp.patient_id,
                GROUP_CONCAT(pg.name ORDER BY pp.display_order SEPARATOR '、') AS pictogram_names,
                GROUP_CONCAT(pg.pictogram_id ORDER BY pp.display_order SEPARATOR ',') AS pictogram_ids
            FROM patient_pictograms pp
            JOIN pictograms pg ON pg.pictogram_id = pp.pictogram_id
            GROUP BY pp.patient_id
        ) pic ON pic.patient_id = p.patient_id
        WHERE p.patient_id = ?
    ");
    $stmt->bind_param('s', $patient_id);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $patient;
}

/**
 * 未割当のESLラベルを患者に割り当て、AIMSへ配信する。
 * @return string 'success' | 'deliver_error' | 'error'
 *   - 'error'         : 対象患者が既に別ラベル割当済み、またはそのラベルが他患者で使用中
 *   - 'deliver_error' : DB上の割当は成立したが、AIMSへの配信に失敗した
 */
function assignEslLabelToPatient(mysqli $mysqli, string $patient_id, string $label_code): string {
    // 割当先の患者が未割当であること、かつそのラベルコードが他の患者に
    // 使われていないことの両方を条件にし、同時操作による二重割当を防ぐ
    $stmt = $mysqli->prepare("
        UPDATE patients
        SET esl_label_code = ?, esl_synced_at = NULL
        WHERE patient_id = ?
          AND (esl_label_code IS NULL OR esl_label_code = '')
          AND NOT EXISTS (
              SELECT 1 FROM (SELECT patient_id FROM patients WHERE esl_label_code = ?) AS taken
          )
    ");
    $stmt->bind_param('sss', $label_code, $patient_id, $label_code);
    $stmt->execute();
    $updated = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$updated) {
        return 'error';
    }

    // 割当と同時にAIMSへ配信する。配信に失敗しても割当自体は成立させる。
    $patientForEsl = fetchPatientForEslDelivery($mysqli, $patient_id);
    if ($patientForEsl) {
        $eslResult = linkPatientArticleToLabel($patientForEsl, $label_code);
        if ($eslResult['httpCode'] >= 200 && $eslResult['httpCode'] < 300) {
            $stmtSync = $mysqli->prepare("UPDATE patients SET esl_synced_at = NOW() WHERE patient_id = ?");
            $stmtSync->bind_param('s', $patient_id);
            $stmtSync->execute();
            $stmtSync->close();
        } else {
            return 'deliver_error';
        }
    }
    return 'success';
}

/**
 * 患者からESLラベルの割当を解除し、AIMS側にも解除を通知する。
 * DB側の紐付けは常に解除するが、AIMS側への解除通知(物理ラベル表示のクリア)が
 * 失敗した場合はそれを呼び出し元に伝え、エラー表示できるようにする。
 * @return string 'success' | 'deliver_error'
 *   - 'deliver_error': DB上の解除は成立したが、AIMSへの解除通知に失敗した
 *     (物理ラベルの表示が残ったままの可能性がある)
 */
function unassignEslLabelFromPatient(mysqli $mysqli, string $patient_id): string {
    $stmt = $mysqli->prepare("SELECT esl_label_code FROM patients WHERE patient_id = ?");
    $stmt->bind_param('s', $patient_id);
    $stmt->execute();
    $label_code = $stmt->get_result()->fetch_assoc()['esl_label_code'] ?? null;
    $stmt->close();

    $stmt = $mysqli->prepare("UPDATE patients SET esl_label_code = NULL, esl_synced_at = NULL WHERE patient_id = ?");
    $stmt->bind_param('s', $patient_id);
    $stmt->execute();
    $stmt->close();

    if (!empty($label_code)) {
        $result = unlinkArticleFromLabel($label_code);
        if ($result['httpCode'] < 200 || $result['httpCode'] >= 300) {
            return 'deliver_error';
        }
    }
    return 'success';
}
