'use strict';

const pageRefPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

// This report is scoped to a document, not a visit or identity. Each flag is
// binary, so repeated actions cannot increase the shadow signal.
function summarizeEarlyIntent({ pageRef, beforeOffsetMs, searches, flights }) {
  if (!Array.isArray(searches) || !Array.isArray(flights) || searches.length + flights.length > 256) {
    throw new RangeError('Expected at most 256 Search3 observations');
  }
  const scoped = typeof pageRef === 'string' && pageRefPattern.test(pageRef)
    && Number.isSafeInteger(beforeOffsetMs) && beforeOffsetMs >= 0;
  const beforeCutoff = event => {
    const observation = event?.pageObservation;
    return scoped && observation?.pageRef === pageRef && Number.isSafeInteger(observation.offsetMs)
      && observation.offsetMs >= 0 && observation.offsetMs < beforeOffsetMs;
  };
  const searchOrigin = origin => searches.some(event => event?.origin === origin && beforeCutoff(event)
    && Number.isSafeInteger(event.searchId) && event.searchId > 0
    && Number.isSafeInteger(event.generation) && event.generation > 0);
  const flightOrigin = origin => flights.some(event => event?.origin === origin && beforeCutoff(event)
    && Number.isSafeInteger(event.index) && event.index >= 0
    && (typeof event.tour?.id === 'string' ? event.tour.id.trim() !== ''
      : Number.isSafeInteger(event.tour?.id) && event.tour.id > 0));
  const formSubmit = searchOrigin('form_submit');
  const flightControl = flightOrigin('flight_control');
  return {
    rule: 'search3_early_intent_v0',
    scope: scoped ? 'document' : 'unknown',
    intent: formSubmit || flightControl ? 'manual_action_observed' : 'unknown',
    automation: 'unknown',
    evidence: {
      formSubmit, flightControl,
      urlHydration: searchOrigin('url_hydration'),
      defaultFlightAssignment: flightOrigin('default_assignment'),
    },
  };
}

module.exports = { summarizeEarlyIntent };
