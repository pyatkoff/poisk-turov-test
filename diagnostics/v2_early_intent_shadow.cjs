'use strict';

const pageRefPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const validPageRef = value => typeof value === 'string' && pageRefPattern.test(value);
const validOffset = value => Number.isSafeInteger(value) && value >= 0;
const searchOrigins = new Set(['form_submit', 'url_hydration']);
const flightOrigins = new Set(['flight_control', 'default_assignment']);
const validSearch = event => Number.isSafeInteger(event.searchId) && event.searchId > 0
  && Number.isSafeInteger(event.generation) && event.generation > 0;
const validFlight = event => validOffset(event.index)
  && (typeof event.tour?.id === 'string' ? event.tour.id.trim() !== ''
    : Number.isSafeInteger(event.tour?.id) && event.tour.id > 0);

function retainEarliest(earliest, pageRef, event, origins, validEvent) {
  const observation = event?.pageObservation;
  if (!origins.has(event?.origin) || observation?.pageRef !== pageRef
    || !validOffset(observation.offsetMs) || !validEvent(event)) return;
  const previous = earliest.get(event.origin) ?? Infinity;
  earliest.set(event.origin, Math.min(previous, observation.offsetMs));
}

// This report is scoped to a document. Repeated actions cannot increase it.
function report(earliest, scoped, beforeOffsetMs) {
  const observed = origin => scoped && earliest.get(origin) < beforeOffsetMs;
  const formSubmit = observed('form_submit');
  const flightControl = observed('flight_control');
  return {
    rule: 'search3_early_intent_v0',
    scope: scoped ? 'document' : 'unknown',
    intent: formSubmit || flightControl ? 'manual_action_observed' : 'unknown',
    automation: 'unknown',
    evidence: {
      formSubmit, flightControl,
      urlHydration: observed('url_hydration'),
      defaultFlightAssignment: observed('default_assignment'),
    },
  };
}

function summarizeEarlyIntent({ pageRef, beforeOffsetMs, searches, flights }) {
  if (!Array.isArray(searches) || !Array.isArray(flights) || searches.length + flights.length > 256) {
    throw new RangeError('Expected at most 256 Search3 observations');
  }
  const earliest = new Map();
  const scoped = validPageRef(pageRef) && validOffset(beforeOffsetMs);
  if (scoped) {
    for (const search of searches) retainEarliest(earliest, pageRef, search, searchOrigins, validSearch);
    for (const flight of flights) retainEarliest(earliest, pageRef, flight, flightOrigins, validFlight);
  }
  return report(earliest, scoped, beforeOffsetMs);
}

// Explicit diagnostics opt-in. Retains four offsets and one page reference,
// with no event payloads, persistence or transport.
function observeEarlyIntent(target) {
  const earliest = new Map();
  let pageRef = null;
  const pageObservation = () => target.V2Runtime?.pageObservation?.();
  const record = (event, origins, validEvent) => {
    const current = pageObservation()?.pageRef;
    if (!validPageRef(current)) return;
    if (current !== pageRef) {
      earliest.clear();
      pageRef = current;
    }
    retainEarliest(earliest, pageRef, event.detail, origins, validEvent);
  };
  const search = event => record(event, searchOrigins, validSearch);
  const flight = event => record(event, flightOrigins, validFlight);
  target.addEventListener('v2:search-started', search);
  target.addEventListener('v2:flight-selected', flight);
  return {
    read(beforeOffsetMs) {
      const current = pageObservation();
      const cutoff = beforeOffsetMs ?? current?.offsetMs;
      const scoped = validPageRef(current?.pageRef) && validOffset(cutoff);
      return report(current?.pageRef === pageRef ? earliest : new Map(), scoped, cutoff);
    },
    stop() {
      target.removeEventListener('v2:search-started', search);
      target.removeEventListener('v2:flight-selected', flight);
    },
  };
}

module.exports = { summarizeEarlyIntent, observeEarlyIntent };
