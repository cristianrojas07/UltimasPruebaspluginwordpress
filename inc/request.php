<?php

$path = dirname_for(4);
require_once($path . '/wp-load.php');
$carpeta = dirname(dirname(__FILE__));
require_once($carpeta . '/miembro-press.php');

function dirname_for($cantidad){
	$url = dirname(__FILE__);
	for($i = 0; $i<$cantidad; $i++){
		$url = dirname($url);
	}
	return $url;
}

function consulta_items($producto){
    global $miembropress;
    $levelId = "";
    $levels = $miembropress->model->getLevels();
    $items = $miembropress->model->setting("hotmart_items");
    foreach ($levels as $level){
        $item = "";
        if (isset($items[$level->ID])) {
            $item = $items[$level->ID];
            if($item == $producto){
                $levelId = $level->ID;
            }
        }
    }
    return $levelId;
}

function obtener_hash($levelId){
    global $wpdb;
    $prefix = $wpdb->prefix . "miembropress_";
    $levelsTable = $prefix . "levels";
    $hashlink = $wpdb->get_var( "SELECT level_hash FROM $levelsTable WHERE ID = '$levelId'" );
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
    global $miembropress;
    $checkout = $miembropress->model->signupURL($hashlink);
    return htmlentities($checkout);
}


if(isset($_POST) && isset($_POST['transaction'])){
    $transaccion = $_POST['transaction'];
    $existeOffert = false;
    $status = "";
    $productId = "";
    $nombrePlan = "";

    if(isset($_POST['status'])){
        $status = $_POST['status'];
    }

    if(isset($_POST['prod'])){
        $productId = $_POST['prod'];
    }

    if(isset($_POST['name_subscription_plan'])){
        $nombrePlan = $_POST['name_subscription_plan'];
    }
    
    if ($status == "approved" || $status == "completed") {
        if(isset($_POST['off'])){
            $existeOffert = true;
        }

        $levelId = consulta_items($productId);

        var_dump($transaccion);
        var_dump($status);
        var_dump($productId);
        var_dump($nombrePlan);
        var_dump($existeOffert);
        
        if(!$existeOffert){
            if($levelId != ""){
                $hashlink = obtener_hash_por_offert($nombrePlan);
                if (!empty($hashlink)){
                    $redirectLink = generar_enlace($hashlink);
                    header("Location: $redirectLink");
                }
            }
        }else{
            if($levelId != ""){
                $hashlink = obtener_hash($levelId);
                if (!empty($hashlink)){
                    $redirectLink = generar_enlace($hashlink);
					var_dump($redirectLink);
                    header("Location: $redirectLink");
                }
            }
        }
    }
}

?>
