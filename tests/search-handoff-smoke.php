<?php
require __DIR__.'/../v2/form-defaults.php';
require __DIR__.'/../v2/seo-page-primitives-v1.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$state=v2_seo_offer_search_state(['country'=>4,'region'=>22],['departureId'=>1,'departureDate'=>'2099-09-17','nights'=>9]);
parse_str(parse_url(v2_seo_search_handoff_url('/poisk-turov/',$state),PHP_URL_QUERY),$query);
$form=v2_form_defaults($query);
check($form['date_from']==='2099-09-17'&&$form['date_till']==='2099-09-17','Offer date lost');
check($form['nights_from']===9&&$form['nights_till']===9,'Offer duration lost');
check($query['region']==='22'&&$form['count_people']===2,'Destination/party lost');
$form=v2_form_defaults(['daysFrom'=>'8','daysTill'=>'12','child_age'=>['0','17']]);
check($form['child_ages']===[0,17]&&$form['nights_till']===12,'Family handoff lost');
check(v2_form_defaults(['child_age'=>['18','bad',[]]])['child_ages']===[],'Invalid child age accepted');
echo "SEARCH_HANDOFF_OK\n";
