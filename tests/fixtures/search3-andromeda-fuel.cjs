'use strict';
// Existing server projection: a saved party transport surcharge already included
// in the displayed search price. A verified zero surcharge is valid evidence too.
module.exports=function withFuel(tour,base=tour.price.amount,surcharge='0'){
  return {...tour,base_search_price:{amount:base,currency:'RUB'},search_surcharge:{
    schema_version:1,provider:'andromeda',state:'estimated',
    search_price:{amount:base,currency:'RUB'},
    party_surcharge:{amount:surcharge,currency:'RUB',source:'andromeda_get_flights_transport'},
    search_price_with_surcharge:{...tour.price,source:'derived_search_estimate'},
    surcharge_scope:'party',arithmetic_applied:true,final_price_verified:false
  }};
};
