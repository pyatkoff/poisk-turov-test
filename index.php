<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Поиск туров");

$dir = $APPLICATION->GetCurDir();
$baseDir = "/poisk-turov-test/";
$code = "";
if ($dir !== $baseDir)
{
    $code = str_replace([$baseDir, "/"], "", $dir);
}
?>

<div data-marquiz-id="64a2e3becefb38002582445a"></div>
<script>(function(t, p) {window.Marquiz ? Marquiz.add([t, p]) : document.addEventListener('marquizLoaded', function() {Marquiz.add([t, p])})})('Button', {id: '64a2e3becefb38002582445a', buttonText: 'Хотите подберем Вам тур?', bgColor: '#d34085', textColor: '#fff', rounded: true, shadow: 'rgba(211, 64, 133, 0.5)', blicked: true})</script>

<?$APPLICATION->IncludeComponent(
    "rhat.search:form",
    "form",
    Array(
        "FROM" => $params["TV_CITY"],
        "COUNTRY" => 4,
        "CACHE_TYPE" => "N",
        "FORM_CODE" => $code
    ),
    false
);?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
