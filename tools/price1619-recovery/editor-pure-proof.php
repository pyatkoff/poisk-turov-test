<?php
declare(strict_types=1);
function need(bool $v,string $reason):void{if(!$v)throw new RuntimeException($reason);}
function photoSignature(array $snapshot):string{$media=$snapshot['attachments']??[];need(count($media)===1&&($media[0]['type']??'')==='image','expected_one_photo');$m=$media[0];$id=(string)($m['photo_id']??$m['file_unique_id']??'');need($id!==''&&preg_match('/\A[A-Za-z0-9_-]+\z/',$id)===1,'photo_stable_id_missing');return (string)($m['provider']??'').':'.$id;}
function snapshotMatches(array $p,array $snap,array $plan):bool{return ($snap['text']??'')===$plan['text']&&photoSignature($snap)===$plan['photo_signature']&&($snap['post_url']??'')===$plan['post_url']&&ChannelPostIncomingAdapter::containsOnlyButton($snap,ChannelPostButtonTransport::buttonUrl($p),'✈️ Узнать стоимость тура');}
function monitorEditorPriceLink(string $text,string $platform,string $buttonUrl):array {
    $bases=['max'=>'https://max.ru/id9704048781_1_bot','telegram'=>'https://t.me/anyru_bot'];
    need(isset($bases[$platform])&&preg_match('~\A'.preg_quote($bases[$platform],'~').'\?start=p_[A-Za-z0-9_-]{8,60}\z~D',$buttonUrl)===1,'monitor_price_link_target_invalid');
    need(preg_match('//u',$text)===1,'monitor_price_link_text_invalid');
    $count=preg_match_all('/^💰 (От (?:[0-9]{1,3}(?: [0-9]{3})*|[0-9]+)(?:,[0-9]{1,2})? ₽) за двоих$/mu',$text,$matches,PREG_OFFSET_CAPTURE);
    need($count===1&&substr_count($text,'💰')===1,'monitor_price_link_span_invalid');
    [$label,$byteOffset]=$matches[1][0];
    $prefix=substr($text,0,$byteOffset);$suffix=substr($text,$byteOffset+strlen($label));
    $titleEnd=strpos($prefix,"\n");
    need($titleEnd!==false&&$titleEnd>0,'monitor_price_link_title_invalid');
    $escape=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $utf16=static fn(string $v):int=>preg_match_all('/./us',$v)+preg_match_all('/[\x{10000}-\x{10FFFF}]/u',$v);
    $offset=$utf16($prefix);$length=$utf16($label);
    return ['url'=>$buttonUrl,'label'=>$label,'offset'=>$offset,'length'=>$length,
        'telegram_entities'=>[['type'=>'text_link','offset'=>$offset,'length'=>$length,'url'=>$buttonUrl]],
        'max_html'=>'<b>'.$escape(substr($prefix,0,$titleEnd)).'</b>'.$escape(substr($prefix,$titleEnd))
            .'<a href="'.$escape($buttonUrl).'">'.$escape($label).'</a>'.$escape($suffix)];
}
function monitorEditorPriceLinkVerified(string $platform,array $message,string $text,array $link):bool {
    if(!in_array($platform,['max','telegram'],true))return false;
    $actual=$platform==='max'?($message['body']['text']??null):($message['caption']??null);
    $entities=$platform==='max'?($message['body']['markup']??null):($message['caption_entities']??null);
    if($actual!==$text||!is_array($entities)||!array_is_list($entities))return false;
    $seen=0;$start=$link['offset'];$end=$start+$link['length'];
    foreach($entities as $entity){
        if(!is_array($entity))return false;
        if(($entity['type']??null)!==($platform==='max'?'link':'text_link'))continue;
        $from=$entity[$platform==='max'?'from':'offset']??null;$length=$entity['length']??null;
        if(!is_int($from)||!is_int($length)||$from<0||$length<1)return false;
        if($from<$end&&$from+$length>$start){
            if($from!==$start||$length!==$link['length']||($entity['url']??null)!==$link['url'])return false;
            $seen++;
        }
    }
    return $seen===1;
}
