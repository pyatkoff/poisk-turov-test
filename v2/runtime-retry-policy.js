(function(root){'use strict';
if(root.V2RetryPolicy)return;
const RETRYABLE_ACTIONS=new Set(['departures','countries','arrivals','regions','subregions','meals','operators','hotel_types','hotel_services','hotels','dates','search_status','search_results','hotel_details','tour','flights','rooms']);
const RETRYABLE_HTTP=new Set([502,503,504]);
function shouldRetry(action,error,attempt){if(Number(attempt||0)>=1||!RETRYABLE_ACTIONS.has(String(action||''))||!error)return false;if(error.code==='NETWORK_ERROR')return true;return error.code==='HTTP_ERROR'&&RETRYABLE_HTTP.has(Number(error.status||0));}
function delayFor(attempt){return Math.min(1000,500*(Number(attempt||0)+1));}
root.V2RetryPolicy={shouldRetry,delayFor,retryableActions:Array.from(RETRYABLE_ACTIONS),version:1};
})(typeof window!=='undefined'?window:globalThis);
