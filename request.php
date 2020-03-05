<?php
$path = $_SERVER['DOCUMENT_ROOT'];
$carpeta = dirname( __FILE__ );
include_once $path . '/wp-load.php';
include_once $carpeta . '/miembro-press.php';

$id = consulta_secret('hotmart_id');
$secret = consulta_secret('hotmart_secretid');
$basic = consulta_secret('hotmart_basic');
$transaccion = $_GET['transaction'];

$URL = "https://api-sec-vlc.hotmart.com/security/oauth/token?grant_type=client_credentials&";
$headers = ['Authorization: Basic '.$basic];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, "client_id=$id&client_secret=$secret");
curl_setopt($ch, CURLOPT_POST, true);
$dates = curl_exec($ch);
curl_close($ch);
$otro = json_decode($dates, true);
$token = $otro['access_token'];

$consulta = "https://api-hot-connect.hotmart.com/reports/rest/v2/history?transaction=".$transaccion;
$authorization = "Authorization: Bearer " . $token;
$retorno = curl_get($consulta, $authorization);
$result = json_decode($retorno, true);
foreach($result['data'] as $data){
    foreach($data as $purchase){
		$keyOffert = $purchase['key'];
        if ($purchase['status'] == 'APPROVED'){
            $approved = true;
        };
    }
}

if ($approved) {
    $consulta = "https://api-hot-connect.hotmart.com/reports/rest/v2/purchaseDetails?transaction=".$transaccion;
    $jsonCodificado = curl_get($consulta, $authorization);
    $result = json_decode($jsonCodificado, true);
    foreach($result['data'] as $data){
        $producto = $data['productId'];
    }
	$offert = "https://api-hot-connect.hotmart.com/product/rest/v2/$producto/offers/";
	$offertCodificado = curl_get($offert, $authorization);
	$ofertas = json_decode($offertCodificado, true);
	foreach($ofertas['data'] as $data){
		if ($data['key'] == $keyOffert){
			$existeOffert = true;
			$nombrePlan = $data['planName'];
		}
    }
	$idProducto = consulta_items($producto);
	if($existeOffert){
		if($idProducto != ""){
			$hashlink = obtener_hash_por_offert($nombrePlan);
			if (!empty($hashlink)){
				$redirectLink = generar_enlace($hashlink);
				header("Location: $redirectLink");
			}
		}
	}else{
		if($idProducto != ""){
			$hashlink = obtener_hash($idProducto);
			if (!empty($hashlink)){
				$redirectLink = generar_enlace($hashlink);
				header("Location: $redirectLink");
			}
		}
	}
}

function curl_get($consulta, $authorization){
    $cp = curl_init();
    curl_setopt($cp, CURLOPT_URL, $consulta);
    curl_setopt($cp, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($cp, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
    curl_setopt($cp, CURLOPT_CUSTOMREQUEST, "GET");
    $retorno = curl_exec($cp);
    curl_close($cp);
    return $retorno;
}

function consulta_secret($valor){
    global $wpdb;
    $prefix = $wpdb->prefix . "miembropress_";
    $settingsTable = $prefix . "settings";
    $registros = $wpdb->get_var( "SELECT option_value FROM $settingsTable WHERE option_name = '$valor'" );
    return $registros;
}

function consulta_items($producto){
    global $membergenius;
    $idProducto = "";
    $levels = $membergenius->model->getLevels();
    $items = $membergenius->model->setting("hotmart_items");
    foreach ($levels as $level){
		$item = "";
		if ($items[$level->ID]) {
			$item = $items[$level->ID];
			if($item == $producto){
			    $idProducto = $level->ID;
			}
		}
    }
    return $idProducto;
}

function obtener_hash($idProducto){
    global $wpdb;
    $prefix = $wpdb->prefix . "miembropress_";
    $levelsTable = $prefix . "levels";
    $hashlink = $wpdb->get_var( "SELECT level_hash FROM $levelsTable WHERE ID = '$idProducto'" );
    return $hashlink;
}

function obtener_hash_por_offert($nombrePlan){
    global $wpdb;
    $prefix = $wpdb->prefix . "miembropress_";
    $levelsTable = $prefix . "levels";
    $hashlink = $wpdb->get_var( "SELECT level_hash FROM $levelsTable WHERE level_name = '$nombrePlan'" );
    return $hashlink;
}

function generar_enlace($hashlink){
    global $membergenius;
    $checkout = $membergenius->model->signupURL($hashlink);
    return htmlentities($checkout);
}

?>
