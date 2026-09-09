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
    $html .= '<details open class="description"><summary>Описание отеля</summary><p>' . nl2br(anex_review_value($content['description'] ?? null)) . '</p></details>';
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
    ob_start(); ?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Проверка отелей ANEX — AnyTour</title>
<style nonce="<?= anex_review_escape($nonce) ?>">
*{box-sizing:border-box}body{margin:0;background:#f4f6fa;color:#202c43;font:16px/1.5 system-ui,sans-serif}main{max-width:1440px;padding:24px;margin:auto}h1{font-size:28px;margin:0 0 8px}h2{font-size:21px}h3{font-size:18px}a{color:#2743cb}article,.notice,.filters{background:white;border:1px solid #d7deeb;border-radius:12px;padding:18px;margin:16px 0}.notice{border-left:4px solid #2743cb}.muted,small{color:#59677d}.filters,.actions{display:flex;gap:12px;flex-wrap:wrap;align-items:end}label{display:block}input,select,button{font:inherit;padding:10px;border:1px solid #9ba9bf;border-radius:6px;max-width:100%}button{background:#2743cb;color:white;cursor:pointer}button:disabled{background:#e0e5ed;color:#67758b;cursor:not-allowed}.filters input{width:260px}.list{list-style:none;margin:0;padding:0}.list li{padding:14px 0;border-bottom:1px solid #d7deeb;display:flex;gap:16px;justify-content:space-between}.list a{font-weight:600}.compare{display:grid;grid-template-columns:1fr 1fr;gap:18px}.compare>section,.compare>div,.compare>article{min-width:0}.hotel-comparison{align-items:start}.hotel-comparison>article,.candidate-list>article:first-child{margin-top:0}.gallery{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.gallery a{display:block;min-width:0}.gallery a:first-child{grid-column:1/-1}.gallery img{display:block;width:100%;height:auto;aspect-ratio:4/3;object-fit:cover;border-radius:8px;background:#edf1fb}.gallery a:first-child img{aspect-ratio:16/10}.photo-empty{display:grid;place-items:center;min-height:180px;text-align:center;padding:24px;background:#edf1fb;border-radius:8px;color:#59677d}.photo-caption{font-size:13px}.description{margin:18px 0}details p{overflow-wrap:anywhere}details+details{margin-top:12px}dd{margin:0 0 12px;overflow-wrap:anywhere}dt{font-size:14px;color:#59677d}.confirm{max-width:360px;font-size:14px;margin:10px 0}.confirm input{vertical-align:middle}.badge{background:#edf1fb;padding:3px 8px;border-radius:5px;white-space:nowrap}.actions form{flex:1;min-width:210px}.version{font-size:12px;overflow-wrap:anywhere}nav{display:flex;gap:20px;flex-wrap:wrap}pre{white-space:pre-wrap;overflow-wrap:anywhere;font-size:13px}summary{cursor:pointer}button:focus-visible,a:focus-visible,input:focus-visible,select:focus-visible{outline:3px solid #ff510c;outline-offset:3px}@media(max-width:860px){main{padding:14px}h1{font-size:24px}.compare{grid-template-columns:1fr}.list li{display:block}.filters label,.filters input{width:100%}.actions{align-items:stretch}article{padding:14px}}
</style></head><body><main>
<h1>Проверка отелей ANEX</h1><p class="muted">Сохранённые отели из реальных поисков. Описания и фото каждого источника показаны отдельно.</p>
<?php if (!$write): ?><p class="notice">Режим просмотра. Сохранение решений пока недоступно.</p><?php endif; ?>
<?php if ($message !== ''): ?><p class="notice" role="status"><?= anex_review_escape($message) ?></p><?php endif; ?>
<form class="filters" method="get"><label>Название или ANEX ID<input name="q" maxlength="200" value="<?= anex_review_escape($filters['q'] ?? '') ?>"></label>
<label>Страна<select name="country"><option value="0">Все страны</option><?php foreach ($queue['countries'] as $country): $id = (int)$country['country_id']; ?><option value="<?= $id ?>" <?= (int)($filters['country'] ?? 0) === $id ? 'selected' : '' ?>>ID страны <?= $id ?></option><?php endforeach; ?></select></label>
<label>Статус<select name="status"><?php foreach (['pending'=>'Нерешённые','all'=>'Все','mapped'=>'Сопоставлены','later'=>'Отложены','pair_rejected'=>'Есть отклонённые пары','no_candidates'=>'Нет сохранённых кандидатов'] as $value=>$label): ?><option value="<?= $value ?>" <?= ($filters['status'] ?? 'pending') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><button>Найти</button></form>
<?php if ($detail !== null): ?>
<article><h2><?= anex_review_escape($detail['hotel_name']) ?> · ANEX <?= (int)$detail['anex_hotel_id'] ?></h2>
<p><?= $detail['mapped_id'] === null ? 'Соответствие не принято' : 'Принятое соответствие: AnyTour ' . (int)$detail['mapped_id'] ?>. Частота поисков: <?= (int)$detail['observation']['search_count'] ?>; последнее наблюдение: <?= anex_review_escape($detail['observation']['last_seen_utc']) ?> UTC.</p>
<p class="notice">Показаны сохранённые кандидаты, не новый полный поиск. Сохранено <?= count($detail['candidates']) ?>; найдено на момент обработки: <?= anex_review_value($detail['evidence']['candidate_count'] ?? null) ?>. Ограниченный набор не доказывает отсутствие конкурентов.</p>
<details><summary>Основания проверки</summary><p><?= anex_review_value($detail['evidence']['automated_status'] ?? null) ?> · <?= anex_review_value($detail['evidence']['automated_reason'] ?? null) ?></p>
<p class="muted">Это историческая оценка staging, а не утверждение о свежести всей observed-очереди. Если карточки или кандидатов нет, требуется сохранение дополнительных доказательств — отель остаётся в очереди.</p>
<p class="version">Версия рассмотренных данных: <?= anex_review_escape($detail['version']) ?></p></details><?= anex_review_form($detail, 'later', null, 'Позже', $csrf, $write) ?></article>
<div class="compare hotel-comparison"><article class="anex-card"><h3>ANEX · <?= anex_review_escape($detail['hotel_name']) ?></h3><?= anex_review_saved_content($detail['content'] ?? null, true) ?><details><summary>Названия и данные справочника ANEX</summary><dl><?php foreach (['xml_name'=>'Название XML','xml_alternate_name'=>'Другое название XML','api_name'=>'Название API','api_country'=>'Страна','api_region'=>'Регион','api_town'=>'Курорт','api_address'=>'Адрес','latitude'=>'Широта','longitude'=>'Долгота','checked_at'=>'Данные получены, UTC'] as $key=>$label): ?><dt><?= $label ?></dt><dd><?= anex_review_value($detail['source'][$key] ?? null) ?></dd><?php endforeach; ?><dt>Корпус / секция</dt><dd class="muted">Не представлен отдельным проверенным полем. Это не подтверждает отсутствие отдельного корпуса.</dd></dl></details></article><div class="candidate-list">
<?php if (!$detail['candidates']): ?><p class="notice">Нет сохранённых кандидатов. Одобрение недоступно; отель не считается несовпавшим.</p><?php endif; ?>
<?php foreach ($detail['candidates'] as $candidate): $local = $candidate['current']; $target=(int)$candidate['catalog_hotel_id']; $excluded=false; foreach ($detail['exclusions'] as $pair) if ((int)$pair['catalog_hotel_id'] === $target) $excluded=true; ?>
<article><h3>AnyTour / Tourvisor <?= $target ?> · <?= anex_review_value($local['name'] ?? null) ?></h3>
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
