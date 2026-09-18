const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const listeners = new Map();
global.window = {
  location: { pathname: '/_preview/search3-local-candidate/poisk-turov/' },
  addEventListener(type, fn) { listeners.set(type, fn); },
};
global.document = { getElementById() { return null; } };

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
    querySelector(selector) { return selector === '.hotel-description' ? duplicateNode : null; },
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

const descriptionOnly = fixture({ extras: 0 });
assert.equal(api.normalizeCard(descriptionOnly.card), true, 'description-only card is normalized');
assert.equal(descriptionOnly.duplicateRemoved(), true, 'description remains only in the visible summary');
assert.equal(descriptionOnly.detailsRemoved(), true, 'empty Подробнее disclosure is removed');

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

window.location.pathname = '/_preview/search3-site-candidate/poisk-turov/';
const otherPreview = fixture({ extras: 0 });
assert.equal(api.normalizeCard(otherPreview.card), false, 'owner is isolated to local-candidate');
assert.equal(otherPreview.duplicateRemoved(), false);

const otherPreviewRating = ratingFixture('Рейтинг 4,8');
assert.equal(api.normalizeCard(otherPreviewRating.card), false, 'rating correction is isolated to local-candidate');
assert.equal(otherPreviewRating.rating.textContent, 'Рейтинг 4,8');

console.log('SEARCH3_CANONICAL_DESCRIPTION_DEDUPE_OK rich=1 description_only=1 rating_unscaled=1 structured_preserved=1 route_isolated=1');
