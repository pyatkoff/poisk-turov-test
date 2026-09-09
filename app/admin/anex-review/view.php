<?php
declare(strict_types=1);

function anex_review_escape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function anex_review_value($value): string
{
    return $value === null || $value === '' ? '<span class="muted">Нет сохранённых данных</span>' : anex_review_escape($value);
}

/** Display saved ANEX title/text arrays without changing the source payload or digest. */
function anex_review_description($value, bool $anex): string
{
    $missing = '<p class="muted">Нет сохранённого описания.</p>';
    if (!is_string($value) || trim($value) === '') return $missing;
    $value = trim($value);
    if (!$anex || ($value[0] !== '[' && $value[0] !== '{')) {
        return '<p>' . nl2br(anex_review_escape($value)) . '</p>';
    }
    $unreadable = '<p class="muted">Не удалось прочитать разделы сохранённого описания.</p>';
    if (strlen($value) > 16000) return $unreadable;
    $sections = json_decode($value, true, 8);
    if (!is_array($sections) || $value[0] !== '[' || count($sections) > 32) return $unreadable;
    $html = '';
    foreach ($sections as $section) {
        if (!is_array($section) || !is_string($section['title'] ?? null) || !is_string($section['text'] ?? null)) return $unreadable;
        $title = trim($section['title']);
        $text = trim(str_replace(["\r\n", "\r"], "\n", $section['text']));
        if ($text === '') continue;
        $html .= '<section class="description-section"><h4>' . anex_review_escape($title !== '' ? $title : 'Описание')
            . '</h4><p>' . nl2br(anex_review_escape($text)) . '</p></section>';
    }
    return $html !== '' ? $html : $missing;
}

/** Saved, source-specific HTTPS photos; never derives another hotel's photo URL. */
function anex_review_gallery(array $values, string $source): string
{
    $urls = [];
    foreach (array_slice($values, 0, 80) as $value) {
        $url = is_array($value) ? ($value['url'] ?? null) : $value;
        if (!is_string($url) || strlen($url) > 2048 || preg_match('/[\x00-\x20\\\\]/', $url)) continue;
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || !preg_match('/\A[a-zA-Z0-9.-]+\.[a-zA-Z]{2,63}\z/D', $parts['host'])) continue;
        $urls[$url] = is_array($value) && is_string($value['note'] ?? null) ? $value['note'] : '';
        if (count($urls) === 12) break;
    }
    if (!$urls) return '<p class="photo-empty">В сохранённой карточке ' . anex_review_escape($source) . ' фотографий нет.</p>';
    $html = '<div class="gallery" aria-label="Фотографии ' . anex_review_escape($source) . '">';
    $i = 0;
    foreach ($urls as $url=>$note) {
        $i++;
        $label = $source . ' · ' . ($note !== '' ? $note : 'фото ' . $i);
        $html .= '<a target="_blank" rel="noopener noreferrer" href="' . anex_review_escape($url) . '" aria-label="Открыть: ' . anex_review_escape($label) . '">'
            . '<img src="' . anex_review_escape($url) . '" alt="' . anex_review_escape($label) . '" width="640" height="480" loading="lazy" decoding="async" referrerpolicy="no-referrer"></a>';
    }
    return $html . '</div><p class="muted photo-caption">Фото: ' . anex_review_escape($source) . ' · показано ' . count($urls) . ' (до 12). Нажмите для увеличения.</p>';
}

function anex_review_saved_content(?array $row, bool $anex): string
{
    if ($row === null) return '<p class="muted">Содержательная карточка пока не сохранена.</p>';
    $status = $row['status'] ?? '';
    $html = '<p class="muted">Карточка ' . ($anex ? 'ANEX' : 'Tourvisor') . ' · '
        . anex_review_value($row[$anex ? 'fetched_at_utc' : 'fetched_at'] ?? null) . ' UTC</p>';
    if ($status !== ($anex ? 'ready' : 'success')) return $html . '<p class="muted">Содержание не подтверждено; не используйте его как новое доказательство.</p>';
    $content = $anex ? $row['content'] : $row;
    if (!is_array($content)) return $html;
    $photos = $anex ? ($content['photos'] ?? []) : json_decode((string)($content['images_json'] ?? ''), true);
    $photos = is_array($photos) ? $photos : [];
    if (!$anex && !empty($content['primary_image_url'])) array_unshift($photos, $content['primary_image_url']);
    $html .= anex_review_gallery($photos, $anex ? 'ANEX' : 'Tourvisor');
    $html .= '<dl><dt>Адрес из карточки</dt><dd>' . anex_review_value($content['address'] ?? null) . '</dd>'
        . '<dt>Координаты карточки (отдельно от каталожных)</dt><dd>' . anex_review_value($content['latitude'] ?? null)
        . ', ' . anex_review_value($content['longitude'] ?? null) . '</dd></dl>';
    // Only this inspected ANEX ID is confirmed as an allocation offer; no name heuristic.
    $fortuna = $anex && (int)($content['id'] ?? 0) === 17097;
    if ($fortuna) $html .= '<p class="notice">FORTUNA — предложение без заранее определённого отеля. Текст описывает условия размещения и не подтверждает совпадение с конкретным отелем.</p>';
    $html .= '<details open class="description"><summary>' . ($fortuna ? 'Условия предложения FORTUNA' : 'Описание отеля') . '</summary>'
        . anex_review_description($content['description'] ?? null, $anex) . '</details>';
    if (!empty($row['description_truncated'])) $html .= '<p class="muted">Длинное описание показано частично (до 16 000 символов).</p>';
    if ($anex) {
        foreach (['location'=>'Расположение', 'transfer'=>'Трансфер', 'note'=>'Дополнительная информация'] as $key=>$label) {
            if (!empty($content[$key])) $html .= '<details><summary>' . $label . '</summary><p>' . nl2br(anex_review_escape($content[$key])) . '</p></details>';
        }
        if (!empty($content['attributes']) && is_array($content['attributes'])) {
            $html .= '<details><summary>Характеристики отеля</summary><dl>';
            foreach (array_slice($content['attributes'], 0, 80) as $attribute) {
                if (!is_array($attribute)) continue;
                $html .= '<dt>' . anex_review_escape($attribute['name'] ?? '') . '</dt><dd>' . anex_review_value($attribute['value'] ?? null) . '</dd>';
            }
            $html .= '</dl></details>';
        }
        if (!empty($content['rooms']) && is_array($content['rooms'])) {
            $html .= '<details><summary>Номера</summary><dl>';
            foreach (array_slice($content['rooms'], 0, 30) as $room) {
                if (!is_array($room)) continue;
                $html .= '<dt>' . anex_review_escape($room['name'] ?? '') . '</dt><dd>' . anex_review_value($room['description'] ?? null) . '</dd>';
            }
            $html .= '</dl></details>';
        }
    }
    if (!empty($row['images_truncated'])) $html .= '<p class="muted">Сохранённая галерея превышает лимит чтения; полнота не подтверждается.</p>';
    return $html;
}

/** Owner-only external search links. These helpers never call a provider or accept a mapping. */
function anex_review_tourvisor_integer($value, int $min, int $max): ?int
{
    if ((!is_string($value) && !is_int($value)) || !preg_match('/\A(?:0|[1-9][0-9]{0,8})\z/D', (string)$value)) return null;
    $number = (int)$value;
    return $number >= $min && $number <= $max ? $number : null;
}

function anex_review_tourvisor_criteria(array $input): array
{
    // The owner supplied this example; it is not attributed to a saved search.
    $values = ['tv_date'=>'2026-09-18', 'tv_nights'=>8, 'tv_adults'=>2, 'tv_meal'=>7, 'tv_departure'=>1];
    $errors = [];
    if (array_key_exists('tv_date', $input)) {
        $date = $input['tv_date'];
        if (!is_string($date) || !preg_match('/\A(20[0-9]{2})-([0-9]{2})-([0-9]{2})\z/D', $date, $parts)
            || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])) $errors[] = 'Дата вылета';
        else $values['tv_date'] = $date;
    }
    foreach (['tv_nights'=>[1,60,'Ночи'], 'tv_adults'=>[1,6,'Взрослые'], 'tv_meal'=>[0,99,'ID питания'], 'tv_departure'=>[1,999999999,'ID города вылета']] as $key=>$rule) {
        if (!array_key_exists($key, $input)) continue;
        $number = anex_review_tourvisor_integer($input[$key], $rule[0], $rule[1]);
        if ($number === null) $errors[] = $rule[2];
        else $values[$key] = $number;
    }
    return ['values'=>$values, 'errors'=>$errors];
}

function anex_review_tourvisor_link(array $candidate, array $criteria): ?string
{
    $settings = anex_review_tourvisor_criteria($criteria);
    if ($settings['errors']) return null;
    $local = $candidate['current'] ?? null;
    if (!is_array($local)) return null;
    $target = anex_review_tourvisor_integer($candidate['catalog_hotel_id'] ?? null, 1, 999999999);
    $current = anex_review_tourvisor_integer($local['id'] ?? null, 1, 999999999);
    $country = anex_review_tourvisor_integer($local['country_id'] ?? null, 1, 999999999);
    if ($target === null || $current !== $target || $country === null) return null;
    $v = $settings['values'];
    $date = substr($v['tv_date'], 8, 2) . '.' . substr($v['tv_date'], 5, 2) . '.' . substr($v['tv_date'], 0, 4);
    // Country is from the current local/Tourvisor row, never the ANEX country ID.
    return 'https://tourvisor.ru/search.php?' . http_build_query([
        'ts_dosearch'=>1, 's_form_mode'=>0, 's_nights_from'=>$v['tv_nights'], 's_nights_to'=>$v['tv_nights'],
        's_directflight'=>0, 's_regular'=>1, 'x_hotel_codes'=>$target,
        's_j_date_from'=>$date, 's_j_date_to'=>$date, 's_adults'=>$v['tv_adults'],
        's_meal'=>$v['tv_meal'], 's_flyfrom'=>$v['tv_departure'], 's_country'=>$country,
    ], '', '&', PHP_QUERY_RFC3986);
}

function anex_review_form(array $detail, string $action, ?int $target, string $label, string $csrf, bool $enabled): string
{
    $fields = ['id' => $detail['anex_hotel_id'], 'action' => $action, 'target' => $target ?? '',
        'version' => $detail['version'], 'csrf' => $csrf, 'request_id' => bin2hex(random_bytes(16))];
    $html = '<form method="post">';
    foreach ($fields as $key => $value) $html .= '<input type="hidden" name="' . $key . '" value="' . anex_review_escape($value) . '">';
    if ($action === 'accept') $html .= '<label class="confirm"><input type="checkbox" name="confirm" value="yes" required> Я проверил(а) название, страну и корпус; это один отель</label>';
    return $html . '<button type="submit"' . ($enabled ? '' : ' disabled') . '>' . anex_review_escape($label) . '</button></form>';
}

function anex_review_render(array $queue, ?array $detail, array $filters, string $csrf, bool $write, string $nonce, string $message = ''): string
{
    $tourvisor = anex_review_tourvisor_criteria($filters);
    ob_start(); ?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Проверка отелей ANEX — AnyTour</title>
<style nonce="<?= anex_review_escape($nonce) ?>">
*{box-sizing:border-box}body{margin:0;background:#f4f6fa;color:#202c43;font:16px/1.5 system-ui,sans-serif}main{max-width:1440px;padding:24px;margin:auto}h1{font-size:28px;margin:0 0 8px}h2{font-size:21px}h3{font-size:18px}a{color:#2743cb}article,.notice,.filters{background:white;border:1px solid #d7deeb;border-radius:12px;padding:18px;margin:16px 0}.notice{border-left:4px solid #2743cb}.muted,small{color:#59677d}.filters,.actions{display:flex;gap:12px;flex-wrap:wrap;align-items:end}label{display:block}input,select,button{font:inherit;padding:10px;border:1px solid #9ba9bf;border-radius:6px;max-width:100%}button{background:#2743cb;color:white;cursor:pointer}button:disabled{background:#e0e5ed;color:#67758b;cursor:not-allowed}.filters input{width:260px}.list{list-style:none;margin:0;padding:0}.list li{padding:14px 0;border-bottom:1px solid #d7deeb;display:flex;gap:16px;justify-content:space-between}.list a{font-weight:600}.compare{display:grid;grid-template-columns:1fr 1fr;gap:18px}.compare>section,.compare>div,.compare>article{min-width:0}.hotel-comparison{align-items:start}.hotel-comparison>article,.candidate-list>article:first-child{margin-top:0}.gallery{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.gallery a{display:block;min-width:0}.gallery a:first-child{grid-column:1/-1}.gallery img{display:block;width:100%;height:auto;aspect-ratio:4/3;object-fit:cover;border-radius:8px;background:#edf1fb}.gallery a:first-child img{aspect-ratio:16/10}.photo-empty{display:grid;place-items:center;min-height:180px;text-align:center;padding:24px;background:#edf1fb;border-radius:8px;color:#59677d}.photo-caption{font-size:13px}.description{margin:18px 0}.description-section h4{margin:18px 0 6px;font-size:15px;overflow-wrap:anywhere}.description-section p{margin:0}details p{overflow-wrap:anywhere}details+details{margin-top:12px}dd{margin:0 0 12px;overflow-wrap:anywhere}dt{font-size:14px;color:#59677d}.confirm{max-width:360px;font-size:14px;margin:10px 0}.confirm input{vertical-align:middle}.badge{background:#edf1fb;padding:3px 8px;border-radius:5px;white-space:nowrap}.actions form{flex:1;min-width:210px}.version{font-size:12px;overflow-wrap:anywhere}nav{display:flex;gap:20px;flex-wrap:wrap}pre{white-space:pre-wrap;overflow-wrap:anywhere;font-size:13px}summary{cursor:pointer}button:focus-visible,a:focus-visible,input:focus-visible,select:focus-visible{outline:3px solid #ff510c;outline-offset:3px}@media(max-width:860px){main{padding:14px}h1{font-size:24px}.compare{grid-template-columns:1fr}.list li{display:block}.filters label,.filters input{width:100%}.actions{align-items:stretch}article{padding:14px}}
</style></head><body><main>
<h1>Проверка отелей ANEX</h1><p class="muted">Сохранённые отели из реальных поисков. Описания и фото каждого источника показаны отдельно.</p>
<?php if (!$write): ?><p class="notice">Режим просмотра. Сохранение решений пока недоступно.</p><?php endif; ?>
<?php if ($message !== ''): ?><p class="notice" role="status"><?= anex_review_escape($message) ?></p><?php endif; ?>
<form class="filters" method="get"><?php foreach ($tourvisor['values'] as $key=>$value): ?><input type="hidden" name="<?= $key ?>" value="<?= anex_review_escape($value) ?>"><?php endforeach; ?><label>Название или ANEX ID<input name="q" maxlength="200" value="<?= anex_review_escape($filters['q'] ?? '') ?>"></label>
<label>Страна<select name="country"><option value="0">Все страны</option><?php foreach ($queue['countries'] as $country): $id = (int)$country['country_id']; ?><option value="<?= $id ?>" <?= (int)($filters['country'] ?? 0) === $id ? 'selected' : '' ?>>ID страны <?= $id ?></option><?php endforeach; ?></select></label>
<label>Статус<select name="status"><?php foreach (['pending'=>'Нерешённые','all'=>'Все','mapped'=>'Сопоставлены','later'=>'Отложены','pair_rejected'=>'Есть отклонённые пары','no_candidates'=>'Нет сохранённых кандидатов'] as $value=>$label): ?><option value="<?= $value ?>" <?= ($filters['status'] ?? 'pending') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><button>Найти</button></form>
<article><h2>Проверить кандидата на Tourvisor</h2>
<p class="muted">Начальные условия — из вашего примера, а не из сохранённого поиска: 18.09.2026, 8 ночей, 2 взрослых, питание 7 (AI), вылет 1 (Москва). Их можно изменить ниже. Страна и ID отеля берутся из текущей карточки кандидата Tourvisor.</p>
<?php if ($tourvisor['errors']): ?><p class="notice" role="alert">Исправьте условия ссылок: <?= anex_review_escape(implode(', ', $tourvisor['errors'])) ?>. Ссылки пока недоступны; выберите корректные значения и нажмите «Применить к ссылкам».</p><?php endif; ?>
<form class="filters" method="get" aria-label="Условия внешнего поиска Tourvisor">
<?php foreach (['q','country','status','page'] as $key): if (!isset($filters[$key]) || !is_scalar($filters[$key])) continue; ?><input type="hidden" name="<?= $key ?>" value="<?= anex_review_escape($filters[$key]) ?>"><?php endforeach; ?>
<?php if ($detail !== null): ?><input type="hidden" name="id" value="<?= (int)$detail['anex_hotel_id'] ?>"><?php endif; ?>
<label>Дата вылета<input type="date" name="tv_date" min="2000-01-01" max="2099-12-31" required value="<?= anex_review_escape($tourvisor['values']['tv_date']) ?>"></label>
<?php foreach (['tv_nights'=>['Ночи',1,60], 'tv_adults'=>['Взрослые',1,6], 'tv_meal'=>['ID питания Tourvisor',0,99], 'tv_departure'=>['ID города вылета Tourvisor',1,999999999]] as $key=>$field): ?><label><?= $field[0] ?><input type="number" name="<?= $key ?>" min="<?= $field[1] ?>" max="<?= $field[2] ?>" step="1" required value="<?= $tourvisor['values'][$key] ?>"></label><?php endforeach; ?>
<button type="submit">Применить к ссылкам</button></form>
<p class="muted">Ссылка откроет поиск в новой вкладке с вашей сессией Tourvisor. В выдаче выберите предложение ANEX и проверьте данные оператора. Наличие ID ANEX пока не подтверждено; совпадение автоматически не сохраняется.</p></article>
<?php if ($detail !== null): ?>
<article><h2><?= anex_review_escape($detail['hotel_name']) ?> · ANEX <?= (int)$detail['anex_hotel_id'] ?></h2>
<p><?= $detail['mapped_id'] === null ? 'Соответствие не принято' : 'Принятое соответствие: AnyTour ' . (int)$detail['mapped_id'] ?>. Частота поисков: <?= (int)$detail['observation']['search_count'] ?>; последнее наблюдение: <?= anex_review_escape($detail['observation']['last_seen_utc']) ?> UTC.</p>
<p class="notice">Показаны сохранённые кандидаты, не новый полный поиск. На экране <?= count($detail['candidates']) ?>; найдено на момент обработки: <?= anex_review_value($detail['evidence']['candidate_count'] ?? null) ?>. Ограниченный набор не доказывает отсутствие конкурентов.</p>
<?php if (isset($detail['evidence']['dossier_provenance'])): ?><p class="version">Постоянное досье · artifact <?= (int)$detail['evidence']['dossier_provenance']['artifact_id'] ?> · источник <?= anex_review_escape($detail['evidence']['evidence_origin']) ?> · sha256 <?= anex_review_escape($detail['evidence']['dossier_provenance']['row_digest']) ?>. До 20 кандидатов на экране; полный сохранённый набор остаётся в архиве БД.</p><?php endif; ?>
<details><summary>Основания проверки</summary><p><?= anex_review_value($detail['evidence']['automated_status'] ?? null) ?> · <?= anex_review_value($detail['evidence']['automated_reason'] ?? null) ?></p>
<p class="muted">Это сохранённая оценка, а не новый запрос или утверждение о полноте доказательств. Исторические подсказки не считаются проверенными кандидатами. Если карточки или кандидатов нет, отель остаётся в очереди.</p>
<p class="version">Версия рассмотренных данных: <?= anex_review_escape($detail['version']) ?></p></details><?= anex_review_form($detail, 'later', null, 'Позже', $csrf, $write) ?></article>
<div class="compare hotel-comparison"><article class="anex-card"><h3>ANEX · <?= anex_review_escape($detail['hotel_name']) ?></h3><?php if (!empty($detail['public_card']['url'])): ?><p><a href="<?= anex_review_escape($detail['public_card']['url']) ?>" target="_blank" rel="noopener noreferrer">Официальная карточка ANEX ↗</a></p><?php endif; ?><?= anex_review_saved_content($detail['content'] ?? null, true) ?><details><summary>Названия и данные справочника ANEX</summary><dl><?php foreach (['xml_name'=>'Название XML','xml_alternate_name'=>'Другое название XML','api_name'=>'Название API','api_country'=>'Страна','api_region'=>'Регион','api_town'=>'Курорт','api_address'=>'Адрес','latitude'=>'Широта','longitude'=>'Долгота','checked_at'=>'Данные получены, UTC'] as $key=>$label): ?><dt><?= $label ?></dt><dd><?= anex_review_value($detail['source'][$key] ?? null) ?></dd><?php endforeach; ?><dt>Корпус / секция</dt><dd class="muted">Не представлен отдельным проверенным полем. Это не подтверждает отсутствие отдельного корпуса.</dd></dl></details></article><div class="candidate-list">
<?php if (!$detail['candidates']): ?><p class="notice">Нет сохранённых кандидатов. Одобрение недоступно; отель не считается несовпавшим.</p><?php endif; ?>
<?php foreach ($detail['candidates'] as $candidate): $local = $candidate['current']; $target=(int)$candidate['catalog_hotel_id']; $excluded=false; foreach ($detail['exclusions'] as $pair) if ((int)$pair['catalog_hotel_id'] === $target) $excluded=true; ?>
<article><h3>AnyTour / Tourvisor <?= $target ?> · <?= anex_review_value($local['name'] ?? null) ?></h3>
<?php $tourvisorUrl = anex_review_tourvisor_link($candidate, $filters); if ($tourvisorUrl !== null): ?><p><a href="<?= anex_review_escape($tourvisorUrl) ?>" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">Открыть этот отель на Tourvisor ↗</a></p><?php else: ?><p class="muted">Ссылка Tourvisor недоступна: проверьте условия поиска и наличие актуальных ID отеля и страны в каталоге.</p><?php endif; ?>
<section><?= anex_review_saved_content($candidate['details'] ?? null, false) ?><details><summary>Данные справочника Tourvisor</summary><dl><?php foreach (['country_name'=>'Страна','region_name'=>'Регион','subregion_name'=>'Курорт','category'=>'Категория','latitude'=>'Широта','longitude'=>'Долгота'] as $key=>$label): ?><dt><?= $label ?></dt><dd><?= anex_review_value($local[$key] ?? null) ?></dd><?php endforeach; ?></dl></details></section><details><summary>Почему предложен этот кандидат</summary><dl><?php foreach (['name_similarity'=>'Сходство названия (сохранённое)','distance_m'=>'Расстояние, м (сохранённое)','score'=>'Оценка (сохранённая)'] as $key=>$label): ?><dt><?= $label ?></dt><dd><?= anex_review_value($candidate[$key] ?? null) ?></dd><?php endforeach; ?></dl><details><summary>Сохранённые доказательства кандидата (не новое чтение)</summary><pre><?= anex_review_escape($candidate['candidate_json'] ?? '') ?></pre></details></details>
<?php if ($excluded): ?><p class="notice">Эта пара отклонена. Другие кандидаты остаются доступны.</p><?php endif; ?>
<div class="actions"><?= anex_review_form($detail,'accept',$target,'Одобрить соответствие',$csrf,$write && !$excluded && $local !== null && $detail['manual'] === null && $detail['mapped_id'] === null) ?><?= anex_review_form($detail,'reject_pair',$target,'Отклонить эту пару',$csrf,$write && !$excluded && $detail['mapped_id'] !== $target) ?></div></article>
<?php endforeach; ?>
</div></div>
<article><h3>Журнал решений</h3><?php if ($detail['manual'] !== null): ?><p>Текущее ручное решение: <?= anex_review_escape($detail['manual']['decision_status']) ?> → <?= anex_review_value($detail['manual']['catalog_hotel_id']) ?>; <?= anex_review_escape($detail['manual']['decided_by']) ?>, <?= anex_review_value($detail['manual']['decided_at']) ?>. Перезапись в этой версии панели недоступна.</p><?php endif; ?><?php if (!$detail['audit']): ?><p class="muted">Новых решений панели нет. Ранее принятые решения не изменяются.</p><?php endif; ?><ul><?php foreach ($detail['audit'] as $event): ?><li><?= anex_review_escape($event['decided_at'] . ' UTC · ' . $event['actor'] . ' · ' . $event['action'] . ' → ' . ($event['catalog_hotel_id'] ?? '—')) ?></li><?php endforeach; ?></ul></article>
<?php endif; ?>
<article><h2>Очередь · <?= (int)$queue['total'] ?></h2><p class="muted">По частоте и свежести поисков. 25 отелей на странице.</p><ul class="list"><?php foreach ($queue['items'] as $row): $query=$filters; $query['id']=$row['anex_hotel_id']; ?><li><div><a href="?<?= anex_review_escape(http_build_query($query)) ?>"><?= anex_review_escape($row['hotel_name']) ?></a><br><small>ANEX <?= (int)$row['anex_hotel_id'] ?> · страна <?= (int)$row['country_id'] ?> · поисков <?= (int)$row['search_count'] ?> · <?= anex_review_escape($row['last_seen_utc']) ?> UTC</small></div><span><?= $row['mapped_id'] === null ? 'Нужна проверка' : 'AnyTour ' . (int)$row['mapped_id'] ?><?= !empty($row['deferred_at']) ? ' · Позже' : '' ?></span></li><?php endforeach; ?></ul><?php if (!$queue['items']): ?><p>По этим условиям отелей нет. Это не означает, что весь каталог сопоставлен.</p><?php endif; ?>
<nav aria-label="Страницы очереди"><?php foreach ([-1=>'← Назад',1=>'Далее →'] as $delta=>$label): $page=$queue['page']+$delta; if ($page<1 || $page>$queue['pages']) continue; $query=$filters; unset($query['id']); $query['page']=$page; ?><a href="?<?= anex_review_escape(http_build_query($query)) ?>"><?= $label ?></a><?php endforeach; ?><span>Страница <?= (int)$queue['page'] ?> из <?= (int)$queue['pages'] ?></span></nav></article>
</main></body></html>
<?php return (string)ob_get_clean();
}
