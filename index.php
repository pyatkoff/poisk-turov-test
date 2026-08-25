<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Поиск туров");
$dir = $APPLICATION->GetCurDir();
$code = "";
if($dir!="/poisk-turov/")
{
	$code = str_replace(["/poisk-turov/","/"],"",$dir);
}


?>

<div data-marquiz-id="64a2e3becefb38002582445a"></div>
<script>(function(t, p) {window.Marquiz ? Marquiz.add([t, p]) : document.addEventListener('marquizLoaded', function() {Marquiz.add([t, p])})})('Button', {id: '64a2e3becefb38002582445a', buttonText: 'Хотите подберем Вам тур?', bgColor: '#d34085', textColor: '#fff', rounded: true, shadow: 'rgba(211, 64, 133, 0.5)', blicked: true})</script>

<?/*?>
<div style="margin:0 auto; max-width:1020px; padding:20px;">
<div class="tv-search-form tv-moduleid-175393" tv-departure="<?=$params["TV_CITY"]?>"></div>
<sc ript type="text/javascript" src="//tourvisor.ru/module/init.js"></sc ript>
</div>
<?*/?>
<??>
<?$APPLICATION->IncludeComponent(
	"rhat.search:form", "form",
	Array(
		"FROM" =>$params["TV_CITY"],
		"COUNTRY" => 	4,
		"CACHE_TYPE" => "N",
		"FORM_CODE"=>$code
	),
	false
);?> 
<??>



<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>