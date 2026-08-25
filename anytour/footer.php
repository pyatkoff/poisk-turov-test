<?if (!$isMain ){?>
	</div>
<?}?>	
<div class="footer">
	<div class="lastMenu">
		<ul>
			<li><a href="/payment/">Оплата туров</a></li>
			<li><a href="/personal-data/">Согласие на обработку персональных данных</a></li>
			<li><a href="/politika-konfidentsialnosti/">Политика конфиденциальности</a></li>
			
		</ul>
	</div>
    <p class="footer-list-item">
        &copy; <?=date("Y")?> «ТУРАГЕНТСТВО ANYTour» <?=$params["CITY"]?> | Все права защищены.
    </p>
	<span class="pay_system_icons">
		<i alt="MasterCard" title="MasterCard" class="mastercard"></i>
		<i alt="Visa" title="Visa" class="visa"></i>
		<i alt="Мир" title="Мир" class="mir"></i>					
	</span>
</div>

<?/*if($_SESSION['isAndroid'])
{?>
<div class="mobileAppBlock mobileAppAndroid">
	Скачайте наше приложение из <a href="https://play.google.com/store/apps/details?id=ru.tours39.anex" target="_blank">Google Play</a><br />
	<a href="https://play.google.com/store/apps/details?id=ru.tours39.anex"><img src="https://anytour.com/images/logo-google-play2.png" /></a>
	<div class="closeMobileApp"></div>
</div>
<?}*/?>

<div class="tgChanelSub">
	<a class="tgChanelSubLink" href="" target="_blank">
		<svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.65 1.31029L13.2302 14.6006C13.2302 14.6006 12.8917 15.5223 11.961 15.0799L8.518 12.2044L6.30927 14.3981C6.30927 14.3981 6.13657 14.5408 5.94772 14.4514L6.37064 10.3766L6.38493 10.3887L6.39388 10.3056C6.39388 10.3056 12.5872 4.24118 12.841 3.9831C13.0948 3.72502 13.0102 3.66972 13.0102 3.66972C13.0271 3.35639 12.5533 3.66972 12.5533 3.66972L4.34632 9.34714L0.92815 8.09366C0.92815 8.09366 0.403596 7.89093 0.352831 7.44853C0.302066 7.00614 0.945054 6.76647 0.945054 6.76647L14.5331 0.960027C14.5331 0.960027 15.65 0.425502 15.65 1.31029Z" fill="currentColor"></path></svg>
		Подпишитесь на наш канал
	</a>
	<div class="tgChanelSubClose"></div>
</div>




<?if (strpos($APPLICATION->GetCurDir(),"/country/")===false){?>

<script src="//code-ya.jivosite.com/widget/XtQOwqmLoH" async></script>
<?}?>
<script type="text/javascript">!function(){var t=document.createElement("script");t.type="text/javascript",t.async=!0,t.src='https://vk.com/js/api/openapi.js?169',t.onload=function(){VK.Retargeting.Init("VK-RTRG-1315094-ftapB"),VK.Retargeting.Hit()},document.head.appendChild(t)}();</script><noscript><img src="https://vk.com/rtrg?p=VK-RTRG-1315094-ftapB" style="position:fixed; left:-999px;" alt=""/></noscript>

</body>
</html>