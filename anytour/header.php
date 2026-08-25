<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>
<?
require_once ($_SERVER["DOCUMENT_ROOT"].'/mob.php');
require_once ($_SERVER["DOCUMENT_ROOT"].'/site_conf.php');
$isMain = ($APPLICATION->GetCurDir()=="/") ? true : false;
$isPay = ($APPLICATION->GetCurDir()=="/pay_t/") ? true : false;
$isSPay = ($APPLICATION->GetCurDir()=="/success_pay/") ? true : false;
$isEPay = ($APPLICATION->GetCurDir()=="/error_pay/") ? true : false;
$isTF = ($APPLICATION->GetCurDir()=="/tf/") ? true : false;
$isCountry = (strpos($APPLICATION->GetCurDir(),"/country/")!==false) ? true : false;
$isSearch = (strpos($APPLICATION->GetCurDir(),"/poisk-turov/")!==false) ? true : false;
$isTailand = false;
$isSpecTailand = false;
$isTgSearch = $APPLICATION->GetCurDir()=="/poisk-turov-tg/";

if($isCountry && ($USER->IsAdmin() || $_GET["t"]=="y"))
{
	$isTailand = ($APPLICATION->GetCurDir()=="/country/tailand/") ? true : false;
	if($_GET["t"]=="y")
		$isSpecTailand = true;
}
if  (
		!$isPay && !$isSPay && !$isEPay && !$isTF && 
		SITE_ID!="t1" && SITE_ID!="t2" && SITE_ID!="t3" && SITE_ID!="t4" && SITE_ID!="t5" && 
		SITE_ID!="u1" && SITE_ID!="u2" && SITE_ID!="u3" && SITE_ID!="u4" && SITE_ID!="u5" && 
		SITE_ID!="o1" 
		/*||
		(SITE_ID=="t1" || SITE_ID=="t2" || SITE_ID=="t3" || SITE_ID=="t4" || SITE_ID=="t5") && !$USER->IsAdmin()*/
    )
{	 
	$host = "https://anytour.com";
	if (SITE_ID=="s2")
		$host = "https://spb.anytour.com";
	elseif (SITE_ID=="s3")
		$host = "https://rostov.anytour.com";
	elseif (SITE_ID=="s4")
		$host = "https://samara.anytour.com";	
	elseif (SITE_ID=="s5")
		$host = "https://msk.anytour.com";
	
	
	//die();

	LocalRedirect($host.$_SERVER["REQUEST_URI"],true);
			
	//header('HTTP/1.0 403 Forbidden', true, 403);
	//die();
}	
else
{
	$mainSite = "anytour.com";
	if  (SITE_ID=="u1" || SITE_ID=="u2" || SITE_ID=="u3" || SITE_ID=="u4" || SITE_ID=="u5")
		$mainSite = "anytour.su";
}

?>
<!DOCTYPE html>
<html class="<?=($_SESSION['isAndroid']) ? "bx-android": (($_SESSION['isiOS']) ? : "")?>">
<head>
<meta content="text/html; charset=utf-8" http-equiv=Content-Type>
<link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500&amp;subset=cyrillic-ext" rel="stylesheet">
<?if($isSearch){?>
<link rel="canonical" href="https://<?=$_SERVER["HTTP_HOST"]?>/poisk-turov/" />
<?}?>

<?php
if ($canonical = $APPLICATION->GetPageProperty("canonical")) {
    ?>
    <link rel="canonical" href="<?=htmlspecialcharsbx($canonical)?>" />
    <?php
}
?>
<link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
<link rel="icon" href="/favicon.ico" type="image/x-icon">
<?/*?><link href="/css/tourvisor.css_" rel="stylesheet">
<link href="/css/theme2.css" rel="stylesheet">
<link href="/css/css-core.min.css" rel="stylesheet"><?*/?>
<link href="/css/css_new.css?v<?=time()?>" rel="stylesheet">
<?/*if ($_GET["new_font"]){?>
<?} else {?>
	<link href="/css/css.css?v<?=time()?>" rel="stylesheet">	
<?}*/?>	
<link href="/css/datepicker/datepicker.css" rel="stylesheet">
<link href="/js/fancybox/jquery.fancybox.min.css" rel="stylesheet">
<?//if ($isMain){?>
<link rel="stylesheet" type="text/css" href="/js/slick/slick.css"/>
<link rel="stylesheet" type="text/css" href="/js/slick/slick-theme.css"/>

<?//}?>
<?if ($params["GEORG"]){?>
<link href="/css.css" rel="stylesheet">
<?}?>
<script src="/js/jquery-3.3.1.min.js"></script>
<script src="/js/datepicker/datepicker.min.js"></script>
<script src="/js/fancybox/jquery.fancybox.min.js"></script>
<script src="/js/js.cookie-2.2.0.min.js"></script>
<script src="/js/jquery.mask.min.js"></script>
<script src="/js/slick/slick.min.js"></script>
<script src="/js/interested.js"></script>
<script src="/js/script.js?v10"></script>

<?if($isSpecTailand){?>
	<?/*?><script src="/js/marquiz-thai.js"></script><?*/?>
<?} else {?>	
	<?/*?><script src="/js/marquiz.js"></script><?*/?>
<?}?> 

<?$APPLICATION->ShowHead()?>


<title><?=$APPLICATION->ShowTitle(true)?></title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">

<!-- Top.Mail.Ru counter -->
<script type="text/javascript">
var _tmr = window._tmr || (window._tmr = []);
_tmr.push({id: "3755179", type: "pageView", start: (new Date()).getTime(), pid: "USER_ID"});
(function (d, w, id) {
  if (d.getElementById(id)) return;
  var ts = d.createElement("script"); ts.type = "text/javascript"; ts.async = true; ts.id = id;
  ts.src = "https://top-fwz1.mail.ru/js/code.js";
  var f = function () {var s = d.getElementsByTagName("script")[0]; s.parentNode.insertBefore(ts, s);};
  if (w.opera == "[object Opera]") { d.addEventListener("DOMContentLoaded", f, false); } else { f(); }
})(document, window, "tmr-code");
</script>
<noscript><div><img src="https://top-fwz1.mail.ru/counter?id=3755179;js=na" style="position:absolute;left:-9999px;" alt="Top.Mail.Ru" /></div></noscript>
<!-- /Top.Mail.Ru counter -->



<!-- Yandex.Metrika counter -->
<script type="text/javascript" >
   	(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
  	 m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
  	(window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

    ym(98615635, "init", {
        clickmap:true,
        trackLinks:true,
        accurateTrackBounce:true,
        ecommerce:"dataLayer",
		webvisor:true,
    });
	<?if($isTgSearch){?>
	ym(97251064, "init", {
        clickmap:true,
        trackLinks:true,
        accurateTrackBounce:true,
        ecommerce:"dataLayer"
    });
	<?}?>
</script>
<script>
(function() {
  // Определение GPU
  var canvas = document.createElement('canvas');
  var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
  var debugInfo = gl ? gl.getExtension('WEBGL_debug_renderer_info') : null;
  var gpuVendor = debugInfo ? gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL) : 'Unknown';
  var gpuRenderer = debugInfo ? gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) : 'Unknown';
  var gpu = gpuVendor + ' | ' + gpuRenderer;

  // Определение батареи
  navigator.getBattery().then(function(battery) {
    ym(98615635, 'params', {
      gpu: gpu,
      charging: battery.charging,
      battery_level: Math.round(battery.level * 100)
    });
  });
})();
</script>
<noscript>
	<div>
		<img src="https://mc.yandex.ru/watch/98615635" style="position:absolute; left:-9999px;" alt="" />
		<?if($isTgSearch){?><img src="https://mc.yandex.ru/watch/97251064" style="position:absolute; left:-9999px;" alt="" /><?}?>
	</div>
</noscript>
<!-- /Yandex.Metrika counter -->
<script type="text/javascript">
!function(n,e,t,r,a,s){function i(n,r){var a=e.createElement(t),s=e.getElementsByTagName(t)[0];a.async=1,a.src=n,a.onerror=r,s.parentNode.insertBefore(a,s)}n.SalesNinja=["init","start","onPersonalization","reachGoal"].reduce(function(e,t){return e[t]=function(){var e=Array.prototype.slice.call(arguments);e.unshift(t),n[r].apply(0,e)},e},{k:r,ready:!1}),n[r]=function(){var e,t,a=new Promise(function(n,r){e=n,t=r});return(n[r].r=n[r].r||[]).push({s:e,f:t}),(n[r].c=n[r].c||[]).push(arguments),a},i(a,function(){i(s)})}(window,document,"script","ninja","https://cdn.sales-ninja.me/userBundle.js","https://bundle.sales-ninja.me/userBundle.js");

ninja('init', '2ae97c3a-631c-48d5-b283-548f9ca42335', {sessionCookieKey: 'sn-sid', customerCookieKey: 'sn-cid'});

ninja('start');
</script>



</head>
<body>
    <div class="headerWrap">
        <div class="header">
			<?/*?>
			<div class="topLinks">
				<span>Туристам</span>
				<a href="/b2b/" target="_blank">Агентствам</a>
			</div>
			<?*/?>
			<div class="kusr">
				<?=$params["CURRENCY"]?>
			</div>
            <a href="/" class="logo <?if (count($params["PHONE"])==1){?>onePhone<?}?>"></a>
			<?if (count($params["PHONE"])==2){?>
			<div class="phone1">
                <a class="phoneLink" href="tel:<?=$params["PHONE"][0]?>"><?=$params["PHONE"][0]?></a>
            </div>
            <div class="phone2">
                 <a class="phoneLink" href="tel:<?=$params["PHONE"][1]?>"><?=$params["PHONE"][1]?></a>
            </div>
			<?}else{?>
			<div class="phone">
                <a class="phoneLink" href="tel:<?=$params["PHONE"][0]?>"><?=$params["PHONE"][0]?></a>
            </div>
			<?}?>
            <a href="/personal/" class="personalLink" target="_blank">Личный кабинет</a>
			
			<?/*?>

			<div class="regionBlock <?if (count($params["PHONE"])==1){?>oneReg<?}?>">
				<?if ($params["SITE_URL"]=="https://anextours.ru/"){?>
				<span>Выберите регион</span>
				<?}else{?>
				Ваш регион: <span><?=$params["CITY"]?></span>
				<?}?>
				<div class="regionsList">
					<a class="listItem" href="https://msk.<?=$mainSite?>/">Москва</a>
					<a class="listItem" href="https://spb.<?=$mainSite?>/">Санкт-Петербург</a>
					<?/*?><a class="listItem" href="https://anapa.anextours.ru/">Анапа</a>
					<a class="listItem" href="https://arhangelsk.anextours.ru/">Архангельск</a>
					<a class="listItem" href="https://astrahan.anextours.ru/">Астрахань</a>
					<a class="listItem" href="https://barnaul.anextours.ru/">Барнаул</a>
					<a class="listItem" href="https://belgorod.anextours.ru/">Белгород</a>
					<a class="listItem" href="https://bsk.anextours.ru/">Благовещенск</a>
					<a class="listItem" href="https://vladivostok.anextours.ru/">Владивосток</a>
					<a class="listItem" href="https://vladikavkaz.anextours.ru">Владикавказ</a>
					<a class="listItem" href="https://volgograd.anextours.ru/">Волгоград</a>
					<a class="listItem" href="https://voronezh.anextours.ru/">Воронеж</a>
					<a class="listItem" href="https://grozny.anextours.ru/">Грозный</a>
					<a class="listItem" href="https://ekb.anextours.ru/">Екатеринбург</a>
					<a class="listItem" href="https://irkutsk.anextours.ru/">Иркутск</a>
					<a class="listItem" href="https://kazan.anextours.ru/">Казань</a>
					<a class="listItem" href="https://kaliningrad.anextours.ru/">Калининград</a>
					<a class="listItem" href="https://kaluga.anextours.ru/">Калуга</a>
					<a class="listItem" href="https://kemerovo.anextours.ru/">Кемерово</a>
					<a class="listItem" href="https://kostroma.anextours.ru/">Кострома</a>
					<a class="listItem" href="https://krasnodar.anextours.ru/">Краснодар</a>
					<a class="listItem" href="https://krsk.anextours.ru/">Красноярск</a>
					<a class="listItem" href="https://magadan.anextours.ru/">Магадан</a>
					<a class="listItem" href="https://mgsk.anextours.ru/">Магнитогорск</a>
					<a class="listItem" href="https://mahachkala.anextours.ru/">Махачкала</a>
					<a class="listItem" href="https://minvod.anextours.ru/">Мин. Воды</a>
					<a class="listItem" href="https://murmansk.anextours.ru/">Мурманск</a>
					<a class="listItem" href="https://nvsk.anextours.ru/">Нижневартовск</a>
					<a class="listItem" href="https://nksk.anextours.ru/">Нижнекамск</a>
					<a class="listItem" href="https://nnv.anextours.ru/">Нижний Новгород</a>
					<a class="listItem" href="https://nzsk.anextours.ru/">Новокузнецк</a>
					<a class="listItem" href="https://novosibirsk.anextours.ru/">Новосибирск</a>
					<a class="listItem" href="https://omsk.anextours.ru/">Омск</a>
					<a class="listItem" href="https://orenburg.anextours.ru">Оренбург</a>
					<a class="listItem" href="https://penza.anextours.ru/">Пенза</a>
					<a class="listItem" href="https://perm.anextours.ru/">Пермь</a><?*?>
					<a class="listItem" href="https://rostov.<?=$mainSite?>/">Ростов-на-Дону</a>
					<a class="listItem" href="https://samara.<?=$mainSite?>/">Самара</a>
					<?/*?><a class="listItem" href="https://saransk.anextours.ru/">Саранск</a>
					<a class="listItem" href="https://saratov.anextours.ru/">Саратов</a>
					<a class="listItem" href="https://smp.anextours.ru/">Симферополь</a>
					<a class="listItem" href="https://sochi.anextours.ru/">Сочи</a>
					<a class="listItem" href="https://stvp.anextours.ru/">Ставрополь</a>
					<a class="listItem" href="https://surgut.anextours.ru/">Сургут</a>
					<a class="listItem" href="https://skr.anextours.ru/">Сыктывкар</a>
					<a class="listItem" href="https://tomsk.anextours.ru/">Томск</a>
					<a class="listItem" href="https://tumen.anextours.ru/">Тюмень</a>
					<a class="listItem" href="https://georg.anextours.ru/">Черняховск</a>
					<a class="listItem" href="https://uld.anextours.ru/">Улан-удэ</a>
					<a class="listItem" href="https://ulyanovsk.anextours.ru/">Ульяновск</a>
					<a class="listItem" href="https://ufa.anextours.ru/">Уфа</a>
					<a class="listItem" href="https://habarovsk.anextours.ru/">Хабаровск</a>
					<a class="listItem" href="https://hmsk.anextours.ru/">Ханты-Мансийск</a>
					<a class="listItem" href="https://chb.anextours.ru/">Челябинск</a>
					<a class="listItem" href="https://chita.anextours.ru/">Чита</a>
					<a class="listItem" href="https://usk.anextours.ru/">Южно-Сахалинск</a><?*?>
				</div>
			</div>
			<?*/?>
           
            <div class="clear"></div>
			<a href="" data-type="ajax" data-src="/js/ajax.php" class="sendOrder" >
				Отправить заявку
			</a>
        </div>
		<div class="mobileMenuWrap">
			<div class="mobileMenuButton">
				<span></span><span></span><span></span>
			</div>
			<div class="mobileMenu">
				<ul>
					<?if ($params["GEORG"]){?>
					<li>
						<a href="/country-kld/">Туры из Калининграда</a>
					</li>
					<?}?>
					<li>
					   <?if ($params["GEORG"]){?>
						<a href="/country/">Туры из Москвы</a>
						<?} else {?>
						<a href="/country/">Туры по странам</a>
						<?}?>
					</li>
                    <li><a href="/poisk-turov/">Поиск туров</a></li>
                    <li><a href="/hot/">Горящие туры</a></li>
                    <li><a href="/rb/">Раннее бронирование</a></li>
                    <li><a href="/contacts/">Контакты</a></li>
					<li><a href="/how-to-buy/">Купить тур онлайн</a></li>
				</ul>	
			</div>
		</div>
		<div class="clear"></div>
		
        <div class="menuWrap">
            <div class="menu <?if ($params["GEORG"]){?>wide<?}?>">
                <ul>
					<?if ($params["GEORG"]){?>
					<li>
						<a href="/country-kld/">Туры из Калининграда</a>
						
						<div class="subMenu <?if (count($params["COUNTRY_LIST_KLD"])<9){?>narrow<?}?>">
							<ul class="subMenuUl ">
								<?
								$i=1;
								foreach($params["COUNTRY_LIST_KLD"] as $cname=>$curl){
								?>
								<li><a href="<?=$curl["URL"]?>" class="<?=$curl["CLASS"]?>"><?=$cname?></a></li>
								<?
								if ($i%9==0 && $i<count($params["COUNTRY_LIST_KLD"])){?>
								</ul>	
								<ul class="subMenuUl">
								<?}
								$i++;
								}?>
							</ul>
							
							<div class="clear"></div>
						</div>
                    </li>
					<?}?>
                    <li>
						<?if ($params["GEORG"]){?>
						<a href="/country/">Туры из Москвы</a>
						<?} else {?>
                        <a href="/country/">Туры по странам</a>
						<?}?>
						<div class="subMenu <?if (count($params["COUNTRY_LIST"])<9){?>narrow<?}?>">
							<ul class="subMenuUl ">
								<?
								$i=1;
								foreach($params["COUNTRY_LIST"] as $cname=>$curl){
									$class=str_replace(array("/country/","/"),"",$curl); 
									?>
								<li><a href="<?=$curl?>" class="<?=$class?>"><?=$cname?></a></li>
								<?
								if ($i%4==0 && $i<count($params["COUNTRY_LIST"])){?>
								</ul>	
								<ul class="subMenuUl">
								<?}
								$i++;
								}?>
							</ul>
							<ul class="subMenuUl">
								<li><a href="/country/" class="other">Другие страны</a></li>
							</ul>
							<div class="clear"></div>
						</div>
                    </li>
					
                    <li><a href="/poisk-turov/">Поиск туров</a></li>
                    <li><a href="/hot/">Горящие туры</a></li>
                    <?/*?><li><a href="/rb/">Раннее бронирование</a></li>		<?*/?>
                    <li><a href="/contacts/">Контакты</a></li>
                </ul> 
				<a href="/how-to-buy/" class="onlineLink">Купить тур онлайн</a>	
            </div>
        </div>
    </div>

    <div class="body">
		<?if (!$isMain && !$isTailand){?>
			<div class="middle">
				<?if (!$isSPay && !$isEPay && !$isPay){?>
				<?=$APPLICATION->ShowViewContent('hotel_stars');?>
				<h1><?=$APPLICATION->ShowTitle(false)?></h1>
				<?}?>
		<?}elseif($isTailand){?>
			<div class="middleWide">
		<?}?>	


      