const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const listeners = new Map();
global.window = {
  location: { pathname: '/_preview/search3-local-candidate/poisk-turov/', search: '' },
  addEventListener(type, fn) { listeners.set(type, fn); },
};
global.document = { title: 'Поиск туров онлайн — AnyTour', getElementById() { return null; } };

vm.runInThisContext(fs.readFileSync(require.resolve('../v2/search3-hotel-details-presentation-v1.js'), 'utf8'), { filename: 'search3-hotel-details-presentation-v1.js' });
const api = window.Search3HotelDetailsPresentationV1;
assert.ok(api, 'presentation owner is available');

function fixture({ summary = 'Проверенное описание', duplicate = summary, extras = 1 } = {}) {
  let duplicateRemoved = false;
  let detailsRemoved = false;
  const duplicateNode = { textContent: duplicate, remove() { duplicateRemoved = true; } };
  const extraNodes = Array.from({ length: extras }, () => ({}));
  const content = {
    textContent: duplicate,
    get children() { return duplicateRemoved ? extraNodes : [duplicateNode, ...extraNodes]; },
    querySelector(selector) { return selector === '.hotel-description' && !duplicateRemoved ? duplicateNode : null; },
  };
  const details = {
    querySelector(selector) { return selector === '.hotel-details-content' ? content : null; },
    remove() { detailsRemoved = true; },
  };
  const summaryNode = { textContent: summary };
  const card = {
    querySelector(selector) {
      if (selector === '.hotel-description-summary') return summaryNode;
      if (selector === '.hotel-details') return details;
      return null;
    },
  };
  return { card, duplicateRemoved: () => duplicateRemoved, detailsRemoved: () => detailsRemoved };
}

const rich = fixture({ extras: 2 });
assert.equal(api.normalizeCard(rich.card), true, 'identical description is deduplicated');
assert.equal(rich.duplicateRemoved(), true, 'duplicate paragraph is removed from disclosure');
assert.equal(rich.detailsRemoved(), false, 'disclosure remains when structured hotel facts remain');
assert.equal(api.normalizeCard(rich.card), false, 'rich description normalization is idempotent');

const descriptionOnly = fixture({ extras: 0 });
assert.equal(api.normalizeCard(descriptionOnly.card), false, 'description-only disclosure keeps the native full-text owner');
assert.equal(descriptionOnly.duplicateRemoved(), false, 'full text is retained inside the disclosure instead of a permanently clamped teaser');
assert.equal(descriptionOnly.detailsRemoved(), false, 'native disclosure also remains available for mobile gallery controls');
assert.equal(api.normalizeCard(descriptionOnly.card), false, 'description-only repeated normalization is idempotent');

const mismatched = fixture({ duplicate: 'Другое содержимое', extras: 0 });
assert.equal(api.normalizeCard(mismatched.card), false, 'non-identical detail text is never discarded');
assert.equal(mismatched.duplicateRemoved(), false);
assert.equal(mismatched.detailsRemoved(), false);

function ratingFixture(label, canonical = true) {
  const classes = new Set(['hotel-decision-rating']);
  const attributes = {};
  const rating = {
    textContent: label,
    classList: { add(name) { classes.add(name); }, remove(name) { classes.delete(name); } },
    setAttribute(name, value) { attributes[name] = value; },
  };
  const card = { dataset: canonical ? { anytourHotelId: '1' } : {}, querySelector(selector) { return selector === '.hotel-decision-rating' ? rating : null; } };
  return { card, rating, classes, attributes };
}

const ambiguousRating = ratingFixture('Рейтинг 2.1');
assert.equal(api.normalizeCard(ambiguousRating.card), true, 'ambiguous numeric rating is normalized');
assert.equal(ambiguousRating.rating.textContent, 'Каталожная оценка 2,1 · шкала не указана');
assert.equal(ambiguousRating.classes.has('hotel-decision-rating'), false, 'ambiguous rating cannot retain positive badge styling');
assert.equal(ambiguousRating.classes.has('hotel-decision-rating-unscaled'), true, 'neutral unscaled state is explicit');
assert.equal(ambiguousRating.attributes['data-rating-semantics'], 'unscaled');
assert.equal(ambiguousRating.attributes['aria-label'], 'Каталожная оценка 2,1. Источник, шкала и число отзывов не указаны.');

const structuredRating = ratingFixture('Оценка 8,6 из 10 · 120 отзывов');
assert.equal(api.normalizeCard(structuredRating.card), false, 'future structured rating copy is never guessed or rewritten');
assert.equal(structuredRating.rating.textContent, 'Оценка 8,6 из 10 · 120 отзывов');
assert.equal(structuredRating.classes.has('hotel-decision-rating'), true);

const nonCanonicalRating = ratingFixture('Рейтинг 4,8', false);
assert.equal(api.normalizeCard(nonCanonicalRating.card), false, 'presentation correction requires accepted AnyTour identity');
assert.equal(nonCanonicalRating.rating.textContent, 'Рейтинг 4,8');

function titleCard(anytourId, name, canonical = true) {
  return {
    dataset: canonical ? { anytourHotelId: String(anytourId) } : {},
    querySelector(selector) { return selector === '.hotel-title' ? { textContent: name } : null; },
  };
}
function titleRoot(cards) {
  return { querySelectorAll(selector) { return selector === '.hotel-card[data-anytour-hotel-id]' ? cards : []; } };
}
window.location.search = '?from=1&country=4&search3_hotel=3217&search3_search=13656954932';
assert.equal(api.syncHotelPageTitle(titleRoot([titleCard(3217, 'CARUS CAPPADOCIA')])), true, 'exact hotel URL receives a useful tab title');
assert.equal(document.title, 'CARUS CAPPADOCIA — туры AnyTour');
assert.equal(api.syncHotelPageTitle(titleRoot([titleCard(3217, 'CARUS CAPPADOCIA'), titleCard(9999, 'Другой отель')])), false, 'mixed result cards cannot name an exact hotel tab');
assert.equal(document.title, 'Поиск туров онлайн — AnyTour', 'mixed result cards restore the base title');
assert.equal(api.syncHotelPageTitle(titleRoot([titleCard(9999, 'Другой отель')])), false, 'foreign card cannot name the exact hotel tab');
assert.equal(document.title, 'Поиск туров онлайн — AnyTour', 'missing exact identity restores the base title');
window.location.search = '';
assert.equal(api.syncHotelPageTitle(titleRoot([titleCard(3217, 'CARUS CAPPADOCIA')])), false, 'normal result pages keep the generic search title');
assert.equal(document.title, 'Поиск туров онлайн — AnyTour');

window.location.pathname = '/_preview/search3-site-candidate/poisk-turov/';
window.location.search = '?search3_hotel=3217';
const otherPreview = fixture({ extras: 0 });
assert.equal(api.normalizeCard(otherPreview.card), false, 'owner is isolated to local-candidate');
assert.equal(otherPreview.duplicateRemoved(), false);

const otherPreviewRating = ratingFixture('Рейтинг 4,8');
assert.equal(api.normalizeCard(otherPreviewRating.card), false, 'rating correction is isolated to local-candidate');
assert.equal(otherPreviewRating.rating.textContent, 'Рейтинг 4,8');

assert.equal(api.syncHotelPageTitle(titleRoot([titleCard(3217, 'CARUS CAPPADOCIA')])), false, 'hotel title correction is isolated to local-candidate');
assert.equal(document.title, 'Поиск туров онлайн — AnyTour');

console.log('SEARCH3_CANONICAL_DESCRIPTION_DEDUPE_OK rich=1 description_only=1 rating_unscaled=1 hotel_tab_title=1 route_isolated=1');

// Minimal DOM fixture keeps native anchor semantics without a browser dependency.
class TitleNode {
  constructor(tag = '', value = '') { this.tagName = tag.toUpperCase(); this.value = value; this.children = []; this.attributes = {}; this.className = ''; }
  get firstChild() { return this.children[0] || null; }
  get textContent() { return this.value + this.children.map(node => node.textContent).join(''); }
  appendChild(node) { if (node.parentNode) node.parentNode.removeChild(node); this.children.push(node); node.parentNode = this; return node; }
  insertBefore(node, before) { if (node.parentNode) node.parentNode.removeChild(node); this.children.splice(this.children.indexOf(before), 0, node); node.parentNode = this; return node; }
  removeChild(node) { this.children.splice(this.children.indexOf(node), 1); node.parentNode = null; return node; }
  setAttribute(key, value) { this.attributes[key] = value; }
  getAttribute(key) { return this.attributes[key] ?? null; }
  querySelector(selector) { return this.children.find(node => node.tagName === 'A' && (selector === 'a' || node.className === 'search3-hotel-title-link')) || null; }
}
document.createElement = tag => new TitleNode(tag);
const path = '/_preview/search3-local-candidate/poisk-turov/';
window.location = new URL('https://fixture.invalid' + path + '?from=1&country=4');
const href = 'https://fixture.invalid' + path + '?from=1&country=4&search3_hotel=3217&search3_search=731';
function linkFixture(url = href, id = '3217') {
  const title = new TitleNode('h3'), name = new TitleNode('', 'Тестовый & <отель>');
  title.appendChild(name);
  const action = new TitleNode('a'); action.setAttribute('href', url);
  const card = { dataset: { anytourHotelId: id }, querySelector(selector) { return selector === '.hotel-title' ? title : selector === 'a.tour-more-toggle[target="_blank"]' ? action : null; } };
  return { card, title, name, action };
}
const linked = linkFixture();
assert.equal(api.normalizeHotelTitleLink(linked.card), true, 'accepted canonical title becomes a native link');
const anchor = linked.title.querySelector('a');
assert.equal(anchor.getAttribute('href'), href, 'exact existing action URL is reused');
assert.equal(anchor.getAttribute('target'), '_blank');
assert.equal(anchor.getAttribute('rel'), 'noopener');
assert.equal(anchor.getAttribute('aria-label'), 'Тестовый & <отель> — откроется в новой вкладке');
assert.equal(anchor.firstChild, linked.name, 'original text node is preserved, never parsed as HTML');
assert.equal(api.normalizeHotelTitleLink(linked.card), false, 'normalization is idempotent');
linked.action.setAttribute('href', href.replace('731', '732'));
assert.equal(api.normalizeHotelTitleLink(linked.card), true, 'updated canonical action is mirrored without nesting');
assert.equal(linked.title.querySelector('a'), anchor);
assert.equal(anchor.getAttribute('href'), href.replace('731', '732'));
linked.action.setAttribute('href', href.replace('3217', '9999'));
assert.equal(api.normalizeHotelTitleLink(linked.card), true, 'mismatched identity revokes an existing title link');
assert.equal(linked.title.firstChild, linked.name);
assert.equal(linked.title.querySelector('a'), null);

for (const url of [
  '', 'javascript:alert(1)', 'https://other.invalid' + path + '?search3_hotel=3217&search3_search=731',
  href.replace('https:', 'http:'), href.replace('fixture.invalid', 'user:password@fixture.invalid'),
  href.replace('search3-local-candidate', 'search3-site-candidate'), href.replace('3217', '9999'),
  href.replace('&search3_search=731', ''), href.replace('731', 'invalid'),
  href + '&search3_hotel=9999', href + '&search3_search=732',
]) {
  const item = linkFixture(url);
  assert.equal(api.normalizeHotelTitleLink(item.card), false, 'unsupported URL remains plain text: ' + url);
  assert.equal(item.title.querySelector('a'), null);
}
assert.equal(api.normalizeHotelTitleLink(linkFixture(href, '').card), false, 'canonical identity is required');
assert.equal(api.hotelTitleHref(linkFixture(path + '?search3_hotel=3217&search3_search=731').card), path + '?search3_hotel=3217&search3_search=731', 'native relative URL remains unchanged');
const custom = linkFixture();
const customLink = new TitleNode('a'); custom.title.appendChild(customLink);
assert.equal(api.normalizeHotelTitleLink(custom.card), false, 'an existing custom title link is never replaced');
window.V2SearchLifecycle = { dirty: true };
assert.equal(api.normalizeHotelTitleLink(linkFixture().card), false, 'dirty search cannot acquire title links');
delete window.V2SearchLifecycle;
window.location.search = '?search3_hotel=3217&search3_search=731';
assert.equal(api.normalizeHotelTitleLink(linkFixture().card), false, 'direct hotel view does not link to another copy of itself');
window.location.search = '';
for (const event of ['v2:search-reset', 'v2:search-started']) {
  const item = linkFixture(); api.normalizeHotelTitleLink(item.card);
  document.getElementById = () => ({ querySelectorAll: () => [item.title.querySelector('a')] });
  listeners.get(event)({ detail: { dirty: true } });
  assert.equal(item.title.querySelector('a'), null, event + ' revokes added title navigation');
  assert.equal(item.title.firstChild, item.name, event + ' keeps original heading content');
}
window.location.pathname = '/_preview/search3-site-candidate/poisk-turov/';
assert.equal(api.normalizeHotelTitleLink(linkFixture().card), false, 'title links cannot leak into another preview');
console.log('SEARCH3_HOTEL_NAME_LINK_OK exact_url=1 native_anchor=1 idempotent=1 invalidation=1 route_isolated=1');

// Main-image navigation is independent of tour/search/price authority.
window.location = new URL('https://fixture.invalid' + path);
function photoFixture(src = 'https://images.example/hotel/a.jpg', id = '3217') {
  const gallery = new TitleNode('div'), main = new TitleNode('img');
  main.setAttribute('src', src); main.setAttribute('alt', 'Фото отеля Тестовый & <отель>');
  gallery.appendChild(main);
  gallery.querySelector = selector => selector === 'img.hotel-gallery-main' ? main
    : gallery.children.find(node => node.className === 'search3-hotel-photo-link') || null;
  const card = {dataset: {anytourHotelId:id}, querySelector: selector => selector === '.hotel-gallery' ? gallery : null};
  gallery.closest = () => card;
  return {card, gallery, main, link: () => gallery.querySelector('a.search3-hotel-photo-link')};
}
const photo = photoFixture();
assert.equal(api.normalizeHotelPhotoLink(photo.card), true);
assert.equal(photo.link().getAttribute('href'), photo.main.getAttribute('src'), 'exact displayed image URL is linked');
assert.equal(photo.link().getAttribute('target'), '_blank', 'search page remains open');
assert.equal(photo.link().getAttribute('rel'), 'noopener noreferrer');
assert.equal(photo.link().getAttribute('aria-label'), 'Фото отеля Тестовый & <отель> — открыть в новой вкладке');
assert.equal(photo.gallery.firstChild, photo.main, 'original image and thumbnail owner are not replaced');
assert.equal(api.normalizeHotelPhotoLink(photo.card), false, 'photo normalization is idempotent');
window.HTMLDialogElement = function() {};
window.HTMLDialogElement.prototype.showModal = function() {};
assert.equal(api.normalizeHotelPhotoLink(photo.card), true, 'native dialog support advertises the in-page viewer');
assert.equal(photo.link().getAttribute('aria-haspopup'), 'dialog');
assert.equal(photo.link().getAttribute('aria-label'), 'Фото отеля Тестовый & <отель> — смотреть фотографии');
assert.equal(photo.link().getAttribute('href'), photo.main.getAttribute('src'), 'enhancement preserves the exact native fallback URL');
delete window.HTMLDialogElement;
assert.equal(api.normalizeHotelPhotoLink(photo.card), true, 'unsupported browsers retain original-image navigation');
const photoAnchor = photo.link();
for (const event of ['click', 'auxclick', 'contextmenu', 'focusin']) {
  const next = 'https://images.example/hotel/' + event + '.jpg?size=original';
  photo.main.setAttribute('src', next);
  listeners.get(event)({target:{closest: selector => selector === '#results .hotel-gallery' ? photo.gallery : null}});
  assert.equal(photo.link(), photoAnchor, event + ' updates the single existing anchor');
  assert.equal(photo.link().getAttribute('href'), next, event + ' follows the current image without URL rewriting');
}
for (const src of ['', 'javascript:alert(1)', 'data:image/svg+xml,<svg/>', 'http://images.example/a.jpg',
  '//images.example/a.jpg', '/a.jpg', 'https://user:password@images.example/a.jpg',
  'https://images.example/a.jpg\n', 'https://images.example/a b.jpg', 'https://images.example/\u0000.jpg']) {
  const item = photoFixture(src);
  assert.equal(api.normalizeHotelPhotoLink(item.card), false, 'unsupported navigation source is not linked: ' + JSON.stringify(src));
  assert.equal(item.link(), null);
}
photo.main.setAttribute('src', 'data:image/png;base64,AAAA');
assert.equal(api.normalizeHotelPhotoLink(photo.card), true, 'a changed unsupported source revokes the previous link');
assert.equal(photo.link(), null, 'old image navigation cannot remain attached');
for (const id of ['', '0', '-1', 'abc']) assert.equal(api.normalizeHotelPhotoLink(photoFixture(undefined, id).card), false);
window.V2SearchLifecycle = {dirty:true};
assert.equal(api.normalizeHotelPhotoLink(photoFixture().card), true, 'viewing an existing hotel photo grants no tour selection authority');
delete window.V2SearchLifecycle;
window.location.search = '?search3_hotel=3217&search3_search=731';
assert.equal(api.normalizeHotelPhotoLink(photoFixture().card), true, 'photo also opens in the direct hotel view');
for (const pathname of ['/poisk-turov/', '/_preview/search3-site-candidate/poisk-turov/']) {
  window.location.pathname = pathname;
  assert.equal(api.normalizeHotelPhotoLink(photoFixture().card), false, 'photo behavior stays inside the local candidate');
}
console.log('SEARCH3_HOTEL_PHOTO_LINK_OK current_image=1 native_anchor=1 unsupported_source_rejected=1 route_isolated=1');
