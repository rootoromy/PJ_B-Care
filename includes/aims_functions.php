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
